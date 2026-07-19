<?php

declare(strict_types=1);

use App\Infrastructure\Blizzard\BlizzardApiClient;
use App\Infrastructure\Blizzard\Concerns\ImportsFromBlizzardApi;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Sleep;

beforeEach(function (): void {
    Sleep::fake();
});

/**
 * Consommateur minimal du trait pour exercer ses chemins d'erreur.
 */
function makeApiConsumer(BlizzardApiClient $client): object
{
    return new class($client)
    {
        use ImportsFromBlizzardApi;

        public function __construct(private readonly BlizzardApiClient $blizzardApiClient) {}

        /**
         * @return array<string, mixed>|null
         */
        public function fetch(string $endpoint): ?array
        {
            return $this->fetchWithRetry($endpoint);
        }

        /**
         * @param  array<string|int, string>  $endpoints
         * @return array<string|int, array<string, mixed>|null>
         */
        public function fetchBatch(array $endpoints): array
        {
            return $this->fetchBatchAsync($endpoints);
        }
    };
}

// ─── fetchWithRetry ─────────────────────────────────────────

test('fetchWithRetry returns null on 404', function (): void {
    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);
    $client->shouldReceive('get')->andThrow(new \Exception('Client error: 404 Not Found'));

    expect(makeApiConsumer($client)->fetch('data/wow/quest/999'))->toBeNull();
});

test('fetchWithRetry retries retryable errors then succeeds', function (): void {
    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);
    $client->shouldReceive('get')
        ->twice()
        ->andReturnUsing(function (): array {
            static $calls = 0;
            $calls++;
            throw_if($calls === 1, \Exception::class, 'Server error: 429 Too Many Requests');

            return ['ok' => true];
        });

    expect(makeApiConsumer($client)->fetch('data/wow/mount/index'))->toBe(['ok' => true]);
});

test('fetchWithRetry gives up on non-retryable errors', function (): void {
    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);
    $client->shouldReceive('get')->once()->andThrow(new \Exception('Unexpected parsing failure'));

    expect(makeApiConsumer($client)->fetch('data/wow/mount/index'))->toBeNull();
});

// ─── fetchBatchAsync ────────────────────────────────────────

test('fetchBatchAsync decodes fulfilled responses', function (): void {
    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);
    $client->shouldReceive('getAsync')
        ->andReturnUsing(fn (): \GuzzleHttp\Promise\PromiseInterface => Create::promiseFor(new Response(200, [], (string) json_encode(['id' => 1]))));

    $results = makeApiConsumer($client)->fetchBatch([1 => 'data/wow/x/1']);

    expect($results[1])->toBe(['id' => 1]);
});

test('fetchBatchAsync retries 429 responses then succeeds', function (): void {
    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);
    $client->shouldReceive('getAsync')
        ->twice()
        ->andReturnUsing(function (): \GuzzleHttp\Promise\PromiseInterface {
            static $calls = 0;
            $calls++;
            if ($calls === 1) {
                return Create::promiseFor(new Response(429, [], 'slow down'));
            }

            return Create::promiseFor(new Response(200, [], (string) json_encode(['id' => 7])));
        });

    $results = makeApiConsumer($client)->fetchBatch([7 => 'data/wow/x/7']);

    expect($results[7])->toBe(['id' => 7]);
});

test('fetchBatchAsync abandons server errors after limited retries', function (): void {
    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);
    $client->shouldReceive('getAsync')
        ->andReturnUsing(fn (): \GuzzleHttp\Promise\PromiseInterface => Create::promiseFor(new Response(500, [], 'boom')));

    $results = makeApiConsumer($client)->fetchBatch([3 => 'data/wow/x/3']);

    expect($results[3])->toBeNull();
});

test('fetchBatchAsync marks rejected 404 promises as not found', function (): void {
    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);
    $request = new Request('GET', 'data/wow/x/4');
    $client->shouldReceive('getAsync')
        ->andReturnUsing(fn (): \GuzzleHttp\Promise\PromiseInterface => Create::rejectionFor(
            new RequestException('Not Found', $request, new Response(404)),
        ));

    $results = makeApiConsumer($client)->fetchBatch([4 => 'data/wow/x/4']);

    expect($results[4])->toBeNull();
});

test('fetchBatchAsync retries rejected 429 promises then succeeds', function (): void {
    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);
    $request = new Request('GET', 'data/wow/x/5');
    $client->shouldReceive('getAsync')
        ->twice()
        ->andReturnUsing(function () use ($request): \GuzzleHttp\Promise\PromiseInterface {
            static $calls = 0;
            $calls++;
            if ($calls === 1) {
                return Create::rejectionFor(new RequestException('Too Many Requests', $request, new Response(429)));
            }

            return Create::promiseFor(new Response(200, [], (string) json_encode(['id' => 5])));
        });

    $results = makeApiConsumer($client)->fetchBatch([5 => 'data/wow/x/5']);

    expect($results[5])->toBe(['id' => 5]);
});

test('fetchBatchAsync abandons invalid JSON after limited retries', function (): void {
    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);
    $client->shouldReceive('getAsync')
        ->andReturnUsing(fn (): \GuzzleHttp\Promise\PromiseInterface => Create::promiseFor(new Response(200, [], 'not-json{')));

    $results = makeApiConsumer($client)->fetchBatch([6 => 'data/wow/x/6']);

    expect($results[6])->toBeNull();
});

test('fetchBatchAsync abandons rejected connection errors after limited retries', function (): void {
    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);
    $client->shouldReceive('getAsync')
        ->andReturnUsing(fn (): \GuzzleHttp\Promise\PromiseInterface => Create::rejectionFor(new \RuntimeException('cURL error 28: timed out')));

    $results = makeApiConsumer($client)->fetchBatch([8 => 'data/wow/x/8']);

    expect($results[8])->toBeNull();
});
