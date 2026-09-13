<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Application\Import\ImportPipeline;
use App\Application\Import\ImportProgressStore;
use App\Application\Import\ImportRun;
use App\Application\Import\ImportStage;
use App\Application\Import\ImportWaitReason;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Moteur d'un import complet : une passe par invocation, puis re-dispatch.
 *
 * Rendre la main entre deux étapes est ce qui permet à un import de plusieurs minutes
 * de ne pas confisquer le worker, et à une pause de plafond horaire de se traduire par
 * un délai de file plutôt que par un `sleep` dans un job. C'est aussi la reprise : un
 * worker redémarré reprend l'import à l'étape en cours, les étapes abouties étant
 * retenues par la porte de build.
 *
 * Les commandes qui ne sont pas l'import complet gardent leur chemin d'origine, un
 * appel Artisan dont la sortie est publiée telle quelle.
 */
class RunImportJob implements ShouldQueue
{
    use Queueable;

    /** L'étape la plus longue est bornée par le time-box d'une passe. retry_after (config/queue.php) doit rester supérieur. */
    public int $timeout = 1800;

    private const ORCHESTRATED_COMMAND = 'app:wow-data-import';

    /**
     * @param  array<string, mixed>  $parameters
     */
    public function __construct(
        public readonly string $jobId,
        public readonly string $command,
        public readonly array $parameters = [],
    ) {
        $this->queue = 'imports';
    }

    /** Garde-fou : le chaînage de re-dispatch peut s'étaler sur plusieurs heures. */
    public function retryUntil(): \DateTimeInterface
    {
        return now()->addHours(24);
    }

    public function handle(ImportPipeline $importPipeline, ImportProgressStore $importProgressStore): void
    {
        if ($this->command !== self::ORCHESTRATED_COMMAND) {
            $this->runPlainCommand();

            return;
        }

        $importRun = $importProgressStore->find($this->jobId)
            ?? $importPipeline->begin($this->jobId, $this->stages(), $this->flag('--force'));

        $importRun = $importPipeline->advance($importRun, $this->flag('--full'), $this->limit());

        if ($importPipeline->isDone($importRun)) {
            return;
        }

        dispatch(new self($this->jobId, $this->command, $this->parameters))
            ->delay(now()->addSeconds($this->delayFor($importRun)));
    }

    /**
     * @return list<ImportStage>
     */
    private function stages(): array
    {
        /** @var string $type */
        $type = $this->parameters['--type'] ?? 'all';

        return ImportStage::requested($type);
    }

    private function flag(string $option): bool
    {
        return (bool) ($this->parameters[$option] ?? false);
    }

    private function limit(): ?int
    {
        $limit = $this->parameters['--limit'] ?? null;

        return is_numeric($limit) ? (int) $limit : null;
    }

    /**
     * Seule une pause de plafond horaire retarde la reprise : un lot en vol ou un recul
     * après 429 sont déjà passés quand la passe rend la main.
     */
    private function delayFor(ImportRun $importRun): int
    {
        return $importRun->wait?->reason === ImportWaitReason::HourlyBudget
            ? $importRun->wait->seconds
            : 0;
    }

    private function runPlainCommand(): void
    {
        Cache::put('admin_import:'.$this->jobId, ['status' => 'running', 'output' => null], 3600);

        try {
            Artisan::call($this->command, $this->parameters);

            Cache::put('admin_import:'.$this->jobId, ['status' => 'completed', 'output' => Artisan::output()], 3600);
        } catch (\Throwable $throwable) {
            Log::error('Import job failed', [
                'jobId' => $this->jobId,
                'command' => $this->command,
                'error' => $throwable->getMessage(),
            ]);

            Cache::put('admin_import:'.$this->jobId, [
                'status' => 'failed',
                'output' => $throwable->getMessage(),
            ], 3600);
        }
    }
}
