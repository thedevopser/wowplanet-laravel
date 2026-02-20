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
use Illuminate\Support\Sleep;

beforeEach(function (): void {
    Sleep::fake();
});

// ─── Quest Import ───────────────────────────────────────────

test('importQuests creates quests with correct expansion from DB2 area map', function (): void {
    $mock = $this->mock(BlizzardApiClient::class);

    $mock->shouldReceive('get')
        ->with('data/wow/quest/area/index', \Mockery::any())
        ->andReturn([
            'areas' => [
                ['id' => 1, 'name' => 'Durotar'],
                ['id' => 2, 'name' => 'Nagrand'],
            ],
        ]);

    $mock->shouldReceive('get')
        ->with('data/wow/quest/area/1', \Mockery::any())
        ->andReturn([
            'area' => 'Durotar',
            'quests' => [
                ['id' => 100, 'name' => 'Quête de Durotar'],
                ['id' => 101, 'name' => 'Seconde quête'],
            ],
        ]);

    $mock->shouldReceive('get')
        ->with('data/wow/quest/area/2', \Mockery::any())
        ->andReturn([
            'area' => ['name' => 'Nagrand'],
            'quests' => [
                ['id' => 200, 'name' => 'Quête de Nagrand'],
            ],
        ]);

    $areaExpansionMap = [1 => 0, 2 => 1]; // Durotar=Classic, Nagrand=TBC

    $blizzardBatchImporter = resolve(BlizzardBatchImporter::class);
    $blizzardBatchImporter->importQuests($areaExpansionMap);

    expect(WowQuest::query()->count())->toBe(3);
    expect(WowQuest::query()->find(100)->expansion_id)->toBe(0);
    expect(WowQuest::query()->find(100)->zone_name)->toBe('Durotar');
    expect(WowQuest::query()->find(200)->expansion_id)->toBe(1);
    expect(WowQuest::query()->find(200)->zone_name)->toBe('Nagrand');
});

test('importQuests applies modern quest overrides for expansion >= 10', function (): void {
    $mock = $this->mock(BlizzardApiClient::class);

    $mock->shouldReceive('get')
        ->with('data/wow/quest/area/index', \Mockery::any())
        ->andReturn([
            'areas' => [['id' => 10, 'name' => 'Île de Dorn']],
        ]);

    $mock->shouldReceive('get')
        ->with('data/wow/quest/area/10', \Mockery::any())
        ->andReturn([
            'area' => 'Île de Dorn',
            'quests' => [
                ['id' => 500, 'name' => 'Quête TWW'],
                ['id' => 501, 'name' => 'Quête Midnight'],
            ],
        ]);

    $areaExpansionMap = [10 => 10]; // Île de Dorn = TWW
    $modernQuestOverrides = [501 => 11]; // Quest 501 is actually Midnight

    $blizzardBatchImporter = resolve(BlizzardBatchImporter::class);
    $blizzardBatchImporter->importQuests($areaExpansionMap, $modernQuestOverrides);

    expect(WowQuest::query()->find(500)->expansion_id)->toBe(10); // TWW from area
    expect(WowQuest::query()->find(501)->expansion_id)->toBe(11); // Midnight from override
});

test('importQuests assigns faction from quest faction map', function (): void {
    $mock = $this->mock(BlizzardApiClient::class);

    $mock->shouldReceive('get')
        ->with('data/wow/quest/area/index', \Mockery::any())
        ->andReturn([
            'areas' => [['id' => 1, 'name' => 'Durotar']],
        ]);

    $mock->shouldReceive('get')
        ->with('data/wow/quest/area/1', \Mockery::any())
        ->andReturn([
            'area' => 'Durotar',
            'quests' => [
                ['id' => 100, 'name' => 'Quête Alliance'],
                ['id' => 101, 'name' => 'Quête neutre'],
            ],
        ]);

    $areaExpansionMap = [1 => 0];
    $questFactionMap = [100 => 'Alliance'];

    $blizzardBatchImporter = resolve(BlizzardBatchImporter::class);
    $blizzardBatchImporter->importQuests($areaExpansionMap, [], $questFactionMap);

    expect(WowQuest::query()->find(100)->faction)->toBe('Alliance');
    expect(WowQuest::query()->find(101)->faction)->toBeNull();
});

test('importQuests assigns faction from zone faction map as fallback', function (): void {
    $mock = $this->mock(BlizzardApiClient::class);

    $mock->shouldReceive('get')
        ->with('data/wow/quest/area/index', \Mockery::any())
        ->andReturn([
            'areas' => [['id' => 1, 'name' => 'Durotar']],
        ]);

    $mock->shouldReceive('get')
        ->with('data/wow/quest/area/1', \Mockery::any())
        ->andReturn([
            'area' => 'Durotar',
            'quests' => [['id' => 100, 'name' => 'Quête Horde']],
        ]);

    $areaExpansionMap = [1 => 0];
    $zoneFactionMap = [1 => 'Horde'];

    $blizzardBatchImporter = resolve(BlizzardBatchImporter::class);
    $blizzardBatchImporter->importQuests($areaExpansionMap, [], [], $zoneFactionMap);

    expect(WowQuest::query()->find(100)->faction)->toBe('Horde');
});

test('importQuests skips quests with empty names', function (): void {
    $mock = $this->mock(BlizzardApiClient::class);

    $mock->shouldReceive('get')
        ->with('data/wow/quest/area/index', \Mockery::any())
        ->andReturn([
            'areas' => [['id' => 1, 'name' => 'Zone']],
        ]);

    $mock->shouldReceive('get')
        ->with('data/wow/quest/area/1', \Mockery::any())
        ->andReturn([
            'area' => 'Zone',
            'quests' => [
                ['id' => 100, 'name' => ''],
                ['id' => 101, 'name' => 'Quête valide'],
            ],
        ]);

    $blizzardBatchImporter = resolve(BlizzardBatchImporter::class);
    $blizzardBatchImporter->importQuests([1 => 0]);

    expect(WowQuest::query()->count())->toBe(1);
    expect(WowQuest::query()->first()->id)->toBe(101);
});

test('importQuests defaults unmapped areas to expansion 0', function (): void {
    $mock = $this->mock(BlizzardApiClient::class);

    $mock->shouldReceive('get')
        ->with('data/wow/quest/area/index', \Mockery::any())
        ->andReturn([
            'areas' => [['id' => 999, 'name' => 'Zone inconnue']],
        ]);

    $mock->shouldReceive('get')
        ->with('data/wow/quest/area/999', \Mockery::any())
        ->andReturn([
            'area' => 'Zone inconnue',
            'quests' => [['id' => 100, 'name' => 'Quête orpheline']],
        ]);

    $blizzardBatchImporter = resolve(BlizzardBatchImporter::class);
    $blizzardBatchImporter->importQuests([]); // empty map

    expect(WowQuest::query()->find(100)->expansion_id)->toBe(0);
});

// ─── Achievement Import ─────────────────────────────────────

test('importAchievements creates achievements from category tree', function (): void {
    $mock = $this->mock(BlizzardApiClient::class);

    $mock->shouldReceive('get')
        ->with('data/wow/achievement-category/index', \Mockery::any())
        ->andReturn([
            'root_categories' => [
                ['id' => 1, 'name' => 'Général'],
            ],
        ]);

    $mock->shouldReceive('get')
        ->with('data/wow/achievement-category/1', \Mockery::any())
        ->andReturn([
            'name' => 'Général',
            'achievements' => [
                ['id' => 10, 'name' => 'Bienvenue !'],
                ['id' => 11, 'name' => 'Niveau 10'],
            ],
            'subcategories' => [],
        ]);

    $blizzardBatchImporter = resolve(BlizzardBatchImporter::class);
    $blizzardBatchImporter->importAchievements();

    expect(WowAchievement::query()->count())->toBe(2);
    expect(WowAchievement::query()->find(10)->name_fr)->toBe('Bienvenue !');
    expect(WowAchievement::query()->find(10)->category_name)->toBe('Général');
    expect(WowAchievement::query()->find(10)->expansion_id)->toBe(0); // default when no expansion match
});

test('importAchievements uses addon expansion map when available', function (): void {
    $mock = $this->mock(BlizzardApiClient::class);

    $mock->shouldReceive('get')
        ->with('data/wow/achievement-category/index', \Mockery::any())
        ->andReturn([
            'root_categories' => [['id' => 1, 'name' => 'Général']],
        ]);

    $mock->shouldReceive('get')
        ->with('data/wow/achievement-category/1', \Mockery::any())
        ->andReturn([
            'name' => 'Général',
            'achievements' => [
                ['id' => 10, 'name' => 'Achievement X'],
                ['id' => 11, 'name' => 'Achievement Y'],
            ],
            'subcategories' => [],
        ]);

    $addonMap = [10 => 7]; // Achievement 10 mapped to BfA via addon

    $blizzardBatchImporter = resolve(BlizzardBatchImporter::class);
    $blizzardBatchImporter->importAchievements($addonMap);

    expect(WowAchievement::query()->find(10)->expansion_id)->toBe(7); // From addon
    expect(WowAchievement::query()->find(11)->expansion_id)->toBe(0); // Default (no match in category)
});

test('importAchievements traverses subcategories recursively', function (): void {
    $mock = $this->mock(BlizzardApiClient::class);

    $mock->shouldReceive('get')
        ->with('data/wow/achievement-category/index', \Mockery::any())
        ->andReturn([
            'root_categories' => [['id' => 1, 'name' => 'Quêtes']],
        ]);

    $mock->shouldReceive('get')
        ->with('data/wow/achievement-category/1', \Mockery::any())
        ->andReturn([
            'name' => 'Quêtes',
            'achievements' => [],
            'subcategories' => [['id' => 2]],
        ]);

    $mock->shouldReceive('get')
        ->with('data/wow/achievement-category/2', \Mockery::any())
        ->andReturn([
            'name' => 'Norfendre',
            'achievements' => [
                ['id' => 20, 'name' => 'Quêtes du Norfendre'],
            ],
            'subcategories' => [],
        ]);

    $blizzardBatchImporter = resolve(BlizzardBatchImporter::class);
    $blizzardBatchImporter->importAchievements();

    expect(WowAchievement::query()->count())->toBe(1);
    expect(WowAchievement::query()->find(20)->expansion_id)->toBe(2); // Norfendre = WotLK
    expect(WowAchievement::query()->find(20)->category_name)->toBe('Quêtes'); // root category name
});

// ─── Mount Import ───────────────────────────────────────────

test('importMounts creates mounts from index', function (): void {
    $mock = $this->mock(BlizzardApiClient::class);

    $mock->shouldReceive('get')
        ->with('data/wow/mount/index', \Mockery::any())
        ->andReturn([
            'mounts' => [
                ['id' => 1, 'name' => 'Loup noir'],
                ['id' => 2, 'name' => 'Destrier squelette'],
                ['id' => 3, 'name' => ''], // empty name, should be skipped
            ],
        ]);

    // Create a mock mount.csv for spell map
    $csvDir = storage_path('app/blizzard');
    if (! is_dir($csvDir)) {
        mkdir($csvDir, 0755, true);
    }

    file_put_contents(storage_path('app/blizzard/mount.csv'), implode("\n", [
        'Name_lang,SourceText_lang,Description_lang,ID,MountTypeID,Flags,SourceTypeEnum,SourceSpellID',
        ',,,"1",0,0,0,"12345"',
    ]));

    $blizzardBatchImporter = resolve(BlizzardBatchImporter::class);
    $blizzardBatchImporter->importMounts();

    expect(WowMount::query()->count())->toBe(2);
    expect(WowMount::query()->find(1)->name_fr)->toBe('Loup noir');
    expect(WowMount::query()->find(1)->source_spell_id)->toBe(12345);
    expect(WowMount::query()->find(2)->name_fr)->toBe('Destrier squelette');
});

// ─── Pet Import ─────────────────────────────────────────────

test('importPets creates pets from index', function (): void {
    $mock = $this->mock(BlizzardApiClient::class);

    $mock->shouldReceive('get')
        ->with('data/wow/pet/index', \Mockery::any())
        ->andReturn([
            'pets' => [
                ['id' => 1, 'name' => 'Dragonnet'],
                ['id' => 2, 'name' => ''], // should be skipped
            ],
        ]);

    $csvDir = storage_path('app/blizzard');
    if (! is_dir($csvDir)) {
        mkdir($csvDir, 0755, true);
    }

    file_put_contents(storage_path('app/blizzard/battle_pet_species.csv'), implode("\n", [
        'Description_lang,SourceText_lang,ID,CreatureID,SummonSpellID,IconFileDataID',
        ',,"1","9999",0,0',
    ]));

    $blizzardBatchImporter = resolve(BlizzardBatchImporter::class);
    $blizzardBatchImporter->importPets();

    expect(WowPet::query()->count())->toBe(1);
    expect(WowPet::query()->find(1)->name_fr)->toBe('Dragonnet');
    expect(WowPet::query()->find(1)->creature_id)->toBe(9999);
});

// ─── Decor Import ───────────────────────────────────────────

test('importDecor creates decor items from index', function (): void {
    $mock = $this->mock(BlizzardApiClient::class);

    $mock->shouldReceive('get')
        ->with('data/wow/decor/index', \Mockery::any())
        ->andReturn([
            'decor_items' => [
                ['id' => 1, 'name' => 'Foyer orné'],
                ['id' => 2, 'name' => 'Tapis elfique'],
                ['id' => 3, 'name' => ''],
            ],
        ]);

    $blizzardBatchImporter = resolve(BlizzardBatchImporter::class);
    $blizzardBatchImporter->importDecor();

    expect(WowDecor::query()->count())->toBe(2);
    expect(WowDecor::query()->find(1)->name_fr)->toBe('Foyer orné');
});

// ─── Profession Import ──────────────────────────────────────

test('importProfessions creates professions and recipes', function (): void {
    // Pre-create profession (recipes have FK constraint on profession_id)
    WowProfession::factory()->create(['id' => 171, 'name_fr' => 'Alchimie old']);

    $mock = $this->mock(BlizzardApiClient::class);

    $mock->shouldReceive('get')
        ->with('data/wow/profession/index', \Mockery::any())
        ->andReturn([
            'professions' => [
                ['id' => 171, 'name' => 'Alchimie'],
            ],
        ]);

    $mock->shouldReceive('get')
        ->with('data/wow/profession/171', \Mockery::any())
        ->andReturn([
            'skill_tiers' => [
                ['id' => 2499, 'name' => 'Alchimie de Khaz Algar'],
            ],
        ]);

    $mock->shouldReceive('get')
        ->with('data/wow/profession/171/skill-tier/2499', \Mockery::any())
        ->andReturn([
            'maximum_skill_level' => 100,
            'categories' => [
                [
                    'name' => 'Potions',
                    'recipes' => [
                        ['id' => 5001, 'name' => 'Potion de vie'],
                        ['id' => 5002, 'name' => 'Potion de mana'],
                    ],
                ],
            ],
        ]);

    $csvDir = storage_path('app/blizzard');
    if (! is_dir($csvDir)) {
        mkdir($csvDir, 0755, true);
    }

    file_put_contents(storage_path('app/blizzard/skill_line_ability.csv'), implode("\n", [
        'RaceMask,AbilityVerb_lang,AbilityAllVerb_lang,ID,SkillLine,Spell',
        '0,,,"5001",171,"80001"',
    ]));

    $blizzardBatchImporter = resolve(BlizzardBatchImporter::class);
    $blizzardBatchImporter->importProfessions();

    expect(WowProfession::query()->count())->toBe(1);
    expect(WowProfession::query()->find(171)->name_fr)->toBe('Alchimie');
    expect(WowProfession::query()->find(171)->type)->toBe('primary');

    expect(WowRecipe::query()->count())->toBe(2);
    expect(WowRecipe::query()->find(5001)->name_fr)->toBe('Potion de vie');
    expect(WowRecipe::query()->find(5001)->expansion_id)->toBe(10); // Khaz Algar = TWW
    expect(WowRecipe::query()->find(5001)->category_name)->toBe('Potions');
    expect(WowRecipe::query()->find(5001)->wowhead_spell_id)->toBe(80001);
});

test('importProfessions assigns recipe factions from faction map', function (): void {
    WowProfession::factory()->create(['id' => 171, 'name_fr' => 'Alchimie']);

    $mock = $this->mock(BlizzardApiClient::class);

    $mock->shouldReceive('get')
        ->with('data/wow/profession/index', \Mockery::any())
        ->andReturn([
            'professions' => [['id' => 171, 'name' => 'Alchimie']],
        ]);

    $mock->shouldReceive('get')
        ->with('data/wow/profession/171', \Mockery::any())
        ->andReturn([
            'skill_tiers' => [['id' => 2499, 'name' => 'Alchimie Classique']],
        ]);

    $mock->shouldReceive('get')
        ->with('data/wow/profession/171/skill-tier/2499', \Mockery::any())
        ->andReturn([
            'maximum_skill_level' => 300,
            'categories' => [
                [
                    'name' => 'Potions',
                    'recipes' => [['id' => 5001, 'name' => 'Potion Alliance']],
                ],
            ],
        ]);

    $csvDir = storage_path('app/blizzard');
    if (! is_dir($csvDir)) {
        mkdir($csvDir, 0755, true);
    }

    file_put_contents(storage_path('app/blizzard/skill_line_ability.csv'), "header\n");

    $recipeFactionMap = [5001 => 'Alliance'];

    $blizzardBatchImporter = resolve(BlizzardBatchImporter::class);
    $blizzardBatchImporter->importProfessions($recipeFactionMap);

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
    $mock->shouldReceive('get')
        ->with('data/wow/quest/100', \Mockery::any())
        ->andReturn([
            'rewards' => [
                'reputations' => [
                    ['reward' => ['id' => 2103]], // Zandalari
                ],
            ],
        ]);

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

// ─── Mount Icons ────────────────────────────────────────────

test('importMountIcons fetches and stores icon URLs', function (): void {
    WowMount::factory()->create([
        'id' => 1,
        'name_fr' => 'Loup noir',
        'icon_url' => null,
    ]);

    $mock = $this->mock(BlizzardApiClient::class);

    $mock->shouldReceive('get')
        ->with('data/wow/mount/1', \Mockery::any())
        ->andReturn([
            'creature_displays' => [['id' => 555]],
        ]);

    $mock->shouldReceive('get')
        ->with('data/wow/media/creature-display/555', \Mockery::any())
        ->andReturn([
            'assets' => [['key' => 'zoom', 'value' => 'https://render.com/mount.jpg']],
        ]);

    $blizzardBatchImporter = resolve(BlizzardBatchImporter::class);
    $blizzardBatchImporter->importMountIcons();

    expect(WowMount::query()->find(1)->icon_url)->toBe('https://render.com/mount.jpg');
});

// ─── Pet Icons ──────────────────────────────────────────────

test('importPetIcons fetches and stores icon URLs', function (): void {
    WowPet::factory()->create([
        'id' => 1,
        'name_fr' => 'Dragonnet',
        'icon_url' => null,
    ]);

    $mock = $this->mock(BlizzardApiClient::class);

    $mock->shouldReceive('get')
        ->with('data/wow/media/pet/1', \Mockery::any())
        ->andReturn([
            'assets' => [['key' => 'icon', 'value' => 'https://render.com/pet.jpg']],
        ]);

    $blizzardBatchImporter = resolve(BlizzardBatchImporter::class);
    $blizzardBatchImporter->importPetIcons();

    expect(WowPet::query()->find(1)->icon_url)->toBe('https://render.com/pet.jpg');
});

// ─── Decor Icons ────────────────────────────────────────────

test('importDecorIcons fetches item_id and icon URLs', function (): void {
    WowDecor::factory()->create([
        'id' => 1,
        'name_fr' => 'Foyer orné',
        'icon_url' => null,
        'item_id' => null,
    ]);

    $mock = $this->mock(BlizzardApiClient::class);

    $mock->shouldReceive('get')
        ->with('data/wow/decor/1', \Mockery::any())
        ->andReturn([
            'items' => ['id' => 245000],
        ]);

    $mock->shouldReceive('get')
        ->with('data/wow/media/item/245000', \Mockery::any())
        ->andReturn([
            'assets' => [['key' => 'icon', 'value' => 'https://render.com/decor.jpg']],
        ]);

    $blizzardBatchImporter = resolve(BlizzardBatchImporter::class);
    $blizzardBatchImporter->importDecorIcons();

    expect(WowDecor::query()->find(1)->item_id)->toBe(245000);
    expect(WowDecor::query()->find(1)->icon_url)->toBe('https://render.com/decor.jpg');
});

// ─── Edge Cases ─────────────────────────────────────────────

test('importQuests handles API failure gracefully', function (): void {
    $mock = $this->mock(BlizzardApiClient::class);

    $mock->shouldReceive('get')
        ->with('data/wow/quest/area/index', \Mockery::any())
        ->andThrow(new \Exception('429 Too Many Requests'));

    $blizzardBatchImporter = resolve(BlizzardBatchImporter::class);
    $blizzardBatchImporter->importQuests([]);

    expect(WowQuest::query()->count())->toBe(0);
});

test('importAchievements handles empty category index gracefully', function (): void {
    $mock = $this->mock(BlizzardApiClient::class);

    $mock->shouldReceive('get')
        ->with('data/wow/achievement-category/index', \Mockery::any())
        ->andThrow(new \Exception('404 Not Found'));

    $blizzardBatchImporter = resolve(BlizzardBatchImporter::class);
    $blizzardBatchImporter->importAchievements();

    expect(WowAchievement::query()->count())->toBe(0);
});
