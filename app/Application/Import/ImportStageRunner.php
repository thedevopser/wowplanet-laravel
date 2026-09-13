<?php

declare(strict_types=1);

namespace App\Application\Import;

use App\Application\DTOs\AppearanceImportProgress;
use App\Infrastructure\Blizzard\BlizzardBatchImporter;
use App\Infrastructure\Blizzard\HourlyBudgetGuard;
use App\Infrastructure\Mappings\FrozenAreaExpansionMap;
use App\Infrastructure\Reference\FactionReference;
use App\Infrastructure\Reference\ReferenceMaps;
use Illuminate\Support\Facades\Artisan;

/**
 * Exécute une passe d'une étape d'import et rend ce qu'elle a fait.
 *
 * Une étape qui échoue est rapportée, jamais propagée : le rôle de ce lanceur est que
 * l'échec d'une entité n'emporte pas les suivantes. Ce qu'elle a écrit se mesure sur la
 * base, ce qu'elle a consommé sur le compteur de budget, sans second mécanisme.
 *
 * Seule la garde-robe rend la main avant d'avoir fini : son offset désigne la fenêtre
 * où reprendre, et la passe suivante repart de là.
 */
final readonly class ImportStageRunner
{
    private const REFERENCE_SYNC_COMMAND = 'app:wow-reference-sync';

    public function __construct(
        private BlizzardBatchImporter $blizzardBatchImporter,
        private ReferenceMaps $referenceMaps,
        private FactionReference $factionReference,
        private RowTallyCounter $rowTallyCounter,
        private HourlyBudgetGuard $hourlyBudgetGuard,
    ) {}

    public function run(ImportStep $importStep, bool $full, ?int $limit): ImportStageResult
    {
        $tables = $importStep->stage->tables();
        $startedAt = now();
        $rowsBefore = $this->rowTallyCounter->count($tables);
        $callsBefore = $this->hourlyBudgetGuard->totalConsumed();
        $startedMs = (int) (microtime(true) * 1000);

        try {
            $progress = $this->execute($importStep, $full, $limit);
        } catch (\Throwable $throwable) {
            return new ImportStageResult(
                $importStep->failed($throwable->getMessage(), $this->elapsedMs($startedMs)),
                null,
            );
        }

        $tally = $this->rowTallyCounter->tally($tables, $startedAt, $rowsBefore);
        $calls = $this->hourlyBudgetGuard->totalConsumed() - $callsBefore;
        $durationMs = $this->elapsedMs($startedMs);

        if ($progress instanceof AppearanceImportProgress && ! $progress->done) {
            return new ImportStageResult(
                $importStep->advanced($tally, $calls, $durationMs, $progress->offset, $progress->total),
                $progress->secondsUntilBudget > 0 ? ImportWait::hourlyBudget($progress->secondsUntilBudget) : null,
            );
        }

        return new ImportStageResult($importStep->finished($tally, $calls, $durationMs), null);
    }

    /**
     * Rend l'avancement de la passe pour une étape reprenable, `null` pour les autres.
     */
    private function execute(ImportStep $importStep, bool $full, ?int $limit): ?AppearanceImportProgress
    {
        return match ($importStep->stage) {
            ImportStage::Reference => $this->syncReference(),
            ImportStage::Achievements => $this->nothingToResume($this->blizzardBatchImporter->importAchievements(...)),
            ImportStage::Quests => $this->importQuests(),
            ImportStage::Professions => $this->importProfessions(),
            ImportStage::Mounts => $this->nothingToResume($this->blizzardBatchImporter->importMounts(...)),
            ImportStage::Pets => $this->nothingToResume($this->blizzardBatchImporter->importPets(...)),
            ImportStage::Decor => $this->nothingToResume($this->blizzardBatchImporter->importDecor(...)),
            ImportStage::Appearances => $this->sweepAppearances($importStep, $full, $limit),
        };
    }

    private function syncReference(): null
    {
        $exitCode = Artisan::call(self::REFERENCE_SYNC_COMMAND, []);

        throw_if($exitCode !== 0, \RuntimeException::class, trim(Artisan::output()));

        return null;
    }

    private function importQuests(): null
    {
        $this->blizzardBatchImporter->importQuests(
            FrozenAreaExpansionMap::load(),
            $this->referenceMaps->questExpansions(),
            $this->referenceMaps->questFactions(),
            $this->referenceMaps->zoneFactions(),
        );

        $this->blizzardBatchImporter->tagMirrorQuestFactions($this->factionReference->factions());

        return null;
    }

    private function importProfessions(): null
    {
        $this->blizzardBatchImporter->importProfessions($this->referenceMaps->recipeFactions());
        $this->blizzardBatchImporter->tagMirrorRecipeFactions();

        return null;
    }

    private function sweepAppearances(ImportStep $importStep, bool $full, ?int $limit): AppearanceImportProgress
    {
        /** @var int $timeBox */
        $timeBox = config('services.blizzard.import_chunk_timebox', 600);

        return $this->blizzardBatchImporter->importAppearanceChunk($full, $importStep->offset, $timeBox, $limit);
    }

    /**
     * @param  callable(): void  $import
     */
    private function nothingToResume(callable $import): null
    {
        $import();

        return null;
    }

    private function elapsedMs(int $startedMs): int
    {
        return max(0, (int) (microtime(true) * 1000) - $startedMs);
    }
}
