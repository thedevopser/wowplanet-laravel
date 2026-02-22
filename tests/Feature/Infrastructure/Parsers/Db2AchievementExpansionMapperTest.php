<?php

declare(strict_types=1);

use App\Infrastructure\Parsers\Db2AchievementExpansionMapper;

beforeEach(function (): void {
    $this->mapper = new Db2AchievementExpansionMapper;

    $dir = storage_path('app/blizzard');
    if (! is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    // Back up real files to avoid interference with test data
    $this->backups = [];
    foreach (['achievement.csv', 'achievement_category.csv', 'map.csv', 'area_table.csv', 'criteria_tree.csv'] as $file) {
        $path = $dir.'/'.$file;
        if (file_exists($path)) {
            $backup = $path.'.testbak';
            rename($path, $backup);
            $this->backups[$path] = $backup;
        }
    }
});

afterEach(function (): void {
    foreach (['achievement.csv', 'achievement_category.csv', 'map.csv', 'area_table.csv', 'criteria_tree.csv'] as $file) {
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

// ─── build() ────────────────────────────────────────────────

test('build returns empty when CSV files missing', function (): void {
    expect($this->mapper->build())->toBe([]);
});

test('build resolves expansion from category name via ExpansionTierMatcher', function (): void {
    achWriteCategoryCsv([
        ['92', 'Quêtes', '-1'],
        ['14861', 'Norfendre', '92'],
    ]);
    achWriteAchievementCsv([
        ['100', '14861', '0'],
    ]);

    $map = $this->mapper->build();

    expect($map)->toHaveCount(1);
    expect($map[100])->toBe(2);
});

test('build walks up category hierarchy to find expansion', function (): void {
    achWriteCategoryCsv([
        ['92', 'Quêtes', '-1'],
        ['14861', 'Norfendre', '92'],
        ['14862', 'Fjord Hurlant', '14861'],
    ]);
    achWriteAchievementCsv([
        ['200', '14862', '0'],
    ]);

    $map = $this->mapper->build();

    expect($map[200])->toBe(2);
});

test('build resolves dungeon/raid achievements via Instance_ID and Map', function (): void {
    achWriteCategoryCsv([
        ['168', 'Donjons et raids', '-1'],
    ]);
    achWriteAchievementCsv([
        ['300', '168', '269'],
    ]);
    achWriteMapCsv([
        ['269', '2'],
    ]);

    $map = $this->mapper->build();

    expect($map[300])->toBe(2);
});

test('build prioritizes Instance_ID over category hierarchy', function (): void {
    achWriteCategoryCsv([
        ['168', 'Donjons et raids', '-1'],
        ['15272', 'Dragonflight', '168'],
    ]);
    achWriteAchievementCsv([
        ['400', '15272', '269'],
    ]);
    achWriteMapCsv([
        ['269', '2'],
    ]);

    $map = $this->mapper->build();

    expect($map[400])->toBe(2);
});

test('build excludes unresolvable achievements from result', function (): void {
    achWriteCategoryCsv([
        ['1', 'Général', '-1'],
    ]);
    achWriteAchievementCsv([
        ['500', '1', '0'],
    ]);

    $map = $this->mapper->build();

    expect($map)->not->toHaveKey(500);
});

test('build handles multiple achievements across expansions', function (): void {
    achWriteCategoryCsv([
        ['92', 'Quêtes', '-1'],
        ['14861', 'Norfendre', '92'],
        ['15271', 'Dragon Isles', '92'],
        ['15272', 'Khaz Algar', '92'],
    ]);
    achWriteAchievementCsv([
        ['100', '14861', '0'],
        ['200', '15271', '0'],
        ['300', '15272', '0'],
    ]);

    $map = $this->mapper->build();

    expect($map[100])->toBe(2);
    expect($map[200])->toBe(9);
    expect($map[300])->toBe(10);
});

test('build handles circular parent references without infinite loop', function (): void {
    achWriteCategoryCsv([
        ['1', 'Cat A', '2'],
        ['2', 'Cat B', '1'],
    ]);
    achWriteAchievementCsv([
        ['100', '1', '0'],
    ]);

    $map = $this->mapper->build();

    expect($map)->not->toHaveKey(100);
});

test('build handles unknown category gracefully', function (): void {
    achWriteCategoryCsv([
        ['1', 'Général', '-1'],
    ]);
    achWriteAchievementCsv([
        ['100', '9999', '0'],
    ]);

    $map = $this->mapper->build();

    expect($map)->not->toHaveKey(100);
});

test('build resolves via CATEGORY_EXPANSION_MAP override', function (): void {
    achWriteCategoryCsv([
        ['15301', "Contenu d'extension", '-1'],
        ['15440', 'Tourment', '15301'],
    ]);
    achWriteAchievementCsv([
        ['600', '15440', '0'],
    ]);

    $map = $this->mapper->build();

    expect($map[600])->toBe(8);
});

test('build resolves child category via parent CATEGORY_EXPANSION_MAP', function (): void {
    achWriteCategoryCsv([
        ['15301', "Contenu d'extension", '-1'],
        ['15440', 'Tourment', '15301'],
        ['99999', 'Étage spécial', '15440'],
    ]);
    achWriteAchievementCsv([
        ['700', '99999', '0'],
    ]);

    $map = $this->mapper->build();

    expect($map[700])->toBe(8);
});

test('build resolves mix of resolved and unresolved achievements', function (): void {
    achWriteCategoryCsv([
        ['92', 'Personnages', '-1'],
        ['15301', "Contenu d'extension", '-1'],
        ['15441', 'Sanctums des congrégations', '15301'],
        ['96', 'Quêtes', '-1'],
        ['14862', 'Outreterre', '96'],
    ]);
    achWriteAchievementCsv([
        ['100', '92', '0'],      // generic → excluded
        ['200', '15441', '0'],    // category map → 8
        ['300', '14862', '0'],    // keyword → 1
    ]);

    $map = $this->mapper->build();

    expect($map)->not->toHaveKey(100);
    expect($map[200])->toBe(8);
    expect($map[300])->toBe(1);
});

// ─── Area-name matching for multi-expansion categories ──────

test('build resolves multi-expansion category via area-name in description', function (): void {
    achWriteCategoryCsv([
        ['15301', "Contenu d'extension", '-1'],
        ['15462', 'Vol dynamique', '15301'],
    ]);
    // TWW zone "Dornogal" in description → should resolve to TWW (10)
    achWriteAchievementCsvFull([
        ['800', '15462', '0', 'Derby de Dornogal – Bronze', 'Terminer le Derby de Dornogal.'],
    ]);
    achWriteMapCsv([
        ['2552', '10'], // Khaz Algar continent → TWW
    ]);
    achWriteAreaTableCsv([
        ['15006', 'Dornogal', '2552'],
    ]);

    $map = $this->mapper->build();

    expect($map[800])->toBe(10);
});

test('build area-name matching uses title when description is empty', function (): void {
    achWriteCategoryCsv([
        ['15301', "Contenu d'extension", '-1'],
        ['15462', 'Vol dynamique', '15301'],
    ]);
    achWriteAchievementCsvFull([
        ['900', '15462', '0', "Glyphes de vol dynamique : Lune-d'Argent", ''],
    ]);
    achWriteMapCsv([
        ['2601', '11'], // Midnight continent
    ]);
    achWriteAreaTableCsv([
        ['15100', "Lune-d'Argent", '2601'],
    ]);

    $map = $this->mapper->build();

    expect($map[900])->toBe(11);
});

test('build falls back to CATEGORY_EXPANSION_MAP when area-name does not match', function (): void {
    achWriteCategoryCsv([
        ['15301', "Contenu d'extension", '-1'],
        ['15462', 'Vol dynamique', '15301'],
    ]);
    // Old-world race with no matching modern area name → fallback to 15462 → 9 (DF)
    achWriteAchievementCsvFull([
        ['1000', '15462', '0', 'Balade hivernale – Bronze', 'Terminer la Balade hivernale en Kalimdor.'],
    ]);

    $map = $this->mapper->build();

    expect($map[1000])->toBe(9);
});

test('build area-name matching ignores old-expansion areas', function (): void {
    achWriteCategoryCsv([
        ['15301', "Contenu d'extension", '-1'],
        ['15462', 'Vol dynamique', '15301'],
    ]);
    achWriteAchievementCsvFull([
        ['1100', '15462', '0', 'Course en Kalimdor', 'Terminer la course en Kalimdor.'],
    ]);
    achWriteMapCsv([
        ['1', '0'], // Kalimdor continent → Classic
    ]);
    achWriteAreaTableCsv([
        ['17', 'Kalimdor', '1'],
    ]);

    $map = $this->mapper->build();

    // Kalimdor is Classic (exp 0, < 9) → not matched → falls to 15462 → 9 (DF)
    expect($map[1100])->toBe(9);
});

test('build resolves non-multi-expansion categories via text fallback', function (): void {
    achWriteCategoryCsv([
        ['1', 'Général', '-1'],
    ]);
    // Achievement in generic category with modern zone in text → resolved via fallback
    achWriteAchievementCsvFull([
        ['1200', '1', '0', 'Something in Dornogal', 'Do something in Dornogal.'],
    ]);
    achWriteMapCsv([
        ['2552', '10'],
    ]);
    achWriteAreaTableCsv([
        ['15006', 'Dornogal', '2552'],
    ]);

    $map = $this->mapper->build();

    expect($map[1200])->toBe(10);
});

test('build area-name matching prefers longer zone name matches', function (): void {
    achWriteCategoryCsv([
        ['15301', "Contenu d'extension", '-1'],
        ['15462', 'Vol dynamique', '15301'],
    ]);
    achWriteAchievementCsvFull([
        ['1300', '15462', '0', 'Course cité des Fils', 'Terminer la course de la cité des Fils.'],
    ]);
    achWriteMapCsv([
        ['2552', '10'], // TWW continent
        ['2601', '11'], // Midnight continent
    ]);
    achWriteAreaTableCsv([
        ['15050', 'cité des Fils', '2552'], // TWW
        ['15051', 'Fils', '2601'],           // Midnight (shorter match)
    ]);

    $map = $this->mapper->build();

    // "cité des Fils" (longer) should match before "Fils" (shorter)
    expect($map[1300])->toBe(10);
});

test('build resolves via ExpansionTierMatcher on description text', function (): void {
    achWriteCategoryCsv([
        ['15117', 'Combats de mascottes', '-1'],
    ]);
    achWriteAchievementCsvFull([
        ['1400', '15117', '0', 'Maître de Norfendre', 'Vaincre tous les dresseurs de Norfendre.'],
    ]);

    $map = $this->mapper->build();

    expect($map[1400])->toBe(2);
});

test('build text matching does not resolve truly generic achievements', function (): void {
    achWriteCategoryCsv([
        ['92', 'Personnages', '-1'],
    ]);
    achWriteAchievementCsvFull([
        ['1500', '92', '0', 'Niveau 10', 'Atteindre le niveau 10.'],
    ]);

    $map = $this->mapper->build();

    expect($map)->not->toHaveKey(1500);
});

// ─── PvP battleground category mapping ──────────────────────

test('build resolves PvP battleground category to correct expansion', function (): void {
    achWriteCategoryCsv([
        ['95', 'Joueur contre Joueur', '-1'],
        ['14801', 'Vallée d\'Alterac', '95'],
        ['14803', 'Œil du cyclone', '95'],
    ]);
    achWriteAchievementCsvFull([
        ['1600', '14801', '0', 'Maître d\'Alterac', 'Gagner Alterac 100 fois.'],
        ['1601', '14803', '0', 'Cyclone parfait', 'Gagner Œil du cyclone.'],
    ]);

    $map = $this->mapper->build();

    expect($map[1600])->toBe(0); // Alterac → Classic
    expect($map[1601])->toBe(1); // Œil du cyclone → TBC
});

// ─── Holiday event category mapping ─────────────────────────

test('build resolves holiday event category to WotLK', function (): void {
    achWriteCategoryCsv([
        ['155', 'Évènements mondiaux', '-1'],
        ['156', "Voile d'hiver", '155'],
    ]);
    achWriteAchievementCsvFull([
        ['1700', '156', '0', 'Simplement abominable', 'Sauver le Grinche.'],
    ]);

    $map = $this->mapper->build();

    expect($map[1700])->toBe(2); // Voile d'hiver → WotLK
});

// ─── New ExpansionTierMatcher keywords ──────────────────────

test('build resolves covenant keywords in description', function (): void {
    achWriteCategoryCsv([
        ['81', 'Tours de force', '-1'],
    ]);
    achWriteAchievementCsvFull([
        ['1800', '81', '0', 'Renommée Venthyr', 'Atteindre le renom maximal chez les Venthyr.'],
    ]);

    $map = $this->mapper->build();

    expect($map[1800])->toBe(8); // Venthyr → Shadowlands
});

test('build resolves raid name keyword in title', function (): void {
    achWriteCategoryCsv([
        ['81', 'Tours de force', '-1'],
    ]);
    achWriteAchievementCsvFull([
        ['1801', '81', '0', 'Progression : Aberrus', "Vaincre tous les boss d'Aberrus."],
    ]);

    $map = $this->mapper->build();

    expect($map[1801])->toBe(9); // Aberrus → Dragonflight
});

// ─── Supercedes chain propagation ───────────────────────────

test('build resolves unmapped achievement via supercedes chain', function (): void {
    achWriteCategoryCsv([
        ['92', 'Personnages', '-1'],
        ['14861', 'Norfendre', '92'],
    ]);
    // Achievement 1901 supercedes 1900, and 1900 is in Norfendre (WotLK)
    achWriteAchievementCsvFull([
        ['1900', '14861', '0', 'Quêtes Norfendre I', 'Achever 50 quêtes.', '0', '0'],
        ['1901', '92', '0', 'Quêtes Norfendre II', 'Achever 100 quêtes.', '1900', '0'],
    ]);

    $map = $this->mapper->build();

    expect($map[1900])->toBe(2); // Direct category resolution
    expect($map[1901])->toBe(2); // Via supercedes chain
});

test('build supercedes chain handles missing target', function (): void {
    achWriteCategoryCsv([
        ['92', 'Personnages', '-1'],
    ]);
    // Supercedes ID 9999 which does not exist
    achWriteAchievementCsvFull([
        ['2000', '92', '0', 'Quelque chose', 'Description.', '9999', '0'],
    ]);

    $map = $this->mapper->build();

    expect($map)->not->toHaveKey(2000);
});

// ─── CriteriaTree resolution ────────────────────────────────

test('build resolves via criteria tree descriptions', function (): void {
    achWriteCategoryCsv([
        ['81', 'Tours de force', '-1'],
    ]);
    // Achievement with criteria_tree pointing to nodes mentioning "Norfendre"
    achWriteAchievementCsvFull([
        ['2100', '81', '0', 'Cutting Edge', 'Vaincre le boss final.', '0', '5000'],
    ]);
    achWriteCriteriaTreeCsv([
        ['5000', 'Vaincre le boss', '0'],
        ['5001', 'Vaincre Kel\'Thuzad à Naxxramas en Norfendre', '5000'],
    ]);

    $map = $this->mapper->build();

    expect($map[2100])->toBe(2); // Norfendre → WotLK
});

test('build criteria tree handles empty criteria_tree id', function (): void {
    achWriteCategoryCsv([
        ['92', 'Personnages', '-1'],
    ]);
    achWriteAchievementCsvFull([
        ['2200', '92', '0', 'Generic', 'No criteria.', '0', '0'],
    ]);

    $map = $this->mapper->build();

    expect($map)->not->toHaveKey(2200);
});

test('build criteria tree handles missing criteria_tree.csv', function (): void {
    achWriteCategoryCsv([
        ['81', 'Tours de force', '-1'],
    ]);
    achWriteAchievementCsvFull([
        ['2300', '81', '0', 'Something', 'Description.', '0', '5000'],
    ]);
    // No criteria_tree.csv written

    $map = $this->mapper->build();

    expect($map)->not->toHaveKey(2300);
});

test('build criteria tree matches expansion keywords in descendant descriptions', function (): void {
    achWriteCategoryCsv([
        ['81', 'Tours de force', '-1'],
        ['15271', 'Raids', '81'],
    ]);
    achWriteAchievementCsvFull([
        ['2400', '15271', '0', 'Ahead of the Curve', 'Vaincre le boss.', '0', '6000'],
    ]);
    achWriteCriteriaTreeCsv([
        ['6000', '', '0'],
        ['6001', 'Vaincre Sire Denathrius', '6000'],
        ['6002', 'Château Nathria mode héroïque', '6001'],
    ]);

    $map = $this->mapper->build();

    expect($map[2400])->toBe(8); // Château Nathria → Shadowlands
});

// ─── Midnight category and area fixes ────────────────────────

test('build resolves Midnight Gouffres category to Midnight', function (): void {
    achWriteCategoryCsv([
        ['15522', 'Gouffres', '-1'],
        ['15571', 'Midnight', '15522'],
    ]);
    achWriteAchievementCsvFull([
        ['2500', '15571', '0', 'Adepte des profondeurs : Midnight', 'Terminer les Gouffres de Midnight.'],
    ]);

    $map = $this->mapper->build();

    expect($map[2500])->toBe(11); // Midnight Gouffres → Midnight
});

test('build resolves Logis category to Midnight', function (): void {
    achWriteCategoryCsv([
        ['15606', 'Logis', '-1'],
    ]);
    achWriteAchievementCsvFull([
        ['2600', '15606', '0', 'Décorateur', 'Placez 100 décorations.'],
    ]);

    $map = $this->mapper->build();

    expect($map[2600])->toBe(11); // Logis → Midnight
});

test('build resolves Midnight flight glyph via MAP_EXPANSION_OVERRIDES for missing continent', function (): void {
    achWriteCategoryCsv([
        ['15301', "Contenu d'extension", '-1'],
        ['15462', 'Vol dynamique', '15301'],
    ]);
    achWriteAchievementCsvFull([
        ['2700', '15462', '0', 'Glyphes de vol dynamique : travée Rayonnante', 'Obtenir le glyphe dans le bois des Chants éternels.'],
    ]);
    // Map 2711 NOT in CSV (missing from real DB2 data) — MAP_EXPANSION_OVERRIDES injects it as 11
    achWriteMapCsv([]);
    achWriteAreaTableCsv([
        ['15173', 'Bois des Chants éternels', '2711'],
    ]);

    $map = $this->mapper->build();

    expect($map[2700])->toBe(11);
});

test('build resolves Zul Aman flight glyph via AREA_ID_EXPANSION_OVERRIDES', function (): void {
    achWriteCategoryCsv([
        ['15301', "Contenu d'extension", '-1'],
        ['15462', 'Vol dynamique', '15301'],
    ]);
    achWriteAchievementCsvFull([
        ['2800', '15462', '0', "Glyphes de vol dynamique : guet d'Ombre-Bassin", "Obtenir le glyphe à Zul'Aman."],
    ]);
    achWriteMapCsv([
        ['0', '0'], // Eastern Kingdoms → Classic
    ]);
    achWriteAreaTableCsv([
        // Area 15947 has ContinentID 0 but AREA_ID_EXPANSION_OVERRIDES says 11
        ['15947', "Zul'Aman", '0'],
    ]);

    $map = $this->mapper->build();

    expect($map[2800])->toBe(11);
});

// ─── Helpers (prefixed to avoid Pest global conflicts) ──────

function achWriteAchievementCsv(array $rows): void
{
    $lines = ['Description_lang,Title_lang,Reward_lang,ID,Instance_ID,Faction,Supercedes,Category,Minimum_criteria,Points,Flags,Ui_order,IconFileID,RewardItemID,Criteria_tree,Shares_criteria,CovenantID,HiddenBeforeDisplaySeason,LegacyAfterTimeEvent'];
    foreach ($rows as $row) {
        // $row = [ID, Category, Instance_ID]
        $lines[] = sprintf(',,,"%s","%s","-1","0","%s","0","0","0","0","0","0","0","0","0","0","0"', $row[0], $row[2], $row[1]);
    }

    file_put_contents(storage_path('app/blizzard/achievement.csv'), implode("\n", $lines));
}

function achWriteAchievementCsvFull(array $rows): void
{
    $lines = ['Description_lang,Title_lang,Reward_lang,ID,Instance_ID,Faction,Supercedes,Category,Minimum_criteria,Points,Flags,Ui_order,IconFileID,RewardItemID,Criteria_tree,Shares_criteria,CovenantID,HiddenBeforeDisplaySeason,LegacyAfterTimeEvent'];
    foreach ($rows as $row) {
        // $row = [ID, Category, Instance_ID, Title, Description, ?Supercedes, ?Criteria_tree]
        $desc = str_replace('"', '""', $row[4] ?? '');
        $title = str_replace('"', '""', $row[3] ?? '');
        $supercedes = $row[5] ?? '0';
        $criteriaTree = $row[6] ?? '0';
        $lines[] = sprintf('"%s","%s",,"%s","%s","-1","%s","%s","0","0","0","0","0","0","%s","0","0","0","0"', $desc, $title, $row[0], $row[2], $supercedes, $row[1], $criteriaTree);
    }

    file_put_contents(storage_path('app/blizzard/achievement.csv'), implode("\n", $lines));
}

function achWriteCategoryCsv(array $rows): void
{
    $lines = ['Name_lang,ID,Parent,Ui_order'];
    foreach ($rows as $row) {
        // $row = [ID, Name, Parent]
        $lines[] = sprintf('"%s","%s","%s","0"', $row[1], $row[0], $row[2]);
    }

    file_put_contents(storage_path('app/blizzard/achievement_category.csv'), implode("\n", $lines));
}

function achWriteMapCsv(array $rows): void
{
    $lines = ['ID,ExpansionID'];
    foreach ($rows as $row) {
        $lines[] = implode(',', $row);
    }

    file_put_contents(storage_path('app/blizzard/map.csv'), implode("\n", $lines));
}

function achWriteAreaTableCsv(array $rows): void
{
    $lines = ['ID,ZoneName,AreaName_lang,ContinentID,ParentAreaID,AreaBit,SoundProviderPref,SoundProviderPrefUnderwater,AmbienceID,UwAmbience,ZoneMusic,UwZoneMusic,IntroSound,UwIntroSound,FactionGroupMask,Ambient_multiplier,MountFlags,PvpCombatWorldStateID,WildBattlePetLevelMin,WildBattlePetLevelMax,WindSettingsID,ContentTuningID,Flags_0,Flags_1,LiquidTypeID_0,LiquidTypeID_1,LiquidTypeID_2,LiquidTypeID_3'];
    foreach ($rows as $row) {
        // $row = [ID, AreaName_lang, ContinentID]
        $lines[] = sprintf('"%s","","%s","%s","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0"', $row[0], $row[1], $row[2]);
    }

    file_put_contents(storage_path('app/blizzard/area_table.csv'), implode("\n", $lines));
}

function achWriteCriteriaTreeCsv(array $rows): void
{
    $lines = ['ID,Description_lang,Parent,Amount,Operator,CriteriaID,OrderIndex,Flags'];
    foreach ($rows as $row) {
        // $row = [ID, Description_lang, Parent]
        $desc = str_replace('"', '""', $row[1] ?? '');
        $lines[] = sprintf('"%s","%s","%s","0","0","0","0","0"', $row[0], $desc, $row[2]);
    }

    file_put_contents(storage_path('app/blizzard/criteria_tree.csv'), implode("\n", $lines));
}
