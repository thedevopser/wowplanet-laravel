<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class RunImportJob implements ShouldQueue
{
    use Queueable;

    // La charge lourde (apparences) est externalisée dans ImportAppearancesJob, qui
    // s'auto-relâche pendant les pauses de budget. Ce job ne fait plus que les imports
    // légers inline. retry_after (config/queue.php) doit rester > à ce timeout.
    public int $timeout = 1800;

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

    public function handle(): void
    {
        Cache::put('admin_import:'.$this->jobId, ['status' => 'running', 'output' => null], 3600);

        try {
            Artisan::call($this->command, $this->parameters);
            $output = Artisan::output();

            Cache::put('admin_import:'.$this->jobId, ['status' => 'completed', 'output' => $output], 3600);
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
