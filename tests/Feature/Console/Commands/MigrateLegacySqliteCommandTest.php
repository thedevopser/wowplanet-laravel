<?php

declare(strict_types=1);

use App\Models\CharacterFavorite;
use App\Models\CharacterTask;
use App\Models\CharacterVisit;
use App\Models\CrossCharacterData;
use App\Models\WowProfession;
use App\Models\WowQuest;
use App\Models\WowRecipe;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * The legacy database is a throwaway SQLite file built by the very migrations the
 * application ships, never the real one: the command has to be exercised against a
 * schema it will actually meet in production.
 */
beforeEach(function (): void {
    $this->legacyPath = sys_get_temp_dir().'/pest-legacy-'.uniqid().'.sqlite';
    touch($this->legacyPath);
    config(['database.connections.sqlite_legacy.database' => $this->legacyPath]);
    DB::purge('sqlite_legacy');

    Artisan::call('migrate', ['--database' => 'sqlite_legacy', '--force' => true]);
});

afterEach(function (): void {
    DB::purge('sqlite_legacy');

    if (is_string($this->legacyPath) && file_exists($this->legacyPath)) {
        unlink($this->legacyPath);
    }
});

/**
 * @param  list<array<string, scalar|null>>  $rows
 */
function seedLegacy(string $table, array $rows): void
{
    DB::connection('sqlite_legacy')->table($table)->insert($rows);
}

function legacyCatalogue(): void
{
    seedLegacy('wow_professions', [
        ['id' => 164, 'name_fr' => 'Forge', 'type' => 'primary', 'is_active' => true, 'max_skill_levels' => '[]'],
        ['id' => 171, 'name_fr' => 'Alchimie', 'type' => 'primary', 'is_active' => true, 'max_skill_levels' => null],
    ]);
    seedLegacy('wow_recipes', [
        ['id' => 900, 'name_fr' => 'Lingot', 'profession_id' => 164, 'expansion_id' => 10, 'is_active' => true],
        ['id' => 901, 'name_fr' => 'Potion', 'profession_id' => 171, 'expansion_id' => 9, 'is_active' => false],
    ]);
    seedLegacy('wow_quests', [
        ['id' => 500, 'name_fr' => 'Bouclier des Anciens', 'expansion_id' => 10, 'is_active' => true],
    ]);
}

function legacyUserData(): void
{
    seedLegacy('character_visits', [
        ['id' => 7, 'realm_slug' => 'hyjal', 'character_name' => 'thrall', 'last_visited_at' => '2026-01-01 10:00:00'],
    ]);
    seedLegacy('character_favorites', [
        ['id' => 3, 'bnet_user_id' => '42', 'realm_slug' => 'hyjal', 'character_name' => 'thrall', 'sort_order' => 0],
    ]);
    seedLegacy('character_tasks', [
        ['id' => 11, 'bnet_user_id' => '42', 'realm_slug' => 'hyjal', 'character_name' => 'thrall', 'name' => 'Coffre', 'reset_type' => 'weekly', 'is_completed' => false, 'sort_order' => 0],
    ]);
    seedLegacy('cross_character_data', [
        ['bnet_user_id' => '42', 'data' => '{"score":10}', 'character_count' => 1, 'fetched_at' => null],
    ]);
}

test('it transfers the catalogue and the user data', function (): void {
    legacyCatalogue();
    legacyUserData();

    $this->artisan('app:migrate-legacy-sqlite')->assertSuccessful();

    expect(WowProfession::query()->count())->toBe(2)
        ->and(WowRecipe::query()->count())->toBe(2)
        ->and(WowQuest::query()->count())->toBe(1)
        ->and(CharacterVisit::query()->count())->toBe(1)
        ->and(CharacterFavorite::query()->count())->toBe(1)
        ->and(CharacterTask::query()->count())->toBe(1)
        ->and(CrossCharacterData::query()->count())->toBe(1);
});

test('it preserves values through the transfer', function (): void {
    legacyCatalogue();
    legacyUserData();

    $this->artisan('app:migrate-legacy-sqlite')->assertSuccessful();

    $profession = WowProfession::query()->find(164);
    $inactiveRecipe = WowRecipe::query()->find(901);
    $crossCharacter = CrossCharacterData::query()->find('42');

    expect($profession->name_fr)->toBe('Forge')
        ->and($profession->max_skill_levels)->toBe([])
        ->and($profession->is_active)->toBeTrue()
        ->and($inactiveRecipe->is_active)->toBeFalse()
        ->and($crossCharacter->data)->toBe(['score' => 10])
        ->and($crossCharacter->character_count)->toBe(1);
});

test('it can be replayed without duplicating or breaking anything', function (): void {
    legacyCatalogue();
    legacyUserData();

    $this->artisan('app:migrate-legacy-sqlite')->assertSuccessful();
    $this->artisan('app:migrate-legacy-sqlite')->assertSuccessful();

    expect(WowProfession::query()->count())->toBe(2)
        ->and(WowRecipe::query()->count())->toBe(2)
        ->and(CharacterFavorite::query()->count())->toBe(1)
        ->and(CrossCharacterData::query()->count())->toBe(1);
});

test('dry run reports the source volumes without writing anything', function (): void {
    legacyCatalogue();

    $this->artisan('app:migrate-legacy-sqlite', ['--dry-run' => true])
        ->expectsOutputToContain('wow_professions')
        ->assertSuccessful();

    expect(WowProfession::query()->count())->toBe(0)
        ->and(WowRecipe::query()->count())->toBe(0)
        ->and(WowQuest::query()->count())->toBe(0);
});

/**
 * Auto-incremented ids are carried over as they stand, so the sequence stays at 1
 * unless it is repositioned. Without this the first insert after the switch would
 * collide with a row that came from SQLite.
 */
test('it repositions the sequences of auto-incremented tables', function (): void {
    legacyUserData();

    $this->artisan('app:migrate-legacy-sqlite')->assertSuccessful();

    $characterFavorite = CharacterFavorite::query()->create([
        'bnet_user_id' => '99',
        'realm_slug' => 'dalaran',
        'character_name' => 'jaina',
        'sort_order' => 0,
    ]);

    expect($characterFavorite->id)->toBeGreaterThan(3);
});

test('tables holding cache, sessions and queue are left behind', function (): void {
    seedLegacy('sessions', [
        ['id' => 'abc', 'ip_address' => '127.0.0.1', 'user_agent' => 'pest', 'payload' => 'x', 'last_activity' => 1],
    ]);
    seedLegacy('cache', [
        ['key' => 'k', 'value' => 'v', 'expiration' => 1],
    ]);

    $this->artisan('app:migrate-legacy-sqlite')->assertSuccessful();

    expect(DB::table('sessions')->count())->toBe(0)
        ->and(DB::table('cache')->count())->toBe(0);
});

/**
 * A partial transfer that reports success is the worst possible outcome: a row the
 * target refuses has to surface as a failure, not as a silent gap.
 */
test('it fails with a non-zero exit code when a row cannot be transferred', function (): void {
    seedLegacy('wow_recipes', [
        ['id' => 902, 'name_fr' => 'Orpheline', 'profession_id' => 999999, 'expansion_id' => 10, 'is_active' => true],
    ]);

    $this->artisan('app:migrate-legacy-sqlite')->assertFailed();

    expect(WowRecipe::query()->count())->toBe(0);
});
