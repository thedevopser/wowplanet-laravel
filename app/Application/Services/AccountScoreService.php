<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\DTOs\AccountScoreProgress;
use App\Domain\Services\ScoreCalculator;
use App\Domain\ValueObjects\ScoreInput;
use App\Domain\ValueObjects\ScoreWeights;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;

class AccountScoreService
{
    private const BATCH_SIZE = 1;

    private const CACHE_TTL = 86400;

    private const PROGRESS_TTL = 3600;

    public function __construct(
        private readonly CharacterProfileService $characterProfileService,
        private readonly UserCharacterService $userCharacterService,
        private readonly ScoreCalculator $scoreCalculator,
    ) {}

    /**
     * @return array{status: string, data?: array<string, mixed>|null, progress?: array{loaded: int, errors: int, total: int, current: string}}
     */
    public function getOrCompute(): array
    {
        if (! $this->userCharacterService->isAuthenticated()) {
            return ['status' => 'unauthenticated'];
        }

        $cacheKey = $this->getCacheKey();

        /** @var array<string, mixed>|null $cached */
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return ['status' => 'ready', 'data' => $cached];
        }

        $progressKey = $cacheKey.':progress';
        /** @var AccountScoreProgress|null $progress */
        $progress = Cache::get($progressKey);

        if ($progress === null) {
            $characters = $this->userCharacterService->getUserCharacters();
            if ($characters === []) {
                return ['status' => 'ready', 'data' => null];
            }

            /** @var list<array{realmSlug: string, name: string}> $charList */
            $charList = array_map(fn (array $c): array => [
                'realmSlug' => is_string($c['realmSlug'] ?? null) ? $c['realmSlug'] : '',
                'name' => is_string($c['name'] ?? null) ? $c['name'] : '',
            ], $characters);

            $progress = new AccountScoreProgress($charList);
        }

        $total = count($progress->characters);
        $current = $progress->processed + count($progress->errors);

        if ($current >= $total) {
            return $this->finalize($progress, $cacheKey, $progressKey);
        }

        /** @var list<array{realmSlug: string, name: string}> $batch */
        $batch = array_slice($progress->characters, $current, self::BATCH_SIZE);

        foreach ($batch as $char) {
            try {
                $profile = $this->characterProfileService->getProfile(
                    $char['realmSlug'],
                    mb_strtolower($char['name']),
                );
                $progress->mergeProfile($profile);
            } catch (\Exception $exception) {
                Log::warning('Account score: failed to load character', [
                    'character' => $char['name'],
                    'realm' => $char['realmSlug'],
                    'error' => $exception->getMessage(),
                ]);
                $progress->errors[] = $char['name'];
            }
        }

        $current = $progress->processed + count($progress->errors);

        if ($current >= $total) {
            return $this->finalize($progress, $cacheKey, $progressKey);
        }

        Cache::put($progressKey, $progress, self::PROGRESS_TTL);

        $nextChar = $progress->characters[$current] ?? null;

        return [
            'status' => 'computing',
            'progress' => [
                'loaded' => $progress->processed,
                'errors' => count($progress->errors),
                'total' => $total,
                'current' => $nextChar['name'] ?? '',
            ],
        ];
    }

    public function invalidate(): void
    {
        $cacheKey = $this->getCacheKey();
        Cache::forget($cacheKey);
        Cache::forget($cacheKey.':progress');
    }

    /**
     * @return array{status: string, data: array<string, mixed>}
     */
    private function finalize(AccountScoreProgress $accountScoreProgress, string $cacheKey, string $progressKey): array
    {
        $result = $accountScoreProgress->buildResult();
        $result['score'] = $this->scoreCalculator->compute($this->toScoreInput($result));

        Cache::put($cacheKey, $result, self::CACHE_TTL);
        Cache::forget($progressKey);

        return ['status' => 'ready', 'data' => $result];
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function toScoreInput(array $result): ScoreInput
    {
        /** @var array<int|string, array<string, mixed>> $collections */
        $collections = is_array($result['collections'] ?? null) ? $result['collections'] : [];
        /** @var list<array<string, mixed>> $mounts */
        $mounts = is_array($result['mounts'] ?? null) ? $result['mounts'] : [];
        /** @var list<array<string, mixed>> $pets */
        $pets = is_array($result['pets'] ?? null) ? $result['pets'] : [];
        /** @var list<array<string, mixed>> $decor */
        $decor = is_array($result['decor'] ?? null) ? $result['decor'] : [];
        /** @var list<array<string, mixed>> $professions */
        $professions = is_array($result['professions'] ?? null) ? $result['professions'] : [];
        /** @var list<array{slot?: string, total?: int, completed?: int}> $appearances */
        $appearances = is_array($result['appearances'] ?? null) ? $result['appearances'] : [];
        /** @var list<array<string, mixed>>|null $raids */
        $raids = is_array($result['raids'] ?? null) ? $result['raids'] : null;
        /** @var array{completed: int, total: int}|null $bestProfessionStats */
        $bestProfessionStats = is_array($result['bestProfessionStats'] ?? null) ? $result['bestProfessionStats'] : null;

        return new ScoreInput(
            collections: $collections,
            mounts: $mounts,
            pets: $pets,
            decor: $decor,
            professions: $professions,
            appearances: $appearances,
            raids: $raids,
            bestProfessionStats: $bestProfessionStats,
        );
    }

    /** La version de la formule est dans la clé : un changement de barème purge le cache. */
    private function getCacheKey(): string
    {
        return 'account_score:v'.ScoreWeights::VERSION.':'.Session::getId();
    }
}
