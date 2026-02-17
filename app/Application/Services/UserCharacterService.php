<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Infrastructure\Blizzard\BlizzardApiClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Session;

class UserCharacterService
{
    private string $region;

    public function __construct(
        private BlizzardApiClient $apiClient,
    ) {
        $this->region = (string) config('services.blizzard.region', 'eu');
    }

    public function isAuthenticated(): bool
    {
        return Session::has('blizzard_user_token');
    }

    public function logout(): void
    {
        Session::forget('blizzard_user_token');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getUserCharacters(): array
    {
        $token = Session::get('blizzard_user_token');

        if (!$token) {
            return [];
        }

        $response = $this->apiClient->getWithUserToken('profile/user/wow', $token);
        $characters = $this->parseCharacters($response);

        return $this->fetchAvatars($characters, $token);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function parseCharacters(array $response): array
    {
        $characters = [];

        foreach ($response['wow_accounts'] ?? [] as $account) {
            foreach ($account['characters'] ?? [] as $char) {
                $characters[] = [
                    'name' => $char['name'] ?? '',
                    'realm' => $char['realm']['name'] ?? '',
                    'realmSlug' => $char['realm']['slug'] ?? '',
                    'level' => $char['level'] ?? 0,
                    'classId' => $char['playable_class']['id'] ?? 0,
                    'className' => $char['playable_class']['name'] ?? '',
                    'raceId' => $char['playable_race']['id'] ?? 0,
                    'raceName' => $char['playable_race']['name'] ?? '',
                    'faction' => $char['faction']['name'] ?? '',
                    'avatarUrl' => '',
                ];
            }
        }

        usort($characters, fn($a, $b) => strcasecmp($a['name'], $b['name']));

        return $characters;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchAvatars(array $characters, string $token): array
    {
        $baseUrl = "https://{$this->region}.api.blizzard.com";
        $namespace = "profile-{$this->region}";

        $responses = Http::pool(function ($pool) use ($characters, $baseUrl, $namespace, $token) {
            foreach ($characters as $i => $char) {
                $realm = strtolower($char['realmSlug']);
                $name = strtolower($char['name']);

                $pool->as((string) $i)
                    ->withToken($token)
                    ->withHeaders(['Battlenet-Namespace' => $namespace])
                    ->get("{$baseUrl}/profile/wow/character/{$realm}/{$name}/character-media", [
                        'locale' => 'fr_FR',
                    ]);
            }
        });

        foreach ($characters as $i => &$char) {
            $response = $responses[(string) $i] ?? null;
            if ($response && $response->ok()) {
                $data = $response->json();
                foreach ($data['assets'] ?? [] as $asset) {
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
