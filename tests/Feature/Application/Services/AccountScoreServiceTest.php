<?php

declare(strict_types=1);

use App\Application\Services\AccountScoreService;
use App\Application\Services\CharacterProfileService;
use App\Application\Services\UserCharacterService;
use App\Domain\ValueObjects\ScoreWeights;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Session;

test('it returns unauthenticated when no session', function (): void {
    $mockUserCharService = $this->mock(UserCharacterService::class);
    /** @var \Mockery\Expectation $exp */
    $exp = $mockUserCharService->shouldReceive('isAuthenticated');
    $exp->once()->andReturn(false);

    $this->mock(CharacterProfileService::class);

    $accountScoreService = resolve(AccountScoreService::class);
    $result = $accountScoreService->getOrCompute();

    expect($result)->toHaveKey('status', 'unauthenticated');
});

test('it returns cached result when available', function (): void {
    $mockUserCharService = $this->mock(UserCharacterService::class);
    /** @var \Mockery\Expectation $exp */
    $exp = $mockUserCharService->shouldReceive('isAuthenticated');
    $exp->once()->andReturn(true);

    $this->mock(CharacterProfileService::class);

    Session::shouldReceive('getId')->andReturn('test-session-id');

    $cachedData = [
        'collections' => [],
        'mounts' => [],
        'pets' => [],
        'decor' => [],
        'professions' => [],
        'mountsCount' => 5,
        'petsCount' => 3,
        'decorCount' => 2,
        'characterCount' => 1,
        'errors' => [],
        'cachedAt' => now()->toISOString(),
    ];

    Cache::put('account_score:v'.ScoreWeights::VERSION.':test-session-id', $cachedData, 86400);

    $accountScoreService = resolve(AccountScoreService::class);
    $result = $accountScoreService->getOrCompute();

    expect($result)->toHaveKey('status', 'ready')
        ->and($result)->toHaveKey('data')
        ->and($result['data']['mountsCount'])->toBe(5);
});

test('it starts computing when no cache', function (): void {
    $mockUserCharService = $this->mock(UserCharacterService::class);
    /** @var \Mockery\Expectation $authExp */
    $authExp = $mockUserCharService->shouldReceive('isAuthenticated');
    $authExp->once()->andReturn(true);

    /** @var \Mockery\Expectation $charsExp */
    $charsExp = $mockUserCharService->shouldReceive('getUserCharacters');
    $charsExp->once()->andReturn([
        ['realmSlug' => 'hyjal', 'name' => 'Thrall'],
        ['realmSlug' => 'hyjal', 'name' => 'Jaina'],
    ]);

    $mockProfileService = $this->mock(CharacterProfileService::class);
    /** @var \Mockery\Expectation $profileExp */
    $profileExp = $mockProfileService->shouldReceive('getProfile');
    $profileExp->once()->andReturn(new App\Application\DTOs\CharacterProfileDTO(
        name: 'Thrall',
        realm: 'Hyjal',
        race: 'Orc',
        class: 'Chaman',
        classId: 7,
        level: 80,
        ilvl: 600,
        faction: 'Horde',
        avatarUrl: '',
        classIconUrl: '',
        collections: [],
        mountsCount: 0,
        petsCount: 0,
    ));

    Session::shouldReceive('getId')->andReturn('test-session-id');

    $accountScoreService = resolve(AccountScoreService::class);
    $result = $accountScoreService->getOrCompute();

    expect($result)->toHaveKey('status', 'computing')
        ->and($result)->toHaveKey('progress')
        ->and($result['progress']['total'])->toBe(2)
        ->and($result['progress']['loaded'])->toBe(1);
});

test('it invalidates cache', function (): void {
    $this->mock(UserCharacterService::class);
    $this->mock(CharacterProfileService::class);

    Session::shouldReceive('getId')->andReturn('test-session-id');

    Cache::put('account_score:v'.ScoreWeights::VERSION.':test-session-id', ['some' => 'data'], 86400);
    Cache::put('account_score:v'.ScoreWeights::VERSION.':test-session-id:progress', ['some' => 'progress'], 3600);

    $accountScoreService = resolve(AccountScoreService::class);
    $accountScoreService->invalidate();

    expect(Cache::get('account_score:v'.ScoreWeights::VERSION.':test-session-id'))->toBeNull()
        ->and(Cache::get('account_score:v'.ScoreWeights::VERSION.':test-session-id:progress'))->toBeNull();
});

test('it attaches the computed score to the finalized payload', function (): void {
    $mockUserCharService = $this->mock(UserCharacterService::class);
    /** @var \Mockery\Expectation $authExp */
    $authExp = $mockUserCharService->shouldReceive('isAuthenticated');
    $authExp->once()->andReturn(true);

    /** @var \Mockery\Expectation $charsExp */
    $charsExp = $mockUserCharService->shouldReceive('getUserCharacters');
    $charsExp->once()->andReturn([['realmSlug' => 'hyjal', 'name' => 'Thrall']]);

    $mockProfileService = $this->mock(CharacterProfileService::class);
    /** @var \Mockery\Expectation $profileExp */
    $profileExp = $mockProfileService->shouldReceive('getProfile');
    $profileExp->once()->andReturn(new App\Application\DTOs\CharacterProfileDTO(
        name: 'Thrall',
        realm: 'Hyjal',
        race: 'Orc',
        class: 'Chaman',
        classId: 7,
        level: 80,
        ilvl: 600,
        faction: 'Horde',
        avatarUrl: '',
        classIconUrl: '',
        collections: [],
        mountsCount: 0,
        petsCount: 0,
        mounts: [
            ['id' => 1, 'name' => 'A', 'is_completed' => true],
            ['id' => 2, 'name' => 'B', 'is_completed' => false],
        ],
        appearances: [['slot' => 'HEAD', 'category' => 'armor', 'total' => 100, 'completed' => 25]],
    ));

    Session::shouldReceive('getId')->andReturn('test-session-id');

    $result = resolve(AccountScoreService::class)->getOrCompute();

    /** @var \App\Domain\ValueObjects\CompletionScore $score */
    $score = $result['data']['score'];

    // Seules montures (50 %) et garde-robe (25 %) sont applicables.
    $weights = ScoreWeights::WEIGHTS;
    $expected = round(
        (50 * $weights['mounts'] + 25 * $weights['transmog']) / ($weights['mounts'] + $weights['transmog']),
        1,
    );

    expect($result['status'])->toBe('ready')
        ->and($score->version)->toBe(ScoreWeights::VERSION)
        ->and($score->global)->toBe($expected)
        ->and($score->dimensions)->toHaveCount(count($weights));
});
