<?php

declare(strict_types=1);

use App\Domain\ValueObjects\ExpansionId;
use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    $this->basePath = base_path('storage/app/blizzard/mappings');
    $this->outputPath = base_path('storage/app/blizzard/mappings/processed');
});

afterEach(function (): void {
    // Clean up generated output files
    foreach (['achievements.json', 'quests.json'] as $file) {
        $path = $this->outputPath.'/'.$file;
        if (file_exists($path)) {
            unlink($path);
        }
    }

    // Clean up fixtures
    $krowiDir = $this->basePath.'/Krowi_AchievementFilter';
    if (is_dir($krowiDir)) {
        File::deleteDirectory($krowiDir);
    }

    $btwDir = $this->basePath.'/BTW';
    if (is_dir($btwDir)) {
        File::deleteDirectory($btwDir);
    }
});

test('command creates output directory if missing', function (): void {
    // Ensure output directory exists (it should after running)
    $this->artisan('blizzard:import-mappings')->assertSuccessful();

    expect(is_dir($this->outputPath))->toBeTrue();
});

test('command processes Krowi achievement files with French localization', function (): void {
    // Create Krowi folder structure
    $expansionDir = $this->basePath.'/Krowi_AchievementFilter/DataAddons/Retail/01_Vanilla';
    $locDir = $this->basePath.'/Krowi_AchievementFilter/Localization';
    File::makeDirectory($expansionDir, 0755, true);
    File::makeDirectory($locDir, 0755, true);

    // Create French localization file
    File::put($locDir.'/frFR.lua', <<<'LUA'
    L["Exploration"] = "Exploration"
    L["Loremaster of Eastern Kingdoms"] = "Maître du savoir des Royaumes de l'Est"
    LUA);

    // Create CategoryData.lua with achievement IDs
    File::put($expansionDir.'/CategoryData.lua', <<<'LUA'
    7520, -- Loremaster of Eastern Kingdoms
    7521, -- Explorer of Dun Morogh
    LUA);

    $this->artisan('blizzard:import-mappings')->assertSuccessful();

    $achievements = json_decode(File::get($this->outputPath.'/achievements.json'), true);

    expect($achievements)->toHaveKey((string) ExpansionId::CLASSIC);
    expect($achievements[ExpansionId::CLASSIC]['total_ids'])->toContain(7520);
    expect($achievements[ExpansionId::CLASSIC]['total_ids'])->toContain(7521);
    // French localization should be applied to achievement names
    $allNames = [];
    foreach ($achievements[ExpansionId::CLASSIC]['categories'] as $cat) {
        foreach ($cat['names'] as $id => $name) {
            $allNames[$id] = $name;
        }
    }

    expect($allNames[7520])->toBe("Maître du savoir des Royaumes de l'Est");
});

test('command skips missing Krowi expansion folders', function (): void {
    $this->artisan('blizzard:import-mappings')->assertSuccessful();

    $achievements = json_decode(File::get($this->outputPath.'/achievements.json'), true);

    // Should produce an empty map since no expansion folders exist
    expect($achievements)->toBe([]);
});

test('command processes BtWQuests files and extracts quest IDs', function (): void {
    // Create BTW folder structure for Classic
    $btwDir = $this->basePath.'/BTW/BtWQuestsClassic';
    File::makeDirectory($btwDir, 0755, true);

    // Create a zone file with quest IDs
    File::put($btwDir.'/Elwynn.lua', <<<'LUA'
    {
        id = 26,
        name = "A Threat Within",
    },
    {
        id = 28,
        name = "Kobold Camp Cleanup",
    },
    {
        ids = { 100, 101, 102 },
    }
    LUA);

    $this->artisan('blizzard:import-mappings')->assertSuccessful();

    $quests = json_decode(File::get($this->outputPath.'/quests.json'), true);

    expect($quests)->toHaveKey((string) ExpansionId::CLASSIC);
    expect($quests[ExpansionId::CLASSIC]['total_ids'])->toContain(26);
    expect($quests[ExpansionId::CLASSIC]['total_ids'])->toContain(28);
    expect($quests[ExpansionId::CLASSIC]['total_ids'])->toContain(100);
    expect($quests[ExpansionId::CLASSIC]['zones'])->toHaveKey('Elwynn');
});

test('command translates English zone names to French', function (): void {
    $btwDir = $this->basePath.'/BTW/BtWQuestsTheWarWithin';
    File::makeDirectory($btwDir, 0755, true);

    File::put($btwDir.'/Hallowfall.lua', 'id = 500,');

    $this->artisan('blizzard:import-mappings')->assertSuccessful();

    $quests = json_decode(File::get($this->outputPath.'/quests.json'), true);

    // Hallowfall → Sainte-Chute
    expect($quests[ExpansionId::THE_WAR_WITHIN]['zones'])->toHaveKey('Sainte-Chute');
});

test('command skips Defines and General files in BTW', function (): void {
    $btwDir = $this->basePath.'/BTW/BtWQuestsClassic';
    File::makeDirectory($btwDir, 0755, true);

    File::put($btwDir.'/Defines.lua', 'id = 999,');
    File::put($btwDir.'/General.lua', 'id = 998,');
    File::put($btwDir.'/Westfall.lua', 'id = 100,');

    $this->artisan('blizzard:import-mappings')->assertSuccessful();

    $quests = json_decode(File::get($this->outputPath.'/quests.json'), true);

    // Only Westfall should be included
    expect($quests[ExpansionId::CLASSIC]['total_ids'])->toBe([100]);
    expect($quests[ExpansionId::CLASSIC]['zones'])->not()->toHaveKey('Defines');
    expect($quests[ExpansionId::CLASSIC]['zones'])->not()->toHaveKey('General');
});

test('command maps version-numbered files to Général / Patchs zone', function (): void {
    $btwDir = $this->basePath.'/BTW/BtWQuestsTheWarWithin';
    File::makeDirectory($btwDir, 0755, true);

    File::put($btwDir.'/11.1.lua', 'id = 600,');

    $this->artisan('blizzard:import-mappings')->assertSuccessful();

    $quests = json_decode(File::get($this->outputPath.'/quests.json'), true);

    expect($quests[ExpansionId::THE_WAR_WITHIN]['zones'])->toHaveKey('Général / Patchs');
});

test('command loads BTW French quest names', function (): void {
    $btwDir = $this->basePath.'/BTW/BtWQuestsClassic';
    $dbDir = $btwDir.'/Database';
    File::makeDirectory($dbDir, 0755, true);

    // French names file
    File::put($dbDir.'/Quests.frFR.lua', <<<'LUA'
    [26] = { name = "Une menace intérieure" },
    [28] = { name = "Nettoyage du camp Kobold" },
    LUA);

    // Zone file referencing those IDs
    File::put($btwDir.'/Elwynn.lua', <<<'LUA'
    id = 26,
    id = 28,
    LUA);

    $this->artisan('blizzard:import-mappings')->assertSuccessful();

    $quests = json_decode(File::get($this->outputPath.'/quests.json'), true);
    $zoneNames = $quests[ExpansionId::CLASSIC]['zones']['Elwynn']['names'] ?? [];

    expect($zoneNames[26])->toBe('Une menace intérieure');
    expect($zoneNames[28])->toBe('Nettoyage du camp Kobold');
});
