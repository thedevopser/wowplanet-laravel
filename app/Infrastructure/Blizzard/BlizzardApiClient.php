<?php

declare(strict_types=1);

namespace App\Infrastructure\Blizzard;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class BlizzardApiClient
{
    private readonly string $clientId;

    private readonly string $clientSecret;

    private readonly string $region;

    private readonly string $namespace;

    public function __construct(private readonly Client $client)
    {
        /** @var string $clientId */
        $clientId = config('services.blizzard.client_id', '');
        $this->clientId = $clientId;

        /** @var string $clientSecret */
        $clientSecret = config('services.blizzard.client_secret', '');
        $this->clientSecret = $clientSecret;

        /** @var string $region */
        $region = config('services.blizzard.region', 'eu');
        $this->region = $region;

        $this->namespace = 'profile-' . $this->region;
    }

    public function getAccessToken(): string
    {
        /** @var string $token */
        $token = Cache::remember('blizzard_access_token', 3600, function (): string {
            $response = Http::asForm()
                ->withBasicAuth($this->clientId, $this->clientSecret)
                ->post(sprintf('https://%s.battle.net/oauth/token', $this->region), [
                'grant_type' => 'client_credentials',
            ]);

            throw_if($response->failed(), \RuntimeException::class, 'Failed to fetch Blizzard access token');

            /** @var string $accessToken */
            $accessToken = $response->json('access_token');

            return $accessToken;
        });

        return $token;
    }

    /**
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     * @throws GuzzleException
     */
    public function get(string $endpoint, array $query = []): array
    {
        $accessToken = $this->getAccessToken();

        $namespace = is_string($query['namespace'] ?? null) ? $query['namespace'] : $this->namespace;

        $response = $this->client->get($endpoint, [
            'headers' => [
                'Authorization' => 'Bearer ' . $accessToken,
                'Battlenet-Namespace' => $namespace,
            ],
            'query' => array_merge(['locale' => 'fr_FR'], $query),
        ]);

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);

        return $decoded;
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
                'Authorization' => 'Bearer ' . $userToken,
                'Battlenet-Namespace' => $this->namespace,
            ],
            'query' => array_merge(['locale' => 'fr_FR'], $query),
        ]);

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);

        return $decoded;
    }

    public function getClient(): Client
    {
        return $this->client;
    }

    /**
     * @return array{headers: array{Authorization: string, Battlenet-Namespace: string}, query: array{locale: string, namespace: string}}
     */
    public function getBaseOptions(): array
    {
        return [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->getAccessToken(),
                'Battlenet-Namespace' => 'static-' . $this->region,
            ],
            'query' => [
                'locale' => 'fr_FR',
                'namespace' => 'static-' . $this->region,
            ],
        ];
    }
}
