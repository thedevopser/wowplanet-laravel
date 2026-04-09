<?php

declare(strict_types=1);

namespace App\Application\Services\Progress;

class TalentAggregator
{
    /**
     * @param  array<string, mixed>  $specializationsResponse  From /profile/wow/character/{realm}/{name}/specializations
     * @param  array<string, mixed>  $talentTreeResponse  From /data/wow/talent-tree/{treeId}/playable-specialization/{specId}
     * @return array<string, mixed>
     */
    public function aggregate(array $specializationsResponse, array $talentTreeResponse): array
    {
        $activeLoadout = $this->findActiveLoadout($specializationsResponse);

        /** @var list<array<string, mixed>> $selectedClassRaw */
        $selectedClassRaw = $activeLoadout['selected_class_talents'] ?? [];
        /** @var list<array<string, mixed>> $selectedSpecRaw */
        $selectedSpecRaw = $activeLoadout['selected_spec_talents'] ?? [];
        /** @var list<array<string, mixed>> $selectedHeroRaw */
        $selectedHeroRaw = $activeLoadout['selected_hero_talents'] ?? [];

        $selectedClassTalentIds = $this->buildSelectedMap($selectedClassRaw);
        $selectedSpecTalentIds = $this->buildSelectedMap($selectedSpecRaw);
        $selectedHeroTalentIds = $this->buildSelectedMap($selectedHeroRaw);

        $activeHeroTreeId = $this->extractActiveHeroTreeId($activeLoadout);

        /** @var array{name?: string} $playableClass */
        $playableClass = $talentTreeResponse['playable_class'] ?? [];
        /** @var array{name?: string, id?: int} $playableSpec */
        $playableSpec = $talentTreeResponse['playable_specialization'] ?? [];

        /** @var list<array<string, mixed>> $classNodes */
        $classNodes = $talentTreeResponse['class_talent_nodes'] ?? [];
        /** @var list<array<string, mixed>> $specNodes */
        $specNodes = $talentTreeResponse['spec_talent_nodes'] ?? [];
        /** @var list<array<string, mixed>> $allHeroTrees */
        $allHeroTrees = $talentTreeResponse['hero_talent_trees'] ?? [];

        // Only keep hero trees available to the character's active specialization.
        $activeSpecId = (int) ($playableSpec['id'] ?? 0);
        $heroTrees = $this->filterHeroTreesBySpec($allHeroTrees, $activeSpecId);

        // Blizzard duplicates some hero talent nodes into class/spec trees.
        // Collect all hero node IDs to filter them out.
        $heroNodeIds = $this->collectHeroNodeIds($heroTrees);

        return [
            'spec_name' => (string) ($playableSpec['name'] ?? ''),
            'spec_id' => $activeSpecId,
            'class_name' => (string) ($playableClass['name'] ?? ''),
            'class_nodes' => $this->transformNodes($classNodes, $selectedClassTalentIds, $heroNodeIds),
            'spec_nodes' => $this->transformNodes($specNodes, $selectedSpecTalentIds, $heroNodeIds),
            'hero_trees' => $this->transformHeroTrees($heroTrees, $selectedHeroTalentIds, $activeHeroTreeId),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $heroTrees
     * @return list<array<string, mixed>>
     */
    private function filterHeroTreesBySpec(array $heroTrees, int $specId): array
    {
        if ($specId === 0) {
            return $heroTrees;
        }

        $filtered = [];

        foreach ($heroTrees as $heroTree) {
            /** @var list<array{id?: int}> $specs */
            $specs = $heroTree['playable_specializations'] ?? [];

            foreach ($specs as $spec) {
                if ((int) ($spec['id'] ?? 0) === $specId) {
                    $filtered[] = $heroTree;
                    break;
                }
            }
        }

        return $filtered;
    }

    /**
     * @param  list<array<string, mixed>>  $heroTrees
     * @return array<int, true>
     */
    private function collectHeroNodeIds(array $heroTrees): array
    {
        $ids = [];

        foreach ($heroTrees as $heroTree) {
            /** @var list<array<string, mixed>> $nodes */
            $nodes = $heroTree['hero_talent_nodes'] ?? [];

            foreach ($nodes as $node) {
                if (is_int($node['id'] ?? null)) {
                    $ids[$node['id']] = true;
                }
            }
        }

        return $ids;
    }

    /**
     * @param  array<string, mixed>  $specializationsResponse
     * @return array<string, mixed>
     */
    private function findActiveLoadout(array $specializationsResponse): array
    {
        /** @var array{name?: string, id?: int} $activeSpec */
        $activeSpec = $specializationsResponse['active_specialization'] ?? [];
        $activeSpecId = (int) ($activeSpec['id'] ?? 0);

        /** @var list<array<string, mixed>> $specializations */
        $specializations = $specializationsResponse['specializations'] ?? [];

        foreach ($specializations as $specialization) {
            /** @var array{id?: int} $spec */
            $spec = $specialization['specialization'] ?? [];

            if ((int) ($spec['id'] ?? 0) !== $activeSpecId) {
                continue;
            }

            /** @var list<array<string, mixed>> $loadouts */
            $loadouts = $specialization['loadouts'] ?? [];

            foreach ($loadouts as $loadout) {
                if (($loadout['is_active'] ?? false) === true) {
                    return $loadout;
                }
            }
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $activeLoadout
     */
    private function extractActiveHeroTreeId(array $activeLoadout): int
    {
        /** @var array{id?: int} $heroTree */
        $heroTree = $activeLoadout['selected_hero_talent_tree'] ?? [];

        return (int) ($heroTree['id'] ?? 0);
    }

    /**
     * Build a map of nodeId => {rank, talent_id} from loadout selected talents.
     *
     * @param  list<array<string, mixed>>  $selectedTalents
     * @return array<int, array{rank: int, talent_id: int}>
     */
    private function buildSelectedMap(array $selectedTalents): array
    {
        $map = [];

        foreach ($selectedTalents as $selectedTalent) {
            $nodeId = is_int($selectedTalent['id'] ?? null) ? $selectedTalent['id'] : 0;

            if ($nodeId === 0) {
                continue;
            }

            /** @var array{talent?: array{id?: int}} $tooltip */
            $tooltip = $selectedTalent['tooltip'] ?? [];
            $talentId = is_int($tooltip['talent']['id'] ?? null) ? $tooltip['talent']['id'] : 0;

            $map[$nodeId] = [
                'rank' => is_int($selectedTalent['rank'] ?? null) ? $selectedTalent['rank'] : 0,
                'talent_id' => $talentId,
            ];
        }

        return $map;
    }

    /**
     * @param  list<array<string, mixed>>  $rawNodes
     * @param  array<int, array{rank: int, talent_id: int}>  $selectedMap
     * @param  array<int, true>  $excludeIds  Node IDs to skip (e.g. hero tree dups)
     * @return list<array<string, mixed>>
     */
    private function transformNodes(array $rawNodes, array $selectedMap, array $excludeIds = []): array
    {
        $nodes = [];

        foreach ($rawNodes as $rawNode) {
            $nodeId = is_int($rawNode['id'] ?? null) ? $rawNode['id'] : 0;

            if ($nodeId !== 0 && isset($excludeIds[$nodeId])) {
                continue;
            }

            $node = $this->transformNode($rawNode, $selectedMap);

            if ($node !== null) {
                $nodes[] = $node;
            }
        }

        return $nodes;
    }

    /**
     * @param  array<string, mixed>  $rawNode
     * @param  array<int, array{rank: int, talent_id: int}>  $selectedMap
     * @return array<string, mixed>|null
     */
    private function transformNode(array $rawNode, array $selectedMap): ?array
    {
        $nodeId = is_int($rawNode['id'] ?? null) ? $rawNode['id'] : 0;

        if ($nodeId === 0) {
            return null;
        }

        /** @var array{type?: string} $nodeType */
        $nodeType = $rawNode['node_type'] ?? [];
        $type = (string) ($nodeType['type'] ?? 'ACTIVE');

        /** @var list<array<string, mixed>> $ranks */
        $ranks = $rawNode['ranks'] ?? [];
        $maxRank = count($ranks);

        $selected = $selectedMap[$nodeId] ?? null;
        $selectedRank = $selected !== null ? $selected['rank'] : 0;
        $selectedTalentId = $selected !== null ? $selected['talent_id'] : 0;

        $entries = $this->extractEntries($ranks, $type, $selectedTalentId);

        if ($entries === []) {
            return null;
        }

        /** @var list<int|string> $lockedByRaw */
        $lockedByRaw = is_array($rawNode['locked_by'] ?? null) ? $rawNode['locked_by'] : [];
        /** @var list<int|string> $unlocksRaw */
        $unlocksRaw = is_array($rawNode['unlocks'] ?? null) ? $rawNode['unlocks'] : [];

        $displayCol = is_int($rawNode['display_col'] ?? null) ? $rawNode['display_col'] : 0;
        $displayRow = is_int($rawNode['display_row'] ?? null) ? $rawNode['display_row'] : 0;

        return [
            'id' => $nodeId,
            'x' => $displayCol,
            'y' => $displayRow,
            'type' => mb_strtolower($type),
            'max_rank' => $maxRank,
            'selected_rank' => $selectedRank,
            'locked_by' => array_map(fn (int|string $v): int => (int) $v, $lockedByRaw),
            'unlocks' => array_map(fn (int|string $v): int => (int) $v, $unlocksRaw),
            'entries' => $entries,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $ranks
     * @return list<array<string, mixed>>
     */
    private function extractEntries(array $ranks, string $type, int $selectedTalentId): array
    {
        if ($ranks === []) {
            return [];
        }

        $firstRank = $ranks[0];

        if ($type === 'CHOICE') {
            return $this->extractChoiceEntries($firstRank, $selectedTalentId);
        }

        return $this->extractSingleEntry($firstRank);
    }

    /**
     * @param  array<string, mixed>  $rank
     * @return list<array<string, mixed>>
     */
    private function extractSingleEntry(array $rank): array
    {
        /** @var array<string, mixed> $tooltip */
        $tooltip = $rank['tooltip'] ?? [];

        if ($tooltip === []) {
            return [];
        }

        /** @var array{id?: int, name?: string} $talent */
        $talent = $tooltip['talent'] ?? [];
        /** @var array{spell?: array{id?: int}} $spellTooltip */
        $spellTooltip = $tooltip['spell_tooltip'] ?? [];

        $talentId = is_int($talent['id'] ?? null) ? $talent['id'] : 0;
        $spellId = is_int($spellTooltip['spell']['id'] ?? null) ? $spellTooltip['spell']['id'] : 0;
        $name = is_string($talent['name'] ?? null) ? $talent['name'] : '';
        $iconUrl = is_string($tooltip['icon_url'] ?? null) ? $tooltip['icon_url'] : null;

        return [
            [
                'talent_id' => $talentId,
                'spell_id' => $spellId,
                'name' => $name,
                'icon_url' => $iconUrl,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $rank
     * @return list<array<string, mixed>>
     */
    private function extractChoiceEntries(array $rank, int $selectedTalentId): array
    {
        /** @var list<array<string, mixed>> $choices */
        $choices = $rank['choice_of_tooltips'] ?? [];

        if ($choices === []) {
            return [];
        }

        $entries = [];

        foreach ($choices as $choice) {
            /** @var array{id?: int, name?: string} $talent */
            $talent = $choice['talent'] ?? [];
            /** @var array{spell?: array{id?: int}} $spellTooltip */
            $spellTooltip = $choice['spell_tooltip'] ?? [];

            $talentId = is_int($talent['id'] ?? null) ? $talent['id'] : 0;
            $spellId = is_int($spellTooltip['spell']['id'] ?? null) ? $spellTooltip['spell']['id'] : 0;
            $name = is_string($talent['name'] ?? null) ? $talent['name'] : '';
            $iconUrl = is_string($choice['icon_url'] ?? null) ? $choice['icon_url'] : null;

            $entries[] = [
                'talent_id' => $talentId,
                'spell_id' => $spellId,
                'name' => $name,
                'icon_url' => $iconUrl,
                'selected' => $selectedTalentId > 0 && $talentId === $selectedTalentId,
            ];
        }

        return $entries;
    }

    /**
     * @param  list<array<string, mixed>>  $heroTrees
     * @param  array<int, array{rank: int, talent_id: int}>  $selectedMap
     * @return list<array<string, mixed>>
     */
    private function transformHeroTrees(array $heroTrees, array $selectedMap, int $activeHeroTreeId): array
    {
        $result = [];

        foreach ($heroTrees as $heroTree) {
            $treeId = is_int($heroTree['id'] ?? null) ? $heroTree['id'] : 0;

            /** @var list<array<string, mixed>> $rawNodes */
            $rawNodes = $heroTree['hero_talent_nodes'] ?? [];

            $result[] = [
                'id' => $treeId,
                'name' => is_string($heroTree['name'] ?? null) ? $heroTree['name'] : '',
                'active' => $treeId === $activeHeroTreeId,
                'nodes' => $this->transformNodes($rawNodes, $treeId === $activeHeroTreeId ? $selectedMap : []),
            ];
        }

        return $result;
    }
}
