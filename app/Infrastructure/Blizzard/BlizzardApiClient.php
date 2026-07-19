<?php

declare(strict_types=1);

namespace App\Infrastructure\Blizzard;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Promise\PromiseInterface;
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

        $this->namespace = 'profile-'.$this->region;
    }

    /**
     * Guzzle remplace toute query string de l'URI par l'option 'query' : les
     * paramètres embarqués dans l'endpoint (ex. recherches bulk `?id=[a,b]`)
     * doivent donc être extraits et fusionnés explicitement.
     *
     * @param  array<string, mixed>  $query
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function mergeEndpointQuery(string $endpoint, array $query): array
    {
        $parts = explode('?', $endpoint, 2);
        if (! isset($parts[1])) {
            return [$endpoint, $query];
        }

        parse_str($parts[1], $parsed);

        $embedded = [];
        foreach ($parsed as $key => $value) {
            $embedded[(string) $key] = $value;
        }

        return [$parts[0], array_merge($embedded, $query)];
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
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     *
     * @throws GuzzleException
     */
    public function get(string $endpoint, array $query = []): array
    {
        $accessToken = $this->getAccessToken();

        [$endpoint, $query] = $this->mergeEndpointQuery($endpoint, $query);
        $namespace = is_string($query['namespace'] ?? null) ? $query['namespace'] : $this->namespace;

        $response = $this->client->get($endpoint, [
            'headers' => [
                'Authorization' => 'Bearer '.$accessToken,
                'Battlenet-Namespace' => $namespace,
            ],
            'query' => array_merge(['locale' => 'fr_FR'], $query),
        ]);

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);

        return $decoded;
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     *
     * @throws GuzzleException
     */
    public function getWithUserToken(string $endpoint, string $userToken, array $query = []): array
    {
        $response = $this->client->get($endpoint, [
            'headers' => [
                'Authorization' => 'Bearer '.$userToken,
                'Battlenet-Namespace' => $this->namespace,
            ],
            'query' => array_merge(['locale' => 'fr_FR'], $query),
        ]);

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);

        return $decoded;
    }

    /**
     * @param  array<string, mixed>  $query
     */
    public function getAsync(string $endpoint, array $query = []): PromiseInterface
    {
        $accessToken = $this->getAccessToken();

        [$endpoint, $query] = $this->mergeEndpointQuery($endpoint, $query);
        $namespace = is_string($query['namespace'] ?? null) ? $query['namespace'] : $this->namespace;

        return $this->client->getAsync($endpoint, [
            'headers' => [
                'Authorization' => 'Bearer '.$accessToken,
                'Battlenet-Namespace' => $namespace,
            ],
            'query' => array_merge(['locale' => 'fr_FR'], $query),
        ]);
    }

    public function getClient(): Client
    {
        return $this->client;
    }

    public function getRegion(): string
    {
        return $this->region;
    }

    public function getCurrentMythicSeasonId(): int
    {
        /** @var int $seasonId */
        $seasonId = Cache::remember('blizzard_current_m_plus_season', 86400, function (): int {
            /** @var array<string, mixed> $data */
            $data = $this->get('data/wow/mythic-keystone/season/index', [
                'namespace' => 'dynamic-'.$this->region,
            ]);

            /** @var array{id?: int} $currentSeason */
            $currentSeason = $data['current_season'] ?? [];

            return (int) ($currentSeason['id'] ?? 0);
        });

        return (int) $seasonId;
    }

    /**
     * @return array{headers: array{Authorization: string, Battlenet-Namespace: string}, query: array{locale: string, namespace: string}}
     */
    public function getBaseOptions(): array
    {
        return [
            'headers' => [
                'Authorization' => 'Bearer '.$this->getAccessToken(),
                'Battlenet-Namespace' => 'static-'.$this->region,
            ],
            'query' => [
                'locale' => 'fr_FR',
                'namespace' => 'static-'.$this->region,
            ],
        ];
    }
}
