<?php

declare(strict_types=1);

namespace App\Infrastructure\Blizzard\Importers;

use App\Infrastructure\Blizzard\Support\Db2CsvLoader;
use App\Infrastructure\Parsers\SimpleArmoryParser;
use App\Models\WowDecor;
use Illuminate\Support\Facades\Log;

final class DecorImporter
{
    public function import(): void
    {
        $saDecors = $this->loadSimpleArmoryData();
        if ($saDecors === []) {
            return;
        }

        $frenchNames = $this->loadFrenchNames();
        $rows = $this->buildRows($saDecors, $frenchNames);

        $this->saveRows($rows);
    }

    /**
     * @return array<int, array{category: string, source: string, icon: string|null, faction: string|null, spellid: int, creatureId: int, itemId: int|null, notObtainable: bool}>
     */
    private function loadSimpleArmoryData(): array
    {
        $this->info('Parsing SimpleArmory decors.json...');

        $decors = SimpleArmoryParser::parseCollection('decors.json');
        if ($decors === []) {
            $this->info('ERROR: Could not parse decors.json.');

            return [];
        }

        $this->info(sprintf('  Found %d decors.', count($decors)));

        return $decors;
    }

    /**
     * @return array<int, string>
     */
    private function loadFrenchNames(): array
    {
        $this->info('Loading French names from housetdecor.csv...');

        $names = Db2CsvLoader::loadStringMapByHeaders('housetdecor.csv', 'ID', 'Name_lang');
        $this->info(sprintf('  Found %d French names.', count($names)));

        return $names;
    }

    /**
     * @param  array<int, array{category: string, source: string, icon: string|null, faction: string|null, spellid: int, creatureId: int, itemId: int|null, notObtainable: bool}>  $saDecors
     * @param  array<int, string>  $frenchNames
     * @return list<array{id: int, name_fr: string, category: string|null, source: string|null, item_id: int|null, icon_url: string|null, is_active: bool}>
     */
    private function buildRows(array $saDecors, array $frenchNames): array
    {
        $rows = [];
        $matched = 0;
        $fallbacks = 0;
        $inactive = 0;

        foreach ($saDecors as $id => $decor) {
            $nameFr = $frenchNames[$id] ?? null;
            if ($nameFr !== null) {
                $matched++;
            } else {
                $fallbacks++;
            }

            $isActive = ! $decor['notObtainable'];
            if (! $isActive) {
                $inactive++;
            }

            $iconUrl = $decor['icon'] !== null ? SimpleArmoryParser::buildIconUrl($decor['icon']) : null;

            $rows[] = [
                'id' => $id,
                'name_fr' => $nameFr ?? sprintf('[EN] Decor #%d', $id),
                'category' => $decor['category'] !== '' ? $decor['category'] : null,
                'source' => $decor['source'] !== '' ? $decor['source'] : null,
                'item_id' => $decor['itemId'],
                'icon_url' => $iconUrl,
                'is_active' => $isActive,
            ];
        }

        $this->info(sprintf('  %d matched with French name, %d using English fallback, %d not obtainable.', $matched, $fallbacks, $inactive));

        return $rows;
    }

    /**
     * @param  list<array{id: int, name_fr: string, category: string|null, source: string|null, item_id: int|null, icon_url: string|null, is_active: bool}>  $rows
     */
    private function saveRows(array $rows): void
    {
        $this->info(sprintf('Saving %d decors...', count($rows)));

        $count = 0;
        foreach (array_chunk($rows, 500) as $chunk) {
            WowDecor::query()->upsert(
                $chunk,
                uniqueBy: ['id'],
                update: ['name_fr', 'category', 'source', 'item_id', 'icon_url', 'is_active'],
            );
            $count += count($chunk);
            $this->info(sprintf('  Saved %d...', $count));
        }

        $this->info(sprintf('Decor import complete: %d items (%d active).', $count, count(array_filter($rows, fn (array $r): bool => $r['is_active']))));
    }

    private function info(string $message): void
    {
        if (app()->runningInConsole()) {
            echo $message.PHP_EOL;
        }

        Log::info($message);
    }
}
