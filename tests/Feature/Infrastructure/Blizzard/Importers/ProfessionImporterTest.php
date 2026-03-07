<?php

declare(strict_types=1);

use App\Infrastructure\Blizzard\Importers\ProfessionImporter;
use App\Models\WowProfession;
use App\Models\WowRecipe;

beforeEach(function (): void {
    $dir = storage_path('app/blizzard');
    if (! is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $this->testFiles = ['skill_line.csv', 'skill_line_ability.csv', 'trade_skill_category.csv'];
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

    foreach ($this->backups as $path => $backup) {
        if (file_exists($backup)) {
            rename($backup, $path);
        }
    }
});

test('it imports professions and recipes', function (): void {
    writeProfSkillLineCsv([
        ['164', 'Forge', '11', '0'],
    ]);
    writeProfSkillLineAbilityCsv([
        ['5001', '164', '80001', '100'],
        ['5002', '164', '80002', '100'],
    ]);
    writeProfTradeSkillCategoryCsv([
        ['300', 'Forge de Khaz Algar', '0'],
        ['200', 'Recettes de Khaz Algar', '300'],
        ['100', 'Armures', '200'],
    ]);

    $spellNameMap = [
        80001 => 'Plastron en acier',
        80002 => 'Jambiere en fer',
    ];

    $professionImporter = resolve(ProfessionImporter::class);
    $professionImporter->import($spellNameMap);

    expect(WowProfession::query()->count())->toBe(1);
    expect(WowProfession::query()->find(164)->name_fr)->toBe('Forge');
    expect(WowProfession::query()->find(164)->type)->toBe('primary');

    expect(WowRecipe::query()->count())->toBe(2);
    expect(WowRecipe::query()->find(5001)->name_fr)->toBe('Plastron en acier');
    expect(WowRecipe::query()->find(5001)->profession_id)->toBe(164);
    expect(WowRecipe::query()->find(5001)->expansion_id)->toBe(10); // Khaz Algar = TWW
    expect(WowRecipe::query()->find(5001)->category_name)->toBe('Armures');
    expect(WowRecipe::query()->find(5001)->wowhead_spell_id)->toBe(80001);
    expect(WowRecipe::query()->find(5002)->name_fr)->toBe('Jambiere en fer');
});

test('it returns early when no professions found', function (): void {
    writeProfSkillLineCsv([]);
    writeProfSkillLineAbilityCsv([]);
    writeProfTradeSkillCategoryCsv([]);

    $professionImporter = resolve(ProfessionImporter::class);
    $professionImporter->import([]);

    expect(WowProfession::query()->count())->toBe(0);
    expect(WowRecipe::query()->count())->toBe(0);
});

test('it tags mirror recipe factions', function (): void {
    $profession = WowProfession::factory()->create(['id' => 171]);

    WowRecipe::factory()->create([
        'id' => 5001,
        'name_fr' => 'Potion speciale',
        'profession_id' => 171,
        'expansion_id' => 7,
        'faction' => 'Alliance',
        'is_active' => true,
    ]);
    WowRecipe::factory()->create([
        'id' => 5002,
        'name_fr' => 'Potion speciale',
        'profession_id' => 171,
        'expansion_id' => 7,
        'faction' => null,
        'is_active' => true,
    ]);

    $professionImporter = resolve(ProfessionImporter::class);
    $professionImporter->tagMirrorRecipeFactions();

    expect(WowRecipe::query()->find(5002)->faction)->toBe('Horde');
});

// ─── Helpers ────────────────────────────────────────────────

function writeProfSkillLineCsv(array $rows): void
{
    $lines = ['ID,DisplayName_lang,CategoryID,ParentSkillLineID'];
    foreach ($rows as $row) {
        $lines[] = sprintf('"%s","%s","%s","%s"', $row[0], $row[1], $row[2], $row[3]);
    }

    file_put_contents(storage_path('app/blizzard/skill_line.csv'), implode("\n", $lines));
}

function writeProfSkillLineAbilityCsv(array $rows): void
{
    $lines = ['ID,SkillLine,Spell,TradeSkillCategoryID'];
    foreach ($rows as $row) {
        $lines[] = sprintf('"%s","%s","%s","%s"', $row[0], $row[1], $row[2], $row[3]);
    }

    file_put_contents(storage_path('app/blizzard/skill_line_ability.csv'), implode("\n", $lines));
}

function writeProfTradeSkillCategoryCsv(array $rows): void
{
    $lines = ['ID,Name_lang,ParentTradeSkillCategoryID'];
    foreach ($rows as $row) {
        $parent = $row[2] ?? '0';
        $lines[] = sprintf('"%s","%s","%s"', $row[0], $row[1], $parent);
    }

    file_put_contents(storage_path('app/blizzard/trade_skill_category.csv'), implode("\n", $lines));
}
