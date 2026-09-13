<?php

declare(strict_types=1);

namespace App\Application\Import;

use Illuminate\Support\Facades\Cache;

/**
 * Publie et relit l'avancement d'un import sous la clé de suivi du job.
 *
 * La valeur stockée garde `status` et `output`, que le panneau d'administration lit
 * déjà, et range l'état structuré à côté : le front continue de fonctionner sans une
 * ligne de changement, et l'endpoint de progression a de quoi détailler.
 *
 * Ce magasin n'est pas l'autorité de reprise. Il vit dans le cache, que le bouton
 * « Vider les caches » efface : la reprise s'appuie sur la charge du job en file et sur
 * la porte de build, toutes deux durables.
 */
final readonly class ImportProgressStore
{
    private const KEY_PREFIX = 'admin_import:';

    private const TTL_S = 3600;

    public function save(ImportRun $importRun): void
    {
        Cache::put(self::KEY_PREFIX.$importRun->jobId, [
            'status' => $importRun->status()->value,
            'output' => $importRun->summary(now()->getTimestamp()),
            'run' => $importRun->toArray(),
        ], self::TTL_S);
    }

    public function find(string $jobId): ?ImportRun
    {
        /** @var array{run?: array{job_id: string, started_at: int, budget_used: int, wait: array{reason: string, seconds: int, count: int}|null, steps: list<array{stage: string, status: string, created: int, updated: int, deleted: int, api_calls: int, duration_ms: int, offset: int, total: int, error: string|null}>}}|null $stored */
        $stored = Cache::get(self::KEY_PREFIX.$jobId);

        if ($stored === null || ! isset($stored['run'])) {
            return null;
        }

        return ImportRun::fromArray($stored['run']);
    }

    /**
     * Réponse de l'endpoint de progression. Un job suivi par le chemin des commandes
     * simples — celles qui ne sont pas l'import complet — est rendu tel qu'il l'a
     * toujours été, pour que le panneau ne distingue pas les deux.
     *
     * @return array<string, mixed>
     */
    public function payload(string $jobId): array
    {
        $importRun = $this->find($jobId);

        if (! $importRun instanceof ImportRun) {
            /** @var array<string, mixed> $stored */
            $stored = Cache::get(self::KEY_PREFIX.$jobId, ['status' => 'not_found', 'output' => null]);

            return $stored;
        }

        $now = now()->getTimestamp();

        /** @var int $ceiling */
        $ceiling = config('services.blizzard.import_hourly_ceiling', 30000);

        return [
            'status' => $importRun->status()->value,
            'output' => $importRun->summary($now),
            'stage' => $importRun->currentStage()?->value,
            'stage_label' => $importRun->currentStage()?->label(),
            'percent' => (int) round($importRun->fraction() * 100),
            'started_at' => $importRun->startedAt,
            'elapsed_seconds' => $importRun->elapsedSeconds($now),
            'eta_seconds' => $importRun->etaSeconds(),
            'budget' => [
                'used' => $importRun->budgetUsed,
                'ceiling' => $ceiling,
            ],
            'waiting' => $importRun->wait instanceof ImportWait ? [
                'reason' => $importRun->wait->reason->value,
                'seconds' => $importRun->wait->seconds,
                'message' => $importRun->wait->describe(),
            ] : null,
            'steps' => array_map(static fn (ImportStep $importStep): array => [
                'stage' => $importStep->stage->value,
                'label' => $importStep->stage->label(),
                'status' => $importStep->status->value,
                'percent' => (int) round($importStep->fraction() * 100),
                'created' => $importStep->rows->created,
                'updated' => $importStep->rows->updated,
                'deleted' => $importStep->rows->deleted,
                'api_calls' => $importStep->apiCalls,
                'duration_ms' => $importStep->durationMs,
                'error' => $importStep->error,
            ], $importRun->steps),
        ];
    }
}
