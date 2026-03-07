<?php

declare(strict_types=1);

namespace App\Application\Services;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class AdminService
{
    private const array ALLOWED_COMMANDS = [
        'app:download-db2',
        'app:wow-data-import',
        'app:wow-data-refresh',
        'app:wow-quest-faction-tag',
    ];

    /**
     * @param  array<string, mixed>  $parameters
     */
    public function runImportCommand(string $command, array $parameters = []): string
    {
        throw_unless(in_array($command, self::ALLOWED_COMMANDS, true), \InvalidArgumentException::class, 'Unknown command: '.$command);

        Artisan::call($command, $parameters);

        return Artisan::output();
    }

    public function clearCaches(): string
    {
        $output = '';
        foreach (['config:clear', 'route:clear', 'view:clear', 'cache:clear'] as $cmd) {
            Artisan::call($cmd);
            $output .= Artisan::output();
        }

        return $output;
    }

    public function toggleMaintenance(bool $enable, ?string $secret = null): string
    {
        if ($enable) {
            $params = [];
            if ($secret !== null && $secret !== '') {
                $params['--secret'] = $secret;
            }

            Artisan::call('down', $params);
        } else {
            Artisan::call('up');
        }

        return Artisan::output();
    }

    public function isInMaintenance(): bool
    {
        return app()->isDownForMaintenance();
    }

    /**
     * @param  array{title: string, description: string, color?: int, fields?: list<array{name: string, value: string, inline?: bool}>}  $embed
     */
    public function sendDiscordEmbed(string $channel, array $embed): bool
    {
        /** @var string $changelogUrl */
        $changelogUrl = config('services.discord.webhook_changelog', '');
        /** @var string $discussionUrl */
        $discussionUrl = config('services.discord.webhook_discussion', '');

        $webhookUrl = match ($channel) {
            'changelog' => $changelogUrl,
            'discussion' => $discussionUrl,
            default => throw new \InvalidArgumentException('Unknown channel: '.$channel),
        };

        throw_if($webhookUrl === '', \RuntimeException::class, 'Discord webhook URL not configured for channel: '.$channel);

        $response = Http::timeout(10)->post($webhookUrl, [
            'embeds' => [$embed],
        ]);

        return $response->successful();
    }

    /**
     * @return array{status: string, output: string|null}
     */
    public function getImportJobStatus(string $jobId): array
    {
        /** @var array{status: string, output: string|null} $result */
        $result = Cache::get('admin_import:'.$jobId, ['status' => 'not_found', 'output' => null]);

        return $result;
    }
}
