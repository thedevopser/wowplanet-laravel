<?php

declare(strict_types=1);

use App\Infrastructure\Taxonomy\ApiSourceTypeVocabulary;

test('every pending source label is already translated by the collection screens', function (string $tab): void {
    $dictionary = (string) file_get_contents(base_path(sprintf('resources/js/components/%s.vue', $tab)));

    $translated = array_filter(
        ApiSourceTypeVocabulary::pendingSources(),
        fn (string $pending): bool => str_contains($dictionary, sprintf("    '%s':", $pending)),
    );

    expect($translated)->not->toBeEmpty();
})->with(['MountsTab', 'PetsTab', 'DecorTab']);

test('no pending source label is left untranslated across the three screens', function (): void {
    $dictionaries = collect(['MountsTab', 'PetsTab', 'DecorTab'])
        ->map(fn (string $tab): string => (string) file_get_contents(base_path(sprintf('resources/js/components/%s.vue', $tab))))
        ->implode("\n");

    foreach (ApiSourceTypeVocabulary::pendingSources() as $pending) {
        expect($dictionaries)->toContain(sprintf("    '%s':", $pending));
    }
});
