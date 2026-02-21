<?php

declare(strict_types=1);

namespace App\Infrastructure\Blizzard\Importers;

use App\Infrastructure\Blizzard\BlizzardApiClient;
use App\Infrastructure\Blizzard\Concerns\ImportsFromBlizzardApi;
use App\Models\WowDecor;

class DecorImporter
{
    use ImportsFromBlizzardApi;

    public function __construct(
        private readonly BlizzardApiClient $blizzardApiClient,
    ) {}

    public function import(): void
    {
        $this->info('Fetching Decor Index...');
        $response = $this->fetchWithRetry('data/wow/decor/index');
        if (! $response) {
            $this->info('ERROR: Could not fetch decor index.');

            return;
        }

        /** @var list<array{id: int, name: string|null}> $decors */
        $decors = $response['decor_items'] ?? [];
        $this->info('Found '.count($decors).' decor items.');

        $skipped = 0;
        $count = 0;
        foreach ($decors as $decor) {
            $decorName = $decor['name'] ?? '';
            if ($decorName === '') {
                $skipped++;

                continue;
            }

            WowDecor::query()->updateOrCreate(['id' => $decor['id']], [
                'name_fr' => $decorName,
                'is_active' => true,
            ]);
            $count++;
        }

        if ($skipped > 0) {
            $this->info(sprintf('  Skipped %d decor items with empty names.', $skipped));
        }

        $this->info(sprintf('Decor import complete: %d items.', $count));
    }

    /**
     * @param  array<int, array{category: string, source: string}>  $categoryMap
     */
    public function importCategories(array $categoryMap): void
    {
        $this->info('Enriching decor with categories...');

        $updated = 0;
        $unmatched = 0;

        foreach (WowDecor::all() as $decor) {
            if (isset($categoryMap[$decor->id])) {
                $decor->update([
                    'category' => $categoryMap[$decor->id]['category'],
                    'source' => $categoryMap[$decor->id]['source'],
                ]);
                $updated++;
            } else {
                $unmatched++;
            }
        }

        $this->info(sprintf('Decor categories: %d updated, %d unmatched.', $updated, $unmatched));
    }

    public function importIcons(): void
    {
        $this->info('Fetching decor icons...');

        /** @var \Illuminate\Database\Eloquent\Collection<int, WowDecor> $decors */
        $decors = WowDecor::query()->whereNull('icon_url')->get();
        $this->info(sprintf('  %d decor items need icons.', $decors->count()));
        $count = 0;
        $skipped = 0;

        foreach ($decors as $decor) {
            $this->delayIconRequest();

            $detail = $this->fetchWithRetry('data/wow/decor/'.$decor->id);
            if (! $detail) {
                $skipped++;

                continue;
            }

            /** @var array{id?: int} $items */
            $items = $detail['items'] ?? [];
            $itemId = $items['id'] ?? null;
            if ($itemId === null) {
                $skipped++;

                continue;
            }

            $decor->update(['item_id' => $itemId]);

            $this->delayIconRequest();
            $media = $this->fetchWithRetry('data/wow/media/item/'.$itemId);
            if (! $media) {
                $skipped++;

                continue;
            }

            /** @var list<array{key: string, value: string}> $assets */
            $assets = $media['assets'] ?? [];
            $iconUrl = $assets[0]['value'] ?? null;
            if ($iconUrl) {
                $decor->update(['icon_url' => $iconUrl]);
                $count++;
            }

            if ($count % 100 === 0 && $count > 0) {
                $this->info(sprintf('  Icons fetched: %d / skipped: %d...', $count, $skipped));
            }
        }

        $this->info(sprintf('Decor icon import complete: %d icons, %d skipped.', $count, $skipped));
    }
}
