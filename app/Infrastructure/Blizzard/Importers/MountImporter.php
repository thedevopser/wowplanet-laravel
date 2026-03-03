<?php

declare(strict_types=1);

namespace App\Infrastructure\Blizzard\Importers;

use App\Infrastructure\Blizzard\Support\Db2CsvLoader;
use App\Infrastructure\Parsers\SimpleArmoryParser;
use App\Models\WowMount;
use Illuminate\Support\Facades\Log;

final class MountImporter
{
    public function import(): void
    {
        $saMounts = $this->loadSimpleArmoryData();
        if ($saMounts === []) {
            return;
        }

        $frenchNames = $this->loadFrenchNames();
        $rows = $this->buildRows($saMounts, $frenchNames);

        $this->saveRows($rows);
    }

    /**
     * @return array<int, array{category: string, source: string, icon: string|null, faction: string|null, spellid: int, creatureId: int, itemId: int|null}>
     */
    private function loadSimpleArmoryData(): array
    {
        $this->info('Parsing SimpleArmory mounts.json...');

        $mounts = SimpleArmoryParser::parseCollection('mounts.json');
        if ($mounts === []) {
            $this->info('ERROR: Could not parse mounts.json.');

            return [];
        }

        $factionCount = count(array_filter($mounts, static fn (array $m): bool => $m['faction'] !== null));
        $this->info(sprintf('  Found %d mounts (%d faction-specific).', count($mounts), $factionCount));

        return $mounts;
    }

    /**
     * @return array<int, string>
     */
    private function loadFrenchNames(): array
    {
        $this->info('Loading French names from mount.csv...');

        $names = Db2CsvLoader::loadStringMapByHeaders('mount.csv', 'ID', 'Name_lang');
        $this->info(sprintf('  Found %d French names.', count($names)));

        return $names;
    }

    /**
     * @param  array<int, array{category: string, source: string, icon: string|null, faction: string|null, spellid: int, creatureId: int, itemId: int|null}>  $saMounts
     * @param  array<int, string>  $frenchNames
     * @return list<array{id: int, name_fr: string, source: string|null, category: string|null, source_spell_id: int|null, icon_url: string|null, is_active: bool}>
     */
    private function buildRows(array $saMounts, array $frenchNames): array
    {
        $rows = [];
        $matched = 0;
        $fallbacks = 0;
        $withIcons = 0;

        foreach ($saMounts as $id => $mount) {
            $nameFr = $frenchNames[$id] ?? null;
            if ($nameFr !== null) {
                $matched++;
            } else {
                $fallbacks++;
            }

            $iconUrl = $mount['icon'] !== null ? SimpleArmoryParser::buildIconUrl($mount['icon']) : null;
            if ($iconUrl !== null) {
                $withIcons++;
            }

            $rows[] = [
                'id' => $id,
                'name_fr' => $nameFr ?? sprintf('[EN] Mount #%d', $id),
                'source' => $mount['source'] !== '' ? $mount['source'] : null,
                'category' => $mount['category'] !== '' ? $mount['category'] : null,
                'source_spell_id' => $mount['spellid'] > 0 ? $mount['spellid'] : null,
                'icon_url' => $iconUrl,
                'is_active' => true,
            ];
        }

        $this->info(sprintf('  %d matched with French name, %d using English fallback.', $matched, $fallbacks));
        $this->info(sprintf('  %d with icon URL.', $withIcons));

        return $rows;
    }

    /**
     * @param  list<array{id: int, name_fr: string, source: string|null, category: string|null, source_spell_id: int|null, icon_url: string|null, is_active: bool}>  $rows
     */
    private function saveRows(array $rows): void
    {
        $this->info(sprintf('Saving %d mounts...', count($rows)));

        $count = 0;
        foreach (array_chunk($rows, 500) as $chunk) {
            WowMount::query()->upsert(
                $chunk,
                uniqueBy: ['id'],
                update: ['name_fr', 'source', 'category', 'source_spell_id', 'icon_url', 'is_active'],
            );
            $count += count($chunk);
            $this->info(sprintf('  Saved %d...', $count));
        }

        $this->info(sprintf('Mount import complete: %d items.', $count));
    }

    private function info(string $message): void
    {
        if (app()->runningInConsole()) {
            echo $message.PHP_EOL;
        }

        Log::info($message);
    }
}
