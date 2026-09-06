<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use stdClass;
use Throwable;

/**
 * One-shot transfer of the legacy SQLite database into PostgreSQL, for the
 * production switch-over. Delete this command, and the sqlite_legacy connection it
 * reads from, once the sqlite-data volume is retired.
 */
class MigrateLegacySqliteCommand extends Command
{
    protected $signature = 'app:migrate-legacy-sqlite {--dry-run : Report the source volumes without writing anything}';

    protected $description = 'Transfer the legacy SQLite data into PostgreSQL';

    private const LEGACY_CONNECTION = 'sqlite_legacy';

    private const CHUNK_SIZE = 500;

    /**
     * Cache, sessions and the queue are not carried over: the first two move to
     * Redis, and the queue is empty at switch-over since the worker is stopped.
     * Everyone is logged out and goes back through Battle.net, which is accepted.
     */
    private const SKIPPED_TABLES = [
        'migrations',
        'cache',
        'cache_locks',
        'jobs',
        'job_batches',
        'failed_jobs',
        'sessions',
    ];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        /** @var string $legacyDatabase */
        $legacyDatabase = config('database.connections.'.self::LEGACY_CONNECTION.'.database');

        $this->info('Legacy database: '.$legacyDatabase);
        $this->newLine();

        $tables = $this->tablesToTransfer();

        if ($tables === []) {
            $this->error('No table to transfer: the legacy database holds none of the expected tables.');

            return self::FAILURE;
        }

        /** @var list<string> $failures */
        $failures = [];

        foreach ($tables as $table) {
            $sourceCount = DB::connection(self::LEGACY_CONNECTION)->table($table)->count();

            if ($dryRun) {
                $this->line(sprintf('  %-24s %8d rows', $table, $sourceCount));

                continue;
            }

            try {
                $this->transfer($table);
            } catch (Throwable $throwable) {
                $failures[] = sprintf('%s: %s', $table, $throwable->getMessage());
                $this->line(sprintf('  %-24s %8d -> <fg=red>failed</>', $table, $sourceCount));

                continue;
            }

            $targetCount = DB::table($table)->count();
            $matches = $targetCount === $sourceCount;

            if (! $matches) {
                $failures[] = sprintf('%s: %d rows read, %d rows written', $table, $sourceCount, $targetCount);
            }

            $this->line(sprintf(
                '  %-24s %8d -> %-8d %s',
                $table,
                $sourceCount,
                $targetCount,
                $matches ? '<fg=green>ok</>' : '<fg=red>mismatch</>',
            ));
        }

        if ($dryRun) {
            $this->newLine();
            $this->info('Dry run: nothing was written.');

            return self::SUCCESS;
        }

        $this->resetSequences($tables);

        $this->newLine();

        if ($failures !== []) {
            $this->error('Transfer incomplete:');
            foreach ($failures as $failure) {
                $this->error('  - '.$failure);
            }

            return self::FAILURE;
        }

        $this->info('Transfer complete, every table matches.');

        return self::SUCCESS;
    }

    /**
     * The target schema decides what gets transferred. Columns and tables that only
     * exist in SQLite are left behind by construction, which is how the abandoned
     * schema stays abandoned instead of having to be listed here.
     *
     * @return list<string>
     */
    private function tablesToTransfer(): array
    {
        /** @var list<string> $listing */
        $listing = Schema::getTableListing(schemaQualified: false);

        $tables = [];

        foreach ($listing as $table) {
            if (in_array($table, self::SKIPPED_TABLES, true)) {
                continue;
            }

            if (! Schema::connection(self::LEGACY_CONNECTION)->hasTable($table)) {
                $this->warn(sprintf('  %-24s absent from the legacy database, skipped.', $table));

                continue;
            }

            $tables[] = $table;
        }

        sort($tables);

        return $tables;
    }

    /**
     * One transaction per table. A row the target refuses aborts the whole
     * PostgreSQL transaction anyway, so the choice is between rolling the table back
     * and leaving it half transferred — and half transferred is the outcome this
     * command exists to make impossible.
     */
    private function transfer(string $table): void
    {
        $columns = $this->sharedColumns($table);
        $key = $this->primaryKey($table);
        $updatable = array_values(array_diff($columns, $key));

        DB::transaction(function () use ($table, $columns, $key, $updatable): void {
            DB::connection(self::LEGACY_CONNECTION)
                ->table($table)
                ->select($columns)
                ->orderBy($key[0])
                ->chunk(self::CHUNK_SIZE, function (Collection $rows) use ($table, $key, $updatable): void {
                    /** @var list<array<string, scalar|null>> $values */
                    $values = array_map(
                        static fn (stdClass $row): array => (array) $row,
                        array_values($rows->all()),
                    );

                    DB::table($table)->upsert($values, $key, $updatable);
                });
        });
    }

    /**
     * @return list<string>
     */
    private function sharedColumns(string $table): array
    {
        /** @var list<string> $legacy */
        $legacy = Schema::connection(self::LEGACY_CONNECTION)->getColumnListing($table);
        /** @var list<string> $target */
        $target = Schema::getColumnListing($table);

        return array_values(array_intersect($target, $legacy));
    }

    /**
     * @return list<string>
     */
    private function primaryKey(string $table): array
    {
        /** @var list<array{name: string, columns: list<string>, primary: bool, unique: bool}> $indexes */
        $indexes = Schema::getIndexes($table);

        foreach ($indexes as $index) {
            if ($index['primary']) {
                return $index['columns'];
            }
        }

        throw new RuntimeException(sprintf('Table %s has no primary key to upsert on.', $table));
    }

    /**
     * Ids come across as they stand, so every sequence is still sitting at 1. The
     * first application insert would collide with a transferred row.
     *
     * @param  list<string>  $tables
     */
    private function resetSequences(array $tables): void
    {
        foreach ($tables as $table) {
            /** @var list<array{name: string, type_name: string, auto_increment: bool, nullable: bool}> $columns */
            $columns = Schema::getColumns($table);

            foreach ($columns as $column) {
                if (! $column['auto_increment']) {
                    continue;
                }

                $name = $column['name'];

                DB::statement(sprintf(
                    'SELECT setval(pg_get_serial_sequence(?, ?), COALESCE((SELECT MAX(%s) FROM %s), 0) + 1, false)',
                    $name,
                    $table,
                ), [$table, $name]);

                $this->line(sprintf('  sequence %s.%s repositioned', $table, $name));
            }
        }
    }
}
