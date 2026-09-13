<?php

declare(strict_types=1);

use App\Application\Import\ImportStage;
use App\Models\WowAppearance;
use App\Models\WowProfession;
use App\Models\WowRecipe;

test('the chain starts with the reference socle', function (): void {
    expect(ImportStage::chain()[0])->toBe(ImportStage::Reference);
});

test('the chain holds every stage exactly once', function (): void {
    expect(ImportStage::chain())->toEqualCanonicalizing(ImportStage::cases());
});

test('every stage comes after the stages it depends on', function (): void {
    $seen = [];

    foreach (ImportStage::chain() as $importStage) {
        foreach ($importStage->dependsOn() as $dependency) {
            expect($seen)->toContain($dependency);
        }

        $seen[] = $importStage;
    }
});

test('the stages fed by the reference socle declare it', function (): void {
    expect(ImportStage::Quests->dependsOn())->toBe([ImportStage::Reference])
        ->and(ImportStage::Mounts->dependsOn())->toBe([ImportStage::Reference])
        ->and(ImportStage::Professions->dependsOn())->toBe([ImportStage::Reference])
        ->and(ImportStage::Achievements->dependsOn())->toBe([]);
});

test('a stage names the tables its rows are counted in', function (): void {
    expect(ImportStage::Appearances->tables())->toBe([WowAppearance::class])
        ->and(ImportStage::Professions->tables())->toBe([WowProfession::class, WowRecipe::class]);
});

test('the reference socle counts no rows, its tables carrying no timestamps', function (): void {
    expect(ImportStage::Reference->tables())->toBe([]);
});

test('only the wardrobe sweep resumes from an offset', function (): void {
    $resumable = array_values(array_filter(
        ImportStage::cases(),
        static fn (ImportStage $importStage): bool => $importStage->isResumable(),
    ));

    expect($resumable)->toBe([ImportStage::Appearances]);
});

test('requesting everything runs the whole chain', function (): void {
    expect(ImportStage::requested('all'))->toBe(ImportStage::chain());
});

test('requesting one stage runs that stage alone', function (): void {
    expect(ImportStage::requested('quests'))->toBe([ImportStage::Quests]);
});

test('requesting an unknown stage runs nothing', function (): void {
    expect(ImportStage::requested('dragons'))->toBe([]);
});

test('every stage carries a label for the report', function (): void {
    foreach (ImportStage::cases() as $stage) {
        expect($stage->label())->not->toBe('');
    }
});

test('every stage but the reference socle spends the Blizzard quota', function (): void {
    $offline = array_values(array_filter(
        ImportStage::cases(),
        static fn (ImportStage $importStage): bool => ! $importStage->usesBlizzardApi(),
    ));

    expect($offline)->toBe([ImportStage::Reference]);
});
