<?php

declare(strict_types=1);

namespace App\Infrastructure\Blizzard;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class BlizzardApiClient
{
    private string $clientId;
    private string $clientSecret;
    private string $region;
    private string $namespace;

    public function __construct(private Client $client)
    {
        $this->clientId = (string)config('services.blizzard.client_id');
        $this->clientSecret = (string)config('services.blizzard.client_secret');
        $this->region = (string)config('services.blizzard.region', 'eu');
        $this->namespace = "profile-{$this->region}";
    }

    public function getAccessToken(): string
    {
        return Cache::remember('blizzard_access_token', 3600, function () {
            $response = Http::asForm()
                ->withBasicAuth($this->clientId, $this->clientSecret)
                ->post("https://{$this->region}.battle.net/oauth/token", [
                'grant_type' => 'client_credentials',
            ]);

            if ($response->failed()) {
                throw new \RuntimeException('Failed to fetch Blizzard access token');
            }

            return (string)$response->json('access_token');
        });
    }

    /**
     * @param string $endpoint
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     * @throws GuzzleException
     */
    public function get(string $endpoint, array $query = []): array
    {
        $accessToken = $this->getAccessToken();

        $namespace = $query['namespace'] ?? $this->namespace;

        $response = $this->client->get($endpoint, [
            'headers' => [
                'Authorization' => "Bearer {$accessToken}",
                'Battlenet-Namespace' => $namespace,
            ],
            'query' => array_merge(['locale' => 'fr_FR'], $query),
        ]);

        return json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     * @throws GuzzleException
     */
    public function getWithUserToken(string $endpoint, string $userToken, array $query = []): array
    {
        $response = $this->client->get($endpoint, [
            'headers' => [
                'Authorization' => "Bearer {$userToken}",
                'Battlenet-Namespace' => $this->namespace,
            ],
            'query' => array_merge(['locale' => 'fr_FR'], $query),
        ]);

        return json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);
    }

    public function getClient(): Client
    {
        return $this->client;
    }

    public function getBaseOptions(): array
    {
        return [
            'headers' => [
                'Authorization' => "Bearer {$this->getAccessToken()}",
                'Battlenet-Namespace' => "static-{$this->region}",
            ],
            'query' => [
                'locale' => 'fr_FR',
                'namespace' => "static-{$this->region}",
            ],
        ];
    }
}