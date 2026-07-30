<?php

declare(strict_types=1);

use App\Infrastructure\Blizzard\BlizzardApiClient;
use App\Infrastructure\Blizzard\BlizzardBatchImporter;
use App\Models\WowAchievement;
use App\Models\WowDecor;
use App\Models\WowMount;
use App\Models\WowPet;
use App\Models\WowProfession;
use App\Models\WowQuest;
use App\Models\WowRecipe;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Sleep;

beforeEach(function (): void {
    setUpBlizzardTempStorage($this);
    Sleep::fake();
});

afterEach(function (): void {
    tearDownBlizzardTempStorage($this);
});

// ─── Quest Import ───────────────────────────────────────────

test('importQuests creates quests with expansion and zone maps', function (): void {
    $mock = $this->mock(BlizzardApiClient::class);

    // Area index with 2 areas
    $mock->shouldReceive('get')
        ->with('data/wow/quest/area/index', \Mockery::any())
        ->andReturn(['areas' => [
            ['id' => 10, 'name' => 'Durotar'],
            ['id' => 20, 'name' => 'Nagrand'],
        ]]);

    // Area 10: Durotar with 1 quest
    $mock->shouldReceive('getAsync')
        ->with('data/wow/quest/area/10', \Mockery::any())
        ->andReturn(Create::promiseFor(new Response(200, [], json_encode([
            'area' => ['name' => 'Durotar'],
            'quests' => [['id' => 100, 'name' => 'Quête de Durotar']],
        ]))));

    // Area 20: Nagrand with 2 quests
    $mock->shouldReceive('getAsync')
        ->with('data/wow/quest/area/20', \Mockery::any())
        ->andReturn(Create::promiseFor(new Response(200, [], json_encode([
            'area' => ['name' => 'Nagrand'],
            'quests' => [
                ['id' => 101, 'name' => 'Quête de Nagrand'],
                ['id' => 200, 'name' => 'Quête sans expansion'],
            ],
        ]))));

    $areaExpansionMap = [10 => 0, 20 => 1];

    $blizzardBatchImporter = resolve(BlizzardBatchImporter::class);
    $blizzardBatchImporter->importQuests($areaExpansionMap);

    expect(WowQuest::query()->count())->toBe(3);
    expect(WowQuest::query()->find(100)->expansion_id)->toBe(0);
    expect(WowQuest::query()->find(100)->zone_name)->toBe('Durotar');
    expect(WowQuest::query()->find(101)->expansion_id)->toBe(1);
    expect(WowQuest::query()->find(101)->zone_name)->toBe('Nagrand');
    expect(WowQuest::query()->find(200)->expansion_id)->toBe(1); // fallback to area expansion
    expect(WowQuest::query()->find(200)->zone_name)->toBe('Nagrand');
});

test('importQuests uses ContentTuning expansion over area expansion', function (): void {
    $mock = $this->mock(BlizzardApiClient::class);

    $mock->shouldReceive('get')
        ->with('data/wow/quest/area/index', \Mockery::any())
        ->andReturn(['areas' => [['id' => 10, 'name' => 'Zone']]]);

    $mock->shouldReceive('getAsync')
        ->with('data/wow/quest/area/10', \Mockery::any())
        ->andReturn(Create::promiseFor(new Response(200, [], json_encode([
            'area' => ['name' => 'Durotar'],
            'quests' => [
                ['id' => 500, 'name' => 'Quête TWW'],
                ['id' => 501, 'name' => 'Quête Midnight'],
            ],
        ]))));

    $areaExpansionMap = [10 => 0]; // area says Classic
    $questExpansionMap = [500 => 10, 501 => 11]; // ContentTuning says TWW/Midnight

    $blizzardBatchImporter = resolve(BlizzardBatchImporter::class);
    $blizzardBatchImporter->importQuests($areaExpansionMap, $questExpansionMap);

    expect(WowQuest::query()->find(500)->expansion_id)->toBe(10); // CT wins
    expect(WowQuest::query()->find(501)->expansion_id)->toBe(11); // CT wins
});

test('importQuests assigns faction from quest and zone faction maps', function (): void {
    $mock = $this->mock(BlizzardApiClient::class);

    $mock->shouldReceive('get')
        ->with('data/wow/quest/area/index', \Mockery::any())
        ->andReturn(['areas' => [['id' => 10, 'name' => 'Zone']]]);

    $mock->shouldReceive('getAsync')
        ->with('data/wow/quest/area/10', \Mockery::any())
        ->andReturn(Create::promiseFor(new Response(200, [], json_encode([
            'area' => ['name' => 'Hurlevent'],
            'quests' => [
                ['id' => 100, 'name' => 'Quête Alliance'],
                ['id' => 101, 'name' => 'Quête zone'],
            ],
        ]))));

    $questFactionMap = [100 => 'Alliance'];
    $zoneFactionMap = [10 => 'Alliance'];

    $blizzardBatchImporter = resolve(BlizzardBatchImporter::class);
    $blizzardBatchImporter->importQuests([], [], $questFactionMap, $zoneFactionMap);

    expect(WowQuest::query()->find(100)->faction)->toBe('Alliance'); // from questFactionMap
    expect(WowQuest::query()->find(101)->faction)->toBe('Alliance'); // from zoneFactionMap
});

test('importQuests handles empty area index', function (): void {
    $mock = $this->mock(BlizzardApiClient::class);

    $mock->shouldReceive('get')
        ->with('data/wow/quest/area/index', \Mockery::any())
        ->andReturn(['areas' => []]);

    $blizzardBatchImporter = resolve(BlizzardBatchImporter::class);
    $blizzardBatchImporter->importQuests([]);

    expect(WowQuest::query()->count())->toBe(0);
});

test('importQuests defaults unmapped areas to expansion 0', function (): void {
    $mock = $this->mock(BlizzardApiClient::class);

    $mock->shouldReceive('get')
        ->with('data/wow/quest/area/index', \Mockery::any())
        ->andReturn(['areas' => [['id' => 99, 'name' => 'Unknown']]]);

    $mock->shouldReceive('getAsync')
        ->with('data/wow/quest/area/99', \Mockery::any())
        ->andReturn(Create::promiseFor(new Response(200, [], json_encode([
            'area' => ['name' => 'Unknown Zone'],
            'quests' => [['id' => 100, 'name' => 'Quête orpheline']],
        ]))));

    $blizzardBatchImporter = resolve(BlizzardBatchImporter::class);
    $blizzardBatchImporter->importQuests([]); // no area expansion map

    expect(WowQuest::query()->find(100)->expansion_id)->toBe(0);
});

// ─── Achievement Import ─────────────────────────────────────

test('importAchievements creates achievements from SimpleArmory data', function (): void {
    bbiWriteAchievementsJson([
        [
            'name' => 'General',
            'cats' => [
                [
                    'name' => 'Classic',
                    'subcats' => [
                        [
                            'name' => 'Exploration',
                            'items' => [
                                ['id' => 10, 'icon' => 'spell_nature_healingtouch', 'points' => 10],
                                ['id' => 11, 'icon' => 'spell_holy_light', 'points' => 5],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ]);
    bbiMockNameIndex($this->mock(BlizzardApiClient::class), 'achievement/index', 'achievements', [
        10 => 'Bienvenue !',
        11 => 'Niveau 10',
    ]);

    $blizzardBatchImporter = resolve(BlizzardBatchImporter::class);
    $blizzardBatchImporter->importAchievements();

    expect(WowAchievement::query()->count())->toBe(2);
    expect(WowAchievement::query()->find(10)->name_fr)->toBe('Bienvenue !');
    expect(WowAchievement::query()->find(10)->category_name)->toBe('General');
    expect(WowAchievement::query()->find(10)->expansion_id)->toBe(0);
    expect(WowAchievement::query()->find(10)->points)->toBe(10);
    expect(WowAchievement::query()->find(10)->icon_url)->toBe('https://wow.zamimg.com/images/wow/icons/medium/spell_nature_healingtouch.jpg');
    expect(WowAchievement::query()->find(11)->points)->toBe(5);
});

test('importAchievements assigns expansion from category name', function (): void {
    bbiWriteAchievementsJson([
        [
            'name' => 'Quests',
            'cats' => [
                [
                    'name' => 'The War Within',
                    'subcats' => [
                        [
                            'name' => 'Khaz Algar',
                            'items' => [
                                ['id' => 20, 'icon' => 'inv_misc_map01', 'points' => 10],
                            ],
                        ],
                    ],
                ],
                [
                    'name' => 'Battle for Azeroth',
                    'subcats' => [
                        [
                            'name' => 'Zandalar',
                            'items' => [
                                ['id' => 21, 'icon' => 'inv_misc_map02', 'points' => 10],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ]);
    bbiMockNameIndex($this->mock(BlizzardApiClient::class), 'achievement/index', 'achievements', [
        20 => 'Quêtes de Khaz Algar',
        21 => 'Quêtes de Zandalar',
    ]);

    $blizzardBatchImporter = resolve(BlizzardBatchImporter::class);
    $blizzardBatchImporter->importAchievements();

    expect(WowAchievement::query()->find(20)->expansion_id)->toBe(10); // The War Within
    expect(WowAchievement::query()->find(21)->expansion_id)->toBe(7); // Battle for Azeroth
});

test('importAchievements skips notReleased items', function (): void {
    bbiWriteAchievementsJson([
        [
            'name' => 'General',
            'cats' => [
                [
                    'name' => 'Classic',
                    'subcats' => [
                        [
                            'name' => 'Exploration',
                            'items' => [
                                ['id' => 10, 'icon' => 'spell_holy_light', 'points' => 10],
                                ['id' => 11, 'icon' => 'spell_fire_fireball', 'points' => 5, 'notReleased' => true],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ]);
    bbiMockNameIndex($this->mock(BlizzardApiClient::class), 'achievement/index', 'achievements', [
        10 => 'Achievement actif',
        11 => 'Achievement futur',
    ]);

    $blizzardBatchImporter = resolve(BlizzardBatchImporter::class);
    $blizzardBatchImporter->importAchievements();

    expect(WowAchievement::query()->count())->toBe(1);
    expect(WowAchievement::query()->find(10)->name_fr)->toBe('Achievement actif');
});

test('importAchievements handles missing JSON file gracefully', function (): void {
    $blizzardBatchImporter = resolve(BlizzardBatchImporter::class);
    $blizzardBatchImporter->importAchievements();

    expect(WowAchievement::query()->count())->toBe(0);
});

test('importAchievements leaves the catalog untouched when the API index is empty', function (): void {
    WowAchievement::query()->create(['id' => 10, 'name_fr' => 'Achievement existant', 'expansion_id' => 0, 'category_name' => 'Classic', 'points' => 10, 'is_active' => true]);

    bbiWriteAchievementsJson([
        [
            'name' => 'General',
            'cats' => [
                [
                    'name' => 'Classic',
                    'subcats' => [
                        [
                            'name' => 'Going Down!',
                            'items' => [
                                ['id' => 10, 'icon' => 'spell_holy_light', 'points' => 10],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ]);
    bbiMockNameIndex($this->mock(BlizzardApiClient::class), 'achievement/index', 'achievements', []);

    $blizzardBatchImporter = resolve(BlizzardBatchImporter::class);
    $blizzardBatchImporter->importAchievements();

    expect(WowAchievement::query()->count())->toBe(1);
    expect(WowAchievement::query()->find(10)->name_fr)->toBe('Achievement existant');
});

// ─── Mount Import ───────────────────────────────────────────

test('importMounts creates mounts from SimpleArmory data', function (): void {
    bbiWriteCollectionJson('mounts.json', [
        [
            'name' => 'Classic',
            'subcats' => [
                [
                    'name' => 'Reputation',
                    'items' => [
                        ['ID' => 1, 'icon' => 'ability_mount_drake_blue', 'spellid' => 12345, 'creatureId' => 0],
                        ['ID' => 2, 'icon' => 'ability_mount_horse', 'spellid' => 0, 'creatureId' => 0],
                    ],
                ],
            ],
        ],
    ]);
    bbiMockNameIndex($this->mock(BlizzardApiClient::class), 'mount/index', 'mounts', [
        1 => 'Loup noir',
        2 => 'Destrier squelette',
    ]);

    $blizzardBatchImporter = resolve(BlizzardBatchImporter::class);
    $blizzardBatchImporter->importMounts();

    expect(WowMount::query()->count())->toBe(2);
    expect(WowMount::query()->find(1)->name_fr)->toBe('Loup noir');
    expect(WowMount::query()->find(1)->category)->toBe('Classic');
    expect(WowMount::query()->find(1)->source)->toBe('Reputation');
    expect(WowMount::query()->find(1)->source_spell_id)->toBe(12345);
    expect(WowMount::query()->find(1)->icon_url)->toBe('https://wow.zamimg.com/images/wow/icons/medium/ability_mount_drake_blue.jpg');
    expect(WowMount::query()->find(2)->name_fr)->toBe('Destrier squelette');
});

// ─── Pet Import ─────────────────────────────────────────────

test('importPets creates pets from SimpleArmory data with API French names', function (): void {
    bbiWriteCollectionJson('pets.json', [
        [
            'name' => 'Classic',
            'subcats' => [
                [
                    'name' => 'Drop',
                    'items' => [
                        ['ID' => 1, 'icon' => 'spell_nature_pet', 'spellid' => 50001, 'creatureId' => 9999],
                        ['ID' => 2, 'icon' => 'spell_shadow_pet', 'spellid' => 50002, 'creatureId' => 8888],
                    ],
                ],
            ],
        ],
    ]);

    bbiMockNameIndex($this->mock(BlizzardApiClient::class), 'pet/index', 'pets', [
        1 => 'Dragonnet',
        2 => 'Petit chat',
    ]);

    $blizzardBatchImporter = resolve(BlizzardBatchImporter::class);
    $blizzardBatchImporter->importPets();

    expect(WowPet::query()->count())->toBe(2);
    expect(WowPet::query()->find(1)->name_fr)->toBe('Dragonnet');
    expect(WowPet::query()->find(1)->creature_id)->toBe(9999);
    expect(WowPet::query()->find(1)->category)->toBe('Classic');
    expect(WowPet::query()->find(1)->source)->toBe('Drop');
    expect(WowPet::query()->find(1)->icon_url)->toBe('https://wow.zamimg.com/images/wow/icons/medium/spell_nature_pet.jpg');
    expect(WowPet::query()->find(2)->name_fr)->toBe('Petit chat');
});

// ─── Decor Import ───────────────────────────────────────────

test('importDecor creates decor items from SimpleArmory data', function (): void {
    bbiWriteCollectionJson('decors.json', [
        [
            'name' => 'The War Within',
            'subcats' => [
                [
                    'name' => 'Quest',
                    'items' => [
                        ['ID' => 1, 'spellid' => 0, 'creatureId' => 0, 'itemId' => '245000'],
                        ['ID' => 2, 'spellid' => 0, 'creatureId' => 0, 'itemId' => '245001'],
                    ],
                ],
            ],
        ],
    ]);
    bbiMockNameIndex($this->mock(BlizzardApiClient::class), 'decor/index', 'decor_items', [
        1 => 'Foyer orné',
        2 => 'Tapis elfique',
    ]);

    $blizzardBatchImporter = resolve(BlizzardBatchImporter::class);
    $blizzardBatchImporter->importDecor();

    expect(WowDecor::query()->count())->toBe(2);
    expect(WowDecor::query()->find(1)->name_fr)->toBe('Foyer orné');
    expect(WowDecor::query()->find(1)->category)->toBe('The War Within');
    expect(WowDecor::query()->find(1)->source)->toBe('Quest');
    expect(WowDecor::query()->find(1)->item_id)->toBe(245000);
});

test('importDecor marks notObtainable items as inactive', function (): void {
    bbiWriteCollectionJson('decors.json', [
        [
            'name' => 'Undiscovered',
            'subcats' => [
                [
                    'name' => 'Undiscovered Sources',
                    'items' => [
                        ['ID' => 10, 'spellid' => 0, 'creatureId' => 0, 'itemId' => '300000', 'notObtainable' => true],
                    ],
                ],
            ],
        ],
        [
            'name' => 'The War Within',
            'subcats' => [
                [
                    'name' => 'Quest',
                    'items' => [
                        ['ID' => 1, 'spellid' => 0, 'creatureId' => 0, 'itemId' => '245000'],
                    ],
                ],
            ],
        ],
    ]);
    bbiMockNameIndex($this->mock(BlizzardApiClient::class), 'decor/index', 'decor_items', [
        1 => 'Foyer orné',
        10 => 'Décor caché',
    ]);

    $blizzardBatchImporter = resolve(BlizzardBatchImporter::class);
    $blizzardBatchImporter->importDecor();

    expect(WowDecor::query()->count())->toBe(2);
    expect(WowDecor::query()->find(1)->is_active)->toBeTrue();
    expect(WowDecor::query()->find(10)->is_active)->toBeFalse();
    expect(WowDecor::query()->find(10)->category)->toBe('Undiscovered');
});

// ─── Profession Import ──────────────────────────────────────

test('importProfessions creates professions and recipes from the API', function (): void {
    $mock = $this->mock(BlizzardApiClient::class);
    bbiMockProfessionApi($mock, 171, 'Alchimie', 'PRIMARY', 2871, 'Alchimie de Khaz Algar', [
        ['name' => 'Potions', 'recipes' => [
            ['id' => 5001, 'name' => 'Potion de vie'],
            ['id' => 5002, 'name' => 'Potion de mana'],
        ]],
    ]);

    $blizzardBatchImporter = resolve(BlizzardBatchImporter::class);
    $blizzardBatchImporter->importProfessions();

    expect(WowProfession::query()->count())->toBe(1);
    expect(WowProfession::query()->find(171)->name_fr)->toBe('Alchimie');
    expect(WowProfession::query()->find(171)->type)->toBe('primary');

    expect(WowRecipe::query()->count())->toBe(2);
    expect(WowRecipe::query()->find(5001)->name_fr)->toBe('Potion de vie');
    expect(WowRecipe::query()->find(5001)->expansion_id)->toBe(10); // Khaz Algar = TWW
    expect(WowRecipe::query()->find(5001)->category_name)->toBe('Potions');
});

test('importProfessions assigns recipe factions from faction map', function (): void {
    $mock = $this->mock(BlizzardApiClient::class);
    bbiMockProfessionApi($mock, 171, 'Alchimie', 'PRIMARY', 2871, 'Alchimie classique', [
        ['name' => 'Potions classiques', 'recipes' => [
            ['id' => 5001, 'name' => 'Potion Alliance'],
        ]],
    ]);

    $blizzardBatchImporter = resolve(BlizzardBatchImporter::class);
    $blizzardBatchImporter->importProfessions([5001 => 'Alliance']);

    expect(WowRecipe::query()->find(5001)->faction)->toBe('Alliance');
});

// ─── Mirror Quest Faction Tagging ───────────────────────────

test('tagMirrorQuestFactions tags mirror quest pairs via API reputation', function (): void {
    // Create mirror pair: same name + zone, no faction
    WowQuest::factory()->create([
        'id' => 100,
        'name_fr' => 'Mission de guerre',
        'zone_name' => 'Vallée de Tiragarde',
        'expansion_id' => 7,
        'faction' => null,
        'is_active' => true,
    ]);
    WowQuest::factory()->create([
        'id' => 101,
        'name_fr' => 'Mission de guerre',
        'zone_name' => 'Vallée de Tiragarde',
        'expansion_id' => 7,
        'faction' => null,
        'is_active' => true,
    ]);

    $mock = $this->mock(BlizzardApiClient::class);

    // Quest 100 returns a Horde reputation
    $mock->shouldReceive('getAsync')
        ->with('data/wow/quest/100', \Mockery::any())
        ->andReturn(Create::promiseFor(new Response(200, [], json_encode([
            'rewards' => [
                'reputations' => [
                    ['reward' => ['id' => 2103]], // Zandalari
                ],
            ],
        ]))));

    // Quest 101 returns no faction reputation
    $mock->shouldReceive('getAsync')
        ->with('data/wow/quest/101', \Mockery::any())
        ->andReturn(Create::promiseFor(new Response(200, [], json_encode([
            'rewards' => [],
        ]))));

    $reputationFactionMap = [2103 => 'Horde'];

    $blizzardBatchImporter = resolve(BlizzardBatchImporter::class);
    $blizzardBatchImporter->tagMirrorQuestFactions($reputationFactionMap);

    expect(WowQuest::query()->find(100)->faction)->toBe('Horde');
    expect(WowQuest::query()->find(101)->faction)->toBe('Alliance');
});

// ─── Mirror Recipe Faction Tagging ──────────────────────────

test('tagMirrorRecipeFactions tags untagged recipe in mirror pair', function (): void {
    $profession = WowProfession::factory()->create(['id' => 171]);

    WowRecipe::factory()->create([
        'id' => 5001,
        'name_fr' => 'Potion spéciale',
        'profession_id' => 171,
        'expansion_id' => 7,
        'faction' => 'Alliance',
        'is_active' => true,
    ]);
    WowRecipe::factory()->create([
        'id' => 5002,
        'name_fr' => 'Potion spéciale',
        'profession_id' => 171,
        'expansion_id' => 7,
        'faction' => null,
        'is_active' => true,
    ]);

    $blizzardBatchImporter = resolve(BlizzardBatchImporter::class);
    $blizzardBatchImporter->tagMirrorRecipeFactions();

    expect(WowRecipe::query()->find(5002)->faction)->toBe('Horde');
});

// ─── SA JSON + CSV helpers ──────────────────────────────────

/**
 * Write a SimpleArmory achievements.json file with supercats structure.
 *
 * @param  list<array<string, mixed>>  $supercats
 */
function bbiWriteAchievementsJson(array $supercats): void
{
    $json = json_encode(['supercats' => $supercats], JSON_THROW_ON_ERROR);
    file_put_contents(storage_path('app/blizzard/achievements.json'), $json);
}

/**
 * Write a SimpleArmory collection JSON file (mounts.json, pets.json, decors.json).
 *
 * @param  list<array<string, mixed>>  $categories
 */
function bbiWriteCollectionJson(string $filename, array $categories): void
{
    $json = json_encode($categories, JSON_THROW_ON_ERROR);
    file_put_contents(storage_path('app/blizzard/'.$filename), $json);
}

/**
 * Mocke un index API id → nom (mount/index, pet/index, achievement/index, decor/index).
 *
 * @param  array<int, string>  $names  [id => nom FR]
 */
function bbiMockNameIndex(\Mockery\MockInterface $mock, string $endpoint, string $listKey, array $names): void
{
    $entries = [];
    foreach ($names as $id => $name) {
        $entries[] = ['id' => $id, 'name' => $name];
    }

    $mock->shouldReceive('get')
        ->with('data/wow/'.$endpoint, \Mockery::any())
        ->andReturn([$listKey => $entries]);
}

/**
 * Mocke le pipeline Profession API complet : index → détail → skill tier.
 *
 * @param  list<array{name: string, recipes: list<array{id: int, name: string}>}>  $categories
 */
function bbiMockProfessionApi(\Mockery\MockInterface $mock, int $professionId, string $name, string $type, int $tierId, string $tierName, array $categories): void
{
    $mock->shouldReceive('get')
        ->with('data/wow/profession/index', \Mockery::any())
        ->andReturn(['professions' => [['id' => $professionId, 'name' => $name]]]);

    $mock->shouldReceive('getAsync')
        ->with('data/wow/profession/'.$professionId, \Mockery::any())
        ->andReturnUsing(fn (): \GuzzleHttp\Promise\PromiseInterface => Create::promiseFor(new Response(200, [], (string) json_encode([
            'id' => $professionId,
            'name' => $name,
            'type' => ['type' => $type, 'name' => 'x'],
            'skill_tiers' => [['id' => $tierId, 'name' => $tierName]],
        ]))));

    $mock->shouldReceive('getAsync')
        ->with(sprintf('data/wow/profession/%d/skill-tier/%d', $professionId, $tierId), \Mockery::any())
        ->andReturnUsing(fn (): \GuzzleHttp\Promise\PromiseInterface => Create::promiseFor(new Response(200, [], (string) json_encode([
            'id' => $tierId,
            'categories' => $categories,
        ]))));
}
