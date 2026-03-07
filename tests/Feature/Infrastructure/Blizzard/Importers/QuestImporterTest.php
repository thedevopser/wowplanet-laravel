<?php

declare(strict_types=1);

use App\Infrastructure\Blizzard\BlizzardApiClient;
use App\Infrastructure\Blizzard\Importers\QuestImporter;
use App\Models\WowQuest;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Sleep;

beforeEach(function (): void {
    Sleep::fake();
});

test('it imports quests from API', function (): void {
    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);

    $client->shouldReceive('get')
        ->with('data/wow/quest/area/index', \Mockery::any())
        ->andReturn(['areas' => [
            ['id' => 10, 'name' => 'Durotar'],
            ['id' => 20, 'name' => 'Nagrand'],
        ]]);

    $client->shouldReceive('getAsync')
        ->with('data/wow/quest/area/10', \Mockery::any())
        ->andReturn(Create::promiseFor(new Response(200, [], json_encode([
            'area' => ['name' => 'Durotar'],
            'quests' => [['id' => 100, 'name' => 'Quete de Durotar']],
        ]))));

    $client->shouldReceive('getAsync')
        ->with('data/wow/quest/area/20', \Mockery::any())
        ->andReturn(Create::promiseFor(new Response(200, [], json_encode([
            'area' => ['name' => 'Nagrand'],
            'quests' => [
                ['id' => 101, 'name' => 'Quete de Nagrand'],
                ['id' => 102, 'name' => 'Quete secondaire'],
            ],
        ]))));

    $areaExpansionMap = [10 => 0, 20 => 1];

    $questImporter = resolve(QuestImporter::class);
    $questImporter->import($areaExpansionMap);

    expect(WowQuest::query()->count())->toBe(3);
    expect(WowQuest::query()->find(100)->name_fr)->toBe('Quete de Durotar');
    expect(WowQuest::query()->find(100)->expansion_id)->toBe(0);
    expect(WowQuest::query()->find(100)->zone_name)->toBe('Durotar');
    expect(WowQuest::query()->find(101)->expansion_id)->toBe(1);
    expect(WowQuest::query()->find(101)->zone_name)->toBe('Nagrand');
    expect(WowQuest::query()->find(102)->zone_name)->toBe('Nagrand');
});

test('it returns early when API fails', function (): void {
    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);

    $client->shouldReceive('get')
        ->with('data/wow/quest/area/index', \Mockery::any())
        ->andThrow(new \Exception('API error: 500 Internal Server Error'));

    $questImporter = resolve(QuestImporter::class);
    $questImporter->import([]);

    expect(WowQuest::query()->count())->toBe(0);
});

test('it tags mirror quest factions', function (): void {
    WowQuest::factory()->create([
        'id' => 100,
        'name_fr' => 'Mission de guerre',
        'zone_name' => 'Vallee de Tiragarde',
        'expansion_id' => 7,
        'faction' => null,
        'is_active' => true,
    ]);
    WowQuest::factory()->create([
        'id' => 101,
        'name_fr' => 'Mission de guerre',
        'zone_name' => 'Vallee de Tiragarde',
        'expansion_id' => 7,
        'faction' => null,
        'is_active' => true,
    ]);

    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);

    // Quest 100 returns a Horde reputation
    $client->shouldReceive('getAsync')
        ->with('data/wow/quest/100', \Mockery::any())
        ->andReturn(Create::promiseFor(new Response(200, [], json_encode([
            'rewards' => [
                'reputations' => [
                    ['reward' => ['id' => 2103]],
                ],
            ],
        ]))));

    // Quest 101 returns no faction reputation
    $client->shouldReceive('getAsync')
        ->with('data/wow/quest/101', \Mockery::any())
        ->andReturn(Create::promiseFor(new Response(200, [], json_encode([
            'rewards' => [],
        ]))));

    $reputationFactionMap = [2103 => 'Horde'];

    $questImporter = resolve(QuestImporter::class);
    $questImporter->tagMirrorFactions($reputationFactionMap);

    expect(WowQuest::query()->find(100)->faction)->toBe('Horde');
    expect(WowQuest::query()->find(101)->faction)->toBe('Alliance');
});
