<?php

declare(strict_types=1);

use App\Infrastructure\Reference\ReferenceCatalog;
use App\Infrastructure\Reference\ReferenceTable;
use Illuminate\Support\Facades\Schema;

test('every declared table exists in the database', function (): void {
    foreach ((new ReferenceCatalog)->tables() as $referenceTable) {
        expect(Schema::hasTable($referenceTable->table()))->toBeTrue($referenceTable->table());
    }
});

test('every declared column exists in its table', function (): void {
    foreach ((new ReferenceCatalog)->tables() as $referenceTable) {
        expect(Schema::hasColumns($referenceTable->table(), $referenceTable->targetColumns()))
            ->toBeTrue($referenceTable->table().': '.implode(', ', $referenceTable->targetColumns()));
    }
});

test('a table is identified by its source name and by its slug alone', function (): void {
    $tables = (new ReferenceCatalog)->tables();

    $sources = array_map(static fn (ReferenceTable $referenceTable): string => $referenceTable->source, $tables);
    $slugs = array_map(static fn (ReferenceTable $referenceTable): string => $referenceTable->slug, $tables);

    expect(array_unique($sources))->toHaveCount(count($tables))
        ->and(array_unique($slugs))->toHaveCount(count($tables));
});

test('every table carries the DB2 identifier as its first column', function (): void {
    foreach ((new ReferenceCatalog)->tables() as $referenceTable) {
        expect($referenceTable->columns[0]->source)->toBe('ID')
            ->and($referenceTable->columns[0]->target)->toBe('id');
    }
});

test('an unknown source resolves to nothing rather than to a wrong table', function (): void {
    expect((new ReferenceCatalog)->find('Inconnue'))->toBeNull();
});
