<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Infrastructure\Blizzard\Importers\AppearanceImporter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

/**
 * Import d'apparences reprenable et auto-relâchant : traite une passe bornée en temps
 * (importChunk), puis se re-dispatch pour la suite au lieu de bloquer le worker pendant
 * les pauses de budget horaire. La reprise se fait via l'offset porté d'une passe à l'autre.
 */
class ImportAppearancesJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 1800;

    public function __construct(
        public readonly string $jobId,
        public readonly bool $full,
        public readonly int $offset = 0,
    ) {
        $this->queue = 'imports';
    }

    /** Garde-fou : le chaînage de re-dispatch peut s'étaler sur plusieurs heures. */
    public function retryUntil(): \DateTimeInterface
    {
        return now()->addHours(24);
    }

    public function handle(AppearanceImporter $appearanceImporter): void
    {
        /** @var int $timeBox */
        $timeBox = config('services.blizzard.import_chunk_timebox', 600);

        $appearanceImportProgress = $appearanceImporter->importChunk($this->full, $this->offset, $timeBox);

        if ($appearanceImportProgress->done) {
            Cache::put('admin_import:'.$this->jobId, [
                'status' => 'completed',
                'output' => sprintf('Apparences importées : %d.', $appearanceImportProgress->total),
            ], 3600);

            return;
        }

        Cache::put('admin_import:'.$this->jobId, [
            'status' => 'running',
            'output' => sprintf('Apparences %d/%d...', $appearanceImportProgress->offset, $appearanceImportProgress->total),
        ], 3600);

        dispatch(new self($this->jobId, $this->full, $appearanceImportProgress->offset))
            ->delay(now()->addSeconds($appearanceImportProgress->secondsUntilBudget));
    }
}
