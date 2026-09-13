<?php

declare(strict_types=1);

namespace App\Application\Import;

use App\Infrastructure\Blizzard\BlizzardApiClient;
use App\Infrastructure\Blizzard\HourlyBudgetGuard;
use App\Infrastructure\Blizzard\ImportBuildGate;

/**
 * Enchaîne les étapes d'un import, une passe à la fois, et publie ce qu'elles font.
 *
 * Une passe et une seule par appel : c'est ce qui permet à un job de relâcher le worker
 * entre deux étapes, et à une commande de boucler sans que le déroulé diffère. L'état
 * rendu est complet, donc rejouable — une reprise repart du même point que le suivi.
 *
 * Une étape terminée est retenue par la porte de build : un worker redémarré, un
 * conteneur relancé, et l'import reprend à l'étape qui n'a pas abouti plutôt que tout
 * refaire. C'est la base durable de la reprise, le suivi n'étant qu'un affichage.
 */
final readonly class ImportPipeline
{
    public function __construct(
        private ImportStageRunner $importStageRunner,
        private ImportProgressStore $importProgressStore,
        private ImportBuildGate $importBuildGate,
        private BlizzardApiClient $blizzardApiClient,
        private HourlyBudgetGuard $hourlyBudgetGuard,
        private ImportWaitReporter $importWaitReporter,
    ) {}

    /**
     * @param  list<ImportStage>  $stages
     */
    public function begin(string $jobId, array $stages, bool $force): ImportRun
    {
        $importRun = ImportRun::start($jobId, $stages, now()->getTimestamp());

        if (! $force) {
            $build = $this->blizzardApiClient->currentBuild();

            foreach ($stages as $stage) {
                if ($this->importBuildGate->isUpToDate($stage->value, $build)) {
                    $importRun = $importRun->withStep($importRun->step($stage)->skipped());
                }
            }
        }

        $this->importProgressStore->save($importRun);

        return $importRun;
    }

    /**
     * Exécute une passe de la première étape qui n'a pas abouti, et republie l'import.
     */
    public function advance(ImportRun $importRun, bool $full, ?int $limit): ImportRun
    {
        $importStep = $this->nextStep($importRun);

        if (! $importStep instanceof ImportStep) {
            return $importRun;
        }

        $ceilingWait = $this->ceilingWait($importStep->stage);

        if ($ceilingWait instanceof ImportWait) {
            return $this->publish($importRun->waitingOn($ceilingWait));
        }

        $this->importProgressStore->save($importRun->withStep($importStep->started()));
        $this->importWaitReporter->follow($importRun->jobId);

        try {
            $importStageResult = $this->importStageRunner->run($importStep, $full, $limit);
        } finally {
            $this->importWaitReporter->release();
        }

        if ($importStageResult->step->status === ImportStepStatus::Completed) {
            $this->remember($importStageResult->step->stage);
        }

        return $this->publish(
            $importRun->withStep($importStageResult->step)->waitingOn($importStageResult->wait),
        );
    }

    /**
     * Une étape n'est pas lancée si le plafond réservé aux imports est déjà consommé :
     * sans ce garde-fou, seule la garde-robe le consultait et les autres entraient dans
     * le mur des 429 au lieu d'attendre que la fenêtre se libère.
     */
    private function ceilingWait(ImportStage $importStage): ?ImportWait
    {
        if (! $importStage->usesBlizzardApi()) {
            return null;
        }

        /** @var int $ceiling */
        $ceiling = config('services.blizzard.import_hourly_ceiling', 30000);

        $seconds = $this->hourlyBudgetGuard->secondsUntilAvailable(1, $ceiling);

        return $seconds > 0 ? ImportWait::hourlyBudget($seconds) : null;
    }

    private function publish(ImportRun $importRun): ImportRun
    {
        $importRun = $importRun->withBudgetUsed($this->hourlyBudgetGuard->usedInWindow());

        $this->importProgressStore->save($importRun);

        return $importRun;
    }

    public function isDone(ImportRun $importRun): bool
    {
        return ! $this->nextStep($importRun) instanceof ImportStep;
    }

    private function nextStep(ImportRun $importRun): ?ImportStep
    {
        foreach ($importRun->steps as $importStep) {
            if (! $importStep->isTerminal()) {
                return $importStep;
            }
        }

        return null;
    }

    /**
     * Un build indéterminé n'est jamais bloquant : on n'inscrit rien plutôt que de
     * retenir une étape sous un build qu'on ne sait pas nommer.
     */
    private function remember(ImportStage $importStage): void
    {
        $build = $this->blizzardApiClient->currentBuild();

        if ($build !== null) {
            $this->importBuildGate->remember($importStage->value, $build);
        }
    }
}
