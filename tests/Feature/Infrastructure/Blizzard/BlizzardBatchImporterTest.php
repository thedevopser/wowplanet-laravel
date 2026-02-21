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

    $dir = storage_path('app/blizzard');
    if (! is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    // Back up real files to avoid interference with test data
    $this->testFiles = [
        'mount.csv', 'battle_pet_species.csv', 'skill_line_ability.csv',
        'achievement.csv', 'achievement_category.csv',
        'quest_v2_cli_task.csv', 'skill_line.csv', 'trade_skill_category.csv',
    ];
    $this->backups = [];
    foreach ($this->testFiles as $file) {
        $path = $dir.'/'.$file;
        if (file_exists($path)) {
            $backup = $path.'.testbak';
            rename($path, $backup);
            $this->backups[$path] = $backup;
        }
    }
});

afterEach(function (): void {
    foreach ($this->testFiles as $file) {
        $path = storage_path('app/blizzard/'.$file);
        if (file_exists($path)) {
            unlink($path);
        }
    }

    // Restore real files
    foreach ($this->backups as $path => $backup) {
        if (file_exists($backup)) {
            rename($backup, $path);
        }
    }
});

// ─── Quest Import ───────────────────────────────────────────

test('importQuests creates quests from CSV with expansion and zone maps', function (): void {
    $this->mock(BlizzardApiClient::class);

    bbiWriteQuestCsv([
        ['100', 'Quête de Durotar'],
        ['101', 'Quête de Nagrand'],
        ['200', 'Quête sans zone'],
    ]);

    $questExpansionMap = [100 => 0, 101 => 1];
    $questZoneMap = [100 => 'Durotar', 101 => 'Nagrand'];

    $blizzardBatchImporter = resolve(BlizzardBatchImporter::class);
    $blizzardBatchImporter->importQuests($questExpansionMap, $questZoneMap);

    expect(WowQuest::query()->count())->toBe(3);
    expect(WowQuest::query()->find(100)->expansion_id)->toBe(0);
    expect(WowQuest::query()->find(100)->zone_name)->toBe('Durotar');
    expect(WowQuest::query()->find(101)->expansion_id)->toBe(1);
    expect(WowQuest::query()->find(101)->zone_name)->toBe('Nagrand');
    expect(WowQuest::query()->find(200)->expansion_id)->toBe(0);
    expect(WowQuest::query()->find(200)->zone_name)->toBeNull();
});

test('importQuests uses expansion map for quest expansion', function (): void {
    $this->mock(BlizzardApiClient::class);

    bbiWriteQuestCsv([
        ['500', 'Quête TWW'],
        ['501', 'Quête Midnight'],
    ]);

    $questExpansionMap = [500 => 10, 501 => 11];

    $blizzardBatchImporter = resolve(BlizzardBatchImporter::class);
    $blizzardBatchImporter->importQuests($questExpansionMap);

    expect(WowQuest::query()->find(500)->expansion_id)->toBe(10);
    expect(WowQuest::query()->find(501)->expansion_id)->toBe(11);
});

test('importQuests assigns faction from quest faction map', function (): void {
    $this->mock(BlizzardApiClient::class);

    bbiWriteQuestCsv([
        ['100', 'Quête Alliance'],
        ['101', 'Quête neutre'],
    ]);

    $questFactionMap = [100 => 'Alliance'];

    $blizzardBatchImporter = resolve(BlizzardBatchImporter::class);
    $blizzardBatchImporter->importQuests([], [], $questFactionMap);

    expect(WowQuest::query()->find(100)->faction)->toBe('Alliance');
    expect(WowQuest::query()->find(101)->faction)->toBeNull();
});

test('importQuests skips quests with empty names', function (): void {
    $this->mock(BlizzardApiClient::class);

    bbiWriteQuestCsv([
        ['100', ''],
        ['101', 'Quête valide'],
    ]);

    $blizzardBatchImporter = resolve(BlizzardBatchImporter::class);
    $blizzardBatchImporter->importQuests([]);

    expect(WowQuest::query()->count())->toBe(1);
    expect(WowQuest::query()->first()->id)->toBe(101);
});

test('importQuests defaults unmapped quests to expansion 0', function (): void {
    $this->mock(BlizzardApiClient::class);

    bbiWriteQuestCsv([
        ['100', 'Quête orpheline'],
    ]);

    $blizzardBatchImporter = resolve(BlizzardBatchImporter::class);
    $blizzardBatchImporter->importQuests([]); // empty map

    expect(WowQuest::query()->find(100)->expansion_id)->toBe(0);
});

test('importQuests handles missing CSV gracefully', function (): void {
    $this->mock(BlizzardApiClient::class);

    // No CSV file written
    $blizzardBatchImporter = resolve(BlizzardBatchImporter::class);
    $blizzardBatchImporter->importQuests([]);

    expect(WowQuest::query()->count())->toBe(0);
});

// ─── Achievement Import ─────────────────────────────────────

test('importAchievements creates achievements from CSV data', function (): void {
    bbiWriteAchievementCategoryCsv([
        ['1', 'Général', '-1'],
    ]);
    bbiWriteAchievementCsv([
        ['10', 'Bienvenue !', '1'],
        ['11', 'Niveau 10', '1'],
    ]);

    $blizzardBatchImporter = resolve(BlizzardBatchImporter::class);
    $blizzardBatchImporter->importAchievements();

    expect(WowAchievement::query()->count())->toBe(2);
    expect(WowAchievement::query()->find(10)->name_fr)->toBe('Bienvenue !');
    expect(WowAchievement::query()->find(10)->category_name)->toBe('Général');
    expect(WowAchievement::query()->find(10)->expansion_id)->toBe(0);
});

test('importAchievements uses expansion map when available', function (): void {
    bbiWriteAchievementCategoryCsv([
        ['1', 'Général', '-1'],
    ]);
    bbiWriteAchievementCsv([
        ['10', 'Achievement X', '1'],
        ['11', 'Achievement Y', '1'],
    ]);

    $addonMap = [10 => 7]; // Achievement 10 mapped to BfA

    $blizzardBatchImporter = resolve(BlizzardBatchImporter::class);
    $blizzardBatchImporter->importAchievements($addonMap);

    expect(WowAchievement::query()->find(10)->expansion_id)->toBe(7); // From map
    expect(WowAchievement::query()->find(11)->expansion_id)->toBe(0); // Default
});

test('importAchievements resolves root category from hierarchy', function (): void {
    bbiWriteAchievementCategoryCsv([
        ['1', 'Quêtes', '-1'],
        ['2', 'Norfendre', '1'],
    ]);
    bbiWriteAchievementCsv([
        ['20', 'Quêtes du Norfendre', '2'],
    ]);

    $blizzardBatchImporter = resolve(BlizzardBatchImporter::class);
    $blizzardBatchImporter->importAchievements();

    expect(WowAchievement::query()->count())->toBe(1);
    expect(WowAchievement::query()->find(20)->category_name)->toBe('Quêtes');
});

// ─── Mount Import ───────────────────────────────────────────

test('importMounts creates mounts from CSV', function (): void {
    $this->mock(BlizzardApiClient::class);

    file_put_contents(storage_path('app/blizzard/mount.csv'), implode("\n", [
        'Name_lang,SourceText_lang,Description_lang,ID,MountTypeID,Flags,SourceTypeEnum,SourceSpellID',
        '"Loup noir",,,"1",0,0,0,"12345"',
        '"Destrier squelette",,,"2",0,0,0,"0"',
        ',,,"3",0,0,0,"0"',
    ]));

    $blizzardBatchImporter = resolve(BlizzardBatchImporter::class);
    $blizzardBatchImporter->importMounts();

    expect(WowMount::query()->count())->toBe(2);
    expect(WowMount::query()->find(1)->name_fr)->toBe('Loup noir');
    expect(WowMount::query()->find(1)->source_spell_id)->toBe(12345);
    expect(WowMount::query()->find(2)->name_fr)->toBe('Destrier squelette');
});

test('importMountCategories enriches mounts with category and source', function (): void {
    WowMount::factory()->create(['id' => 1, 'name_fr' => 'Loup noir']);
    WowMount::factory()->create(['id' => 2, 'name_fr' => 'Destrier']);

    $categoryMap = [
        1 => ['category' => 'Classic', 'source' => 'Reputation'],
        2 => ['category' => 'The War Within', 'source' => 'Achievement'],
    ];

    $blizzardBatchImporter = resolve(BlizzardBatchImporter::class);
    $blizzardBatchImporter->importMountCategories($categoryMap);

    expect(WowMount::query()->find(1)->category)->toBe('Classic');
    expect(WowMount::query()->find(1)->source)->toBe('Reputation');
    expect(WowMount::query()->find(2)->category)->toBe('The War Within');
    expect(WowMount::query()->find(2)->source)->toBe('Achievement');
});

// ─── Pet Import ─────────────────────────────────────────────

test('importPets creates pets from CSV with spell name map', function (): void {
    $this->mock(BlizzardApiClient::class);

    file_put_contents(storage_path('app/blizzard/battle_pet_species.csv'), implode("\n", [
        'Description_lang,SourceText_lang,ID,CreatureID,SummonSpellID,IconFileDataID',
        ',,"1","9999","50001",0',
        ',,"2","8888","50002",0',
        ',,"3","7777","0",0',
    ]));

    $spellNameMap = [
        50001 => 'Invocation : Dragonnet',
        50002 => 'Invoquer Petit chat',
    ];

    $blizzardBatchImporter = resolve(BlizzardBatchImporter::class);
    $blizzardBatchImporter->importPets($spellNameMap);

    expect(WowPet::query()->count())->toBe(2);
    expect(WowPet::query()->find(1)->name_fr)->toBe('Dragonnet');
    expect(WowPet::query()->find(1)->creature_id)->toBe(9999);
    expect(WowPet::query()->find(2)->name_fr)->toBe('Petit chat');
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

test('importDecorCategories enriches decor items with category and source', function (): void {
    WowDecor::factory()->create(['id' => 1, 'name_fr' => 'Foyer orné']);
    WowDecor::factory()->create(['id' => 2, 'name_fr' => 'Tapis elfique']);

    $categoryMap = [
        1 => ['category' => 'The War Within', 'source' => 'Quest'],
        2 => ['category' => 'Midnight', 'source' => 'Achievement'],
    ];

    $blizzardBatchImporter = resolve(BlizzardBatchImporter::class);
    $blizzardBatchImporter->importDecorCategories($categoryMap);

    expect(WowDecor::query()->find(1)->category)->toBe('The War Within');
    expect(WowDecor::query()->find(1)->source)->toBe('Quest');
    expect(WowDecor::query()->find(2)->category)->toBe('Midnight');
    expect(WowDecor::query()->find(2)->source)->toBe('Achievement');
});

// ─── Profession Import ──────────────────────────────────────

test('importProfessions creates professions and recipes from CSV', function (): void {
    bbiWriteSkillLineCsv([
        ['171', 'Alchimie', '11', '0'],
        ['2499', 'Alchimie de Khaz Algar', '11', '171'],
    ]);
    bbiWriteSkillLineAbilityCsv([
        ['5001', '2499', '80001', '100'],
        ['5002', '2499', '80002', '0'],
    ]);
    bbiWriteTradeSkillCategoryCsv([
        ['100', 'Potions'],
    ]);

    $spellNameMap = [
        80001 => 'Potion de vie',
        80002 => 'Potion de mana',
    ];

    $blizzardBatchImporter = resolve(BlizzardBatchImporter::class);
    $blizzardBatchImporter->importProfessions($spellNameMap);

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
    bbiWriteSkillLineCsv([
        ['171', 'Alchimie', '11', '0'],
        ['2499', 'Alchimie Classique', '11', '171'],
    ]);
    bbiWriteSkillLineAbilityCsv([
        ['5001', '2499', '80001', '0'],
    ]);
    bbiWriteTradeSkillCategoryCsv([]);

    $spellNameMap = [80001 => 'Potion Alliance'];
    $recipeFactionMap = [5001 => 'Alliance'];

    $blizzardBatchImporter = resolve(BlizzardBatchImporter::class);
    $blizzardBatchImporter->importProfessions($spellNameMap, $recipeFactionMap);

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

test('importAchievements handles missing CSV files gracefully', function (): void {
    // No CSV files written — should produce 0 achievements
    $blizzardBatchImporter = resolve(BlizzardBatchImporter::class);
    $blizzardBatchImporter->importAchievements();

    expect(WowAchievement::query()->count())->toBe(0);
});

test('importAchievements skips achievements with empty or hidden names', function (): void {
    bbiWriteAchievementCategoryCsv([
        ['1', 'Général', '-1'],
    ]);
    bbiWriteAchievementCsv([
        ['10', 'Valid Achievement', '1'],
        ['11', '', '1'],
        ['12', '<Hidden> Debug Achievement', '1'],
    ]);

    $blizzardBatchImporter = resolve(BlizzardBatchImporter::class);
    $blizzardBatchImporter->importAchievements();

    expect(WowAchievement::query()->count())->toBe(1);
    expect(WowAchievement::query()->find(10)->name_fr)->toBe('Valid Achievement');
});

// ─── CSV helpers ────────────────────────────────────────────

function bbiWriteQuestCsv(array $rows): void
{
    $lines = ['ID,QuestTitle_lang,ContentTuningID,FiltRaces'];
    foreach ($rows as $row) {
        $title = str_replace('"', '""', $row[1]);
        $lines[] = sprintf('"%s","%s","0","-1"', $row[0], $title);
    }

    file_put_contents(storage_path('app/blizzard/quest_v2_cli_task.csv'), implode("\n", $lines));
}

function bbiWriteAchievementCsv(array $rows): void
{
    $lines = ['Description_lang,Title_lang,Reward_lang,ID,Instance_ID,Faction,Supercedes,Category,Minimum_criteria,Points,Flags,Ui_order,IconFileID,RewardItemID,Criteria_tree,Shares_criteria,CovenantID,HiddenBeforeDisplaySeason,LegacyAfterTimeEvent'];
    foreach ($rows as $row) {
        // $row = [ID, Title, Category]
        $title = str_replace('"', '""', $row[1]);
        $lines[] = sprintf(',"%s",,"%s","0","-1","0","%s","0","0","0","0","0","0","0","0","0","0","0"', $title, $row[0], $row[2]);
    }

    file_put_contents(storage_path('app/blizzard/achievement.csv'), implode("\n", $lines));
}

function bbiWriteAchievementCategoryCsv(array $rows): void
{
    $lines = ['Name_lang,ID,Parent,Ui_order'];
    foreach ($rows as $row) {
        // $row = [ID, Name, Parent]
        $lines[] = sprintf('"%s","%s","%s","0"', $row[1], $row[0], $row[2]);
    }

    file_put_contents(storage_path('app/blizzard/achievement_category.csv'), implode("\n", $lines));
}

function bbiWriteSkillLineCsv(array $rows): void
{
    $lines = ['ID,DisplayName_lang,CategoryID,ParentSkillLineID'];
    foreach ($rows as $row) {
        $lines[] = sprintf('"%s","%s","%s","%s"', $row[0], $row[1], $row[2], $row[3]);
    }

    file_put_contents(storage_path('app/blizzard/skill_line.csv'), implode("\n", $lines));
}

function bbiWriteSkillLineAbilityCsv(array $rows): void
{
    $lines = ['ID,SkillLine,Spell,TradeSkillCategoryID'];
    foreach ($rows as $row) {
        $lines[] = sprintf('"%s","%s","%s","%s"', $row[0], $row[1], $row[2], $row[3]);
    }

    file_put_contents(storage_path('app/blizzard/skill_line_ability.csv'), implode("\n", $lines));
}

function bbiWriteTradeSkillCategoryCsv(array $rows): void
{
    $lines = ['ID,Name_lang'];
    foreach ($rows as $row) {
        $lines[] = sprintf('"%s","%s"', $row[0], $row[1]);
    }

    file_put_contents(storage_path('app/blizzard/trade_skill_category.csv'), implode("\n", $lines));
}
