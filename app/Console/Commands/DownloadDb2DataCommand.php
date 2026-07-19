<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class DownloadDb2DataCommand extends Command
{
    protected $signature = 'app:download-db2 {--table= : Download a specific table only}';

    protected $description = 'Download DB2 CSV data from wago.tools for WoW data import';

    /**
     * SimpleArmory JSON files to download: [url => local_filename].
     *
     * @var array<string, string>
     */
    private const SIMPLEARMORY_FILES = [
        'https://simplearmory.com/data/achievements.json' => 'achievements.json',
        'https://simplearmory.com/data/mounts.json' => 'mounts.json',
        'https://simplearmory.com/data/pets.json' => 'pets.json',
        'https://simplearmory.com/data/decors.json' => 'decors.json',
    ];

    /**
     * DB2 tables to download: [wago_table_name => [local_filename, locale]].
     *
     * Réduites aux mappings factions/extension (bitmasks FiltRaces, ContentTuning…)
     * que l'API officielle n'expose pas. Toutes les données de contenu (noms FR,
     * icônes, recettes, apparences…) viennent désormais de l'API Blizzard.
     *
     * @var array<string, array{0: string, 1: string|null}>
     */
    private const TABLES = [
        'AreaTable' => ['area_table.csv', 'frFR'],
        'ContentTuning' => ['content_tuning.csv', null],
        'QuestV2CliTask' => ['quest_v2_cli_task.csv', 'frFR'],
        'SkillLineAbility' => ['skill_line_ability.csv', null],
        'Faction' => ['faction.csv', 'frFR'],
    ];

    public function handle(): int
    {
        /** @var string|null $singleTable */
        $singleTable = $this->option('table');

        $tables = self::TABLES;
        if ($singleTable !== null) {
            if (! isset($tables[$singleTable])) {
                $this->error(sprintf('Unknown table "%s". Available: %s', $singleTable, implode(', ', array_keys($tables))));

                return self::FAILURE;
            }

            $tables = [$singleTable => $tables[$singleTable]];
        }

        $this->info(sprintf('Downloading %d DB2 table(s) from wago.tools...', count($tables)));
        $this->newLine();

        $success = 0;
        $failed = 0;

        foreach ($tables as $wagoTable => [$localFilename, $locale]) {
            // product=wow épingle le dernier build LIVE : sans lui, wago sert son dernier
            // build global, souvent un PTR dont la localisation frFR est incomplète.
            $url = sprintf('https://wago.tools/db2/%s/csv?product=wow', $wagoTable);
            if ($locale !== null) {
                $url .= '&locale='.$locale;
            }

            $this->info(sprintf('  Downloading %s → %s...', $wagoTable, $localFilename));

            $response = Http::timeout(120)->get($url);

            if (! $response->successful()) {
                $this->error(sprintf('    FAILED (HTTP %d)', $response->status()));
                $failed++;

                continue;
            }

            $content = $response->body();
            $lines = substr_count($content, "\n");

            $dir = storage_path('app/blizzard');
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            file_put_contents($dir.'/'.$localFilename, $content);
            $this->info(sprintf('    OK (%d lines, %s)', $lines, $this->formatBytes(strlen($content))));
            $success++;
        }

        $this->newLine();

        if ($singleTable === null) {
            $this->info('Downloading SimpleArmory JSON data...');

            foreach (self::SIMPLEARMORY_FILES as $url => $localFilename) {
                $result = $this->downloadExtraFile($url, $localFilename);
                if ($result) {
                    $success++;
                } else {
                    $failed++;
                }
            }

            $this->newLine();
        }

        $this->info(sprintf('Done: %d succeeded, %d failed.', $success, $failed));

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function downloadExtraFile(string $url, string $localFilename): bool
    {
        $this->info(sprintf('  Downloading %s...', $localFilename));

        $response = Http::timeout(120)->get($url);

        if (! $response->successful()) {
            $this->error(sprintf('    FAILED (HTTP %d)', $response->status()));

            return false;
        }

        $content = $response->body();
        $dir = storage_path('app/blizzard');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($dir.'/'.$localFilename, $content);
        $this->info(sprintf('    OK (%s)', $this->formatBytes(strlen($content))));

        return true;
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }

        if ($bytes < 1048576) {
            return round($bytes / 1024, 1).' KB';
        }

        return round($bytes / 1048576, 1).' MB';
    }
}
