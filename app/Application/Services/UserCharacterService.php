<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Infrastructure\Blizzard\BlizzardApiClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Session;

class UserCharacterService
{
    private readonly string $region;

    public function __construct(
        private readonly BlizzardApiClient $blizzardApiClient,
    ) {
        /** @var string $region */
        $region = config('services.blizzard.region', 'eu');
        $this->region = $region;
    }

    public function isAuthenticated(): bool
    {
        return Session::has('blizzard_user_token');
    }

    public function isAdmin(): bool
    {
        return (bool) Session::get('is_admin', false);
    }

    public function logout(): void
    {
        Session::forget(['blizzard_user_token', 'bnet_user_id', 'bnet_battletag', 'is_admin']);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getUserCharacters(): array
    {
        /** @var string $token */
        $token = Session::get('blizzard_user_token', '');

        if ($token === '') {
            return [];
        }

        $response = $this->blizzardApiClient->getWithUserToken('profile/user/wow', $token);
        $characters = $this->parseCharacters($response);

        return $this->fetchAvatars($characters, $token);
    }

    /**
     * @return array<int, string>
     */
    public function getClassIcons(): array
    {
        /** @var array<int, string> $icons */
        $icons = Cache::remember('wow_class_icons', 86400 * 30, function (): array {
            $classIds = range(1, 13);
            $token = $this->blizzardApiClient->getAccessToken();
            $baseUrl = sprintf('https://%s.api.blizzard.com', $this->region);
            $namespace = 'static-'.$this->region;

            /** @var array<string, \Illuminate\Http\Client\Response> $responses */
            $responses = Http::pool(function (\Illuminate\Http\Client\Pool $pool) use ($classIds, $baseUrl, $namespace, $token): void {
                foreach ($classIds as $classId) {
                    $pool->as((string) $classId)
                        ->withToken($token)
                        ->withHeaders(['Battlenet-Namespace' => $namespace])
                        ->get(sprintf('%s/data/wow/media/playable-class/%s', $baseUrl, $classId), [
                            'locale' => 'fr_FR',
                            'namespace' => $namespace,
                        ]);
                }
            });

            $icons = [];
            foreach ($responses as $key => $response) {
                if (! $response->ok()) {
                    continue;
                }

                /** @var list<array{key: string, value: string}> $assets */
                $assets = $response->json('assets') ?? [];
                foreach ($assets as $asset) {
                    if ($asset['key'] === 'icon') {
                        $icons[(int) $key] = $asset['value'];
                        break;
                    }
                }
            }

            return $icons;
        });

        return $icons;
    }

    /**
     * @param  array<string, mixed>  $response
     * @return list<array{name: string, realm: string, realmSlug: string, level: int, classId: int, className: string, raceId: int, raceName: string, faction: string, avatarUrl: string}>
     */
    private function parseCharacters(array $response): array
    {
        $characters = [];

        /** @var list<array{characters?: list<array<string, mixed>>}> $accounts */
        $accounts = $response['wow_accounts'] ?? [];
        foreach ($accounts as $account) {
            /** @var list<array<string, mixed>> $chars */
            $chars = $account['characters'] ?? [];
            foreach ($chars as $char) {
                /** @var array{name?: string, slug?: string} $charRealm */
                $charRealm = $char['realm'] ?? [];
                /** @var array{id?: int, name?: string} $charClass */
                $charClass = $char['playable_class'] ?? [];
                /** @var array{id?: int, name?: string} $charRace */
                $charRace = $char['playable_race'] ?? [];
                /** @var array{name?: string} $charFaction */
                $charFaction = $char['faction'] ?? [];

                $characters[] = [
                    'name' => is_string($char['name'] ?? null) ? $char['name'] : '',
                    'realm' => (string) ($charRealm['name'] ?? ''),
                    'realmSlug' => (string) ($charRealm['slug'] ?? ''),
                    'level' => is_int($char['level'] ?? null) ? $char['level'] : 0,
                    'classId' => (int) ($charClass['id'] ?? 0),
                    'className' => (string) ($charClass['name'] ?? ''),
                    'raceId' => (int) ($charRace['id'] ?? 0),
                    'raceName' => (string) ($charRace['name'] ?? ''),
                    'faction' => (string) ($charFaction['name'] ?? ''),
                    'avatarUrl' => '',
                ];
            }
        }

        usort($characters, fn (array $a, array $b): int => strcasecmp($a['name'], $b['name']));

        return $characters;
    }

    /**
     * @param  list<array{name: string, realm: string, realmSlug: string, level: int, classId: int, className: string, raceId: int, raceName: string, faction: string, avatarUrl: string}>  $characters
     * @return list<array{name: string, realm: string, realmSlug: string, level: int, classId: int, className: string, raceId: int, raceName: string, faction: string, avatarUrl: string}>
     */
    private function fetchAvatars(array $characters, string $token): array
    {
        $baseUrl = sprintf('https://%s.api.blizzard.com', $this->region);
        $namespace = 'profile-'.$this->region;

        /** @var array<string, \Illuminate\Http\Client\Response> $responses */
        $responses = Http::pool(function (\Illuminate\Http\Client\Pool $pool) use ($characters, $baseUrl, $namespace, $token): void {
            foreach ($characters as $i => $char) {
                $realm = strtolower($char['realmSlug']);
                $name = strtolower($char['name']);

                $pool->as((string) $i)
                    ->withToken($token)
                    ->withHeaders(['Battlenet-Namespace' => $namespace])
                    ->get(sprintf('%s/profile/wow/character/%s/%s/character-media', $baseUrl, $realm, $name), [
                        'locale' => 'fr_FR',
                    ]);
            }
        });

        foreach ($characters as $i => &$char) {
            $key = (string) $i;
            if (! isset($responses[$key])) {
                continue;
            }

            $response = $responses[$key];
            if ($response->ok()) {
                /** @var array<string, mixed> $data */
                $data = $response->json();
                /** @var list<array{key: string, value: string}> $assets */
                $assets = $data['assets'] ?? [];
                foreach ($assets as $asset) {
                    if ($asset['key'] === 'avatar') {
                        $char['avatarUrl'] = $asset['value'];
                        break;
                    }
                }
            }
        }

        return $characters;
    }
}
