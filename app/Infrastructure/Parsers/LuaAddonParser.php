<?php

declare(strict_types=1);

namespace App\Infrastructure\Parsers;

use Illuminate\Support\Facades\File;

class LuaAddonParser
{
    /**
     * Supplementary zone→expansion map for zones not covered by addon data or BTW Index files.
     * Covers: Classic zones, TBC starter zones, patch zones, capitals, dungeons, raids.
     */
    private const SUPPLEMENTARY_ZONE_MAP = [
        // === Classic (0) — Starter zones, capitals, dungeons ===
        'Dun Morogh' => 0, 'Durotar' => 0, "Forêt d'Elwynn" => 0, 'Mulgore' => 0,
        'Teldrassil' => 0, 'Clairières de Tirisfal' => 0, 'Hurlevent' => 0,
        'Orgrimmar' => 0, 'Forgefer' => 0, 'Darnassus' => 0, 'Les Pitons-du-Tonnerre' => 0,
        'Fossoyeuse' => 0, "Vallée d'Alterac" => 0, "Ahn'Qiraj" => 0,
        "Ahn'Qiraj : le royaume Déchu" => 0, "Ruines d'Ahn'Qiraj" => 0,
        "Vallée des Épreuves" => 0, 'Camp Narache' => 0, "Val d'Ammen" => 0,
        'Comté-du-Nord' => 0, 'Gnomeregan' => 0, 'Stratholme' => 0,
        'Profondeurs de Rochenoire' => 0, 'Gouffre de Ragefeu' => 0, 'Les Mortemines' => 0,
        "Donjon d'Ombrecroc" => 0, 'Scholomance' => 0, 'Maraudon' => 0,
        "Zul'Farrak" => 0, "Zul'Gurub" => 0, "Le temple d'Atal'Hakkar" => 0,
        'Cavernes des Lamentations' => 0, 'Kraal de Tranchebauge' => 0,
        'Souilles de Tranchebauge' => 0, 'Pic Rochenoire' => 0,
        'Sommet du pic Rochenoire' => 0, 'Cœur du Magma' => 0,
        'Cavernes de Rochenoire' => 0, 'Uldaman' => 0, 'Naxxramas' => 0,
        'Tram des profondeurs' => 0, 'La Basse-tourbière' => 0,
        "Île de Sombrelune" => 0, "Baie-du-Butin" => 0, 'Grottes du Temps' => 0,
        "L'Épuration de Stratholme" => 0, 'Le Noir marécage' => 0,

        // === The Burning Crusade (1) ===
        'Bois des Chants éternels' => 1, 'Bois des Chants éternels (The Burning Crusade)' => 1,
        'Les terres Fantômes' => 1, "Île de Brume-Sang" => 1, "Île de Brume-Azur" => 1,
        'Shattrath' => 1, "Île de Quel'Danas" => 1, "Lune-d'Argent" => 1,
        "Lune-d'Argent (The Burning Crusade)" => 1, "L'Exodar" => 1,
        "Zul'Aman" => 1, 'Citadelle des Flammes infernales' => 1,
        'Remparts des Flammes infernales' => 1, "La Fournaise du sang" => 1,
        'Les enclos aux esclaves' => 1, 'Le caveau de la Vapeur' => 1,
        'Le Méchanar' => 1, 'La Botanica' => 1, "L'Arcatraz" => 1,
        'Cryptes Auchenaï' => 1, 'Les salles des Sethekk' => 1,
        'Labyrinthe des Ombres' => 1, 'Terrasse des Magistères' => 1,
        "Temple Halo-du-Néant" => 1, "Tombes-mana" => 1,
        'Plateau du Puits de soleil' => 1, "Le repaire de Magtheridon" => 1,
        'Temple noir' => 1, "Donjon de la Tempête" => 1,
        'Sanctum Occidental' => 1, "Île de Haut-Soleil" => 1,
        "Havre de Saltheril" => 1,
        "Quel'thalas" => 1,

        // === Wrath of the Lich King (2) ===
        'Dalaran' => 2, 'Citadelle de la Couronne de glace' => 2,
        "Joug-d'Hiver" => 2, "Donjon d'Utgarde" => 2, "Cime d'Utgarde" => 2,
        'Gundrak' => 2, 'Le Nexus' => 2, "L'Oculus" => 2,
        'Fosse de Saron' => 2, "La Forge des Âmes" => 2,
        "Donjon de Drak'Tharon" => 2, "Ahn'kahet : l'Ancien royaume" => 2,
        "Azjol-Nérub" => 2, 'Salles Écarlates' => 2,
        "L'Œil de l'éternité" => 2, 'Ulduar' => 2,
        "Le Jugement des Valeureux" => 2, "L'épreuve du champion" => 2,
        "L'épreuve du croisé" => 2, 'Salles des Reflets' => 2,
        "Forêt du Chant de cristal" => 2,

        // === Cataclysm (3) ===
        'Gilnéas' => 3, 'Ruines de Gilnéas' => 3, 'Les îles Perdues' => 3,
        'Kezan' => 3, "Hache-Tripes" => 3, "Péninsule de Tol Barad" => 3,
        'Tol Barad' => 3, 'Les Serres-Rocheuses' => 3, 'Les Hinterlands' => 3,
        "Profondeurs Abyssales" => 3, "Rivages de Tranchevent" => 3,
        "Contreforts de Hautebrande d'antan" => 3, 'Grim Batol' => 3,
        'Cité perdue des Tol\'vir' => 3, 'Trône des marées' => 3,
        "La Fin des temps" => 3, "L'Heure du Crépuscule" => 3,
        "Puits d'éternité" => 3, 'Sombrevallon' => 3,
        "La cime du Vortex" => 3, "Citadelle d'Obsidienne" => 3,
        'Terres de Feu' => 3, "Sommet d'Hyjal" => 3,
        "Les salles de Pierre" => 3, "Salles de l'Origine" => 3,
        'Monastère Écarlate' => 3,
        "Défilé de Deuillevent" => 3, "Colline Meurtrière" => 3,
        "Le Maelström" => 3,

        // === Mists of Pandaria (4) ===
        "Val de l'Éternel printemps" => 4, "Île du Tonnerre" => 4,
        "L'île Vagabonde" => 4, "Île du Temps figé" => 4, "Île des Géants" => 4,
        'Temple du Serpent de jade' => 4, "Brasserie Brune d'Orage" => 4,
        "Palais Mogu'shan" => 4, "Siège du temple de Niuzao" => 4,
        'Monastère des Pandashan' => 4, "Siège d'Orgrimmar" => 4,
        "Trône du tonnerre" => 4, "Cœur de la Peur" => 4,
        "Terrasse du Printemps éternel" => 4, "Unga Ingou" => 4,

        // === Warlords of Draenor (5) ===
        "A'shran" => 5, "Terres sacrées d'Ombrelune" => 5,
        'Auchindoun' => 5, 'Quais de Fer' => 5,
        'Fonderie des Rochenoires' => 5, "Profondeurs de Brassenoire" => 5,
        'Cognefort' => 5, "Cité des Fils" => 5,
        "Mine de la Masse-Sanglante" => 5, "Bouclier-des-Tempêtes" => 5,

        // === Legion (6) ===
        'Krokuun' => 6, 'Érédath' => 6, 'Étendues Antoréennes' => 6,
        "Tombe de Sargeras" => 6, "Antorus, le Trône ardent" => 6,
        "Palais Sacrenuit" => 6, "Le Cauchemar d'émeraude" => 6,
        "Cour des Étoiles" => 6, "L'Arcavia" => 6,
        "Les salles Brisées" => 6, 'Helheim' => 6,
        "L'Œil d'Azshara" => 6, "Œil d'Azshara" => 6,
        "Caveau des Gardiennes" => 6, "Cathédrale de la Nuit éternelle" => 6,
        "Siège du triumvirat" => 6, "Niskara" => 6,
        "Fer-de-Lance" => 6, "Pavillon du Traqueur" => 6,
        "Orée-du-Ciel" => 6, "Haut-Maul" => 6,
        "Hall du Gardien" => 6, "Le Vindicaar" => 6,
        "Sanctum de la Lumière" => 6, "Marges des Éons" => 6,
        "Achérus : le fort d'Ébène" => 6, "Bastion du Freux" => 6,
        'Flèche d\'Aubétoile' => 6, "La Mugambala" => 6,

        // === Battle for Azeroth (7) ===
        "Confins de l'Exil" => 7,
        'La Nouvelle-Brikabrok' => 7, "Silithus : la Plaie" => 7,
        "Port de Boralus" => 7, "Port de Zandalar" => 7,
        "Bataille de Dazar'alor" => 7, "Ny'alotha, la cité en éveil" => 7,
        "Campagne militaire - Horde" => 7, "Campagne militaire - Alliance" => 7,
        "Palais Éternel" => 7, "Port de Hurlevent" => 7,
        "Île de Theramore" => 7, "Le Glas" => 7,
        "Fief" => 7, "Porte du Soleil couchant" => 7,
        "La Sylverêve" => 7, "Rivage Bouillonnant" => 7,
        "Îles de l'Écho" => 7,

        // === Shadowlands (8) ===
        'Antre' => 8, 'Korthia' => 8, 'Zereth Mortis' => 8, 'Oribos' => 8,
        'Tourment, la tour des Damnés' => 8,
        "Tazavesh, le marché dissimulé" => 8,
        "Château Nathria" => 8, "Sanctum de Domination" => 8,
        "Sépulcre des Fondateurs" => 8,
        "Jardin d'hiver de la Reine" => 8,
        "Salles des Valeureux" => 8, "Le Cœur-de-Pierre" => 8,
        "Les salles de Foudre" => 8, "Trône brisé" => 8,
        "Les Écheveaux" => 8, "Sillage nécrotique" => 8,
        "Faille de Scareffroi" => 8, "Fourré Sombrecœur" => 8,
        "Quartier général des archéologues" => 8, "Cap Fondateur" => 8,

        // === Dragonflight (9) ===
        'Grotte de Zaralek' => 9, "Rêve d'émeraude" => 9,
        'Valdrakken' => 9, 'Îles aux Dragons' => 9,
        'Caveaux de Zskera' => 9, 'Amirdrassil' => 9,
        "Amirdrassil, l'Espoir du Rêve" => 9, "Aube de l'Infini" => 9,
        "Aberrus, le creuset de l'Ombre" => 9,
        "Caveau des Incarnations" => 9,
        "La Flore éternelle" => 9, "Arcantina" => 9,
        "Randonneraie" => 9, "Dépôt de Tristerail" => 9,
        'Creuset des Tempêtes' => 9, "Bastion de Tyr" => 9,
        "Le sanctum Rubis" => 9, "Repaire de Neltharion" => 9,
        "Repos du Vigilant" => 9, "Azmerloth" => 9,
        "Le cloaque aux Dragons" => 9, "Confins Interdits" => 9,

        // === The War Within (10) ===
        'Harandar' => 10, 'Tempête du Vide' => 10,
        'Étendues Chatoyantes' => 10, "L'Oasis" => 10,
        "Forêt de Varech'thar" => 10, 'Dornogal' => 10,
        "Libération de Terremine" => 10,
        "Palais des Nérub'ar" => 10, 'Frimarra' => 10,
        "Larmes de Morgaen" => 10, "Les Abîmes Retentissants" => 10,
        "Amani'Zar" => 10, "Vallée des Frigères" => 10,
        "Village de Morqut" => 10, "Mine de Nibelgaz" => 10,
        "Oubliettes du Runomancien" => 10, "Pierre-Verte" => 10,
        "Reflet-de-Lune" => 10, "Ruisse-Braise" => 10,
        "Repos de Kriegval" => 10, "Ravin d'Abondabîme" => 10,
        "Mereldar" => 10, "Pointe du Crochet" => 10,
        "Épée de l'Aube" => 10, "Tombe-Vice" => 10,
        "Grotte de Mycomancie" => 10, "Répit du gardien" => 10,
        "Séjour céleste" => 10, "Duos infâmes" => 10,
        "Le repaire de la Tisserande" => 10, "Arène de la Cicatrice du Vide" => 10,
        "Har'mara" => 10, "Champs des Tempêtes" => 10, "Manaforge Oméga" => 10,

        // === Midnight (11) ===
        "Hâle solaarien" => 11,

        // === Extra: zones missed by other sources ===
        'Karazhan' => 1,
        'Bassin Arathi' => 0, "L'escalier Dérobé" => 0,
        "La Prison" => 0, "Le fort Pourpre" => 0, "Fort Pourpre" => 0,
        "Falaises de l'embouchure de Hel" => 6,
        "Site d'invasion" => 6, "Site d'invasion prioritaire : Occularus" => 6,
        "Site d'invasion prioritaire : Sotanathor" => 6,
        "Site d'invasion prioritaire : inquisiteur Méto" => 6,
        "Site d'invasion prioritaire : matrone Folnuna" => 6,
        "Site d'invasion prioritaire : maîtresse Alluradel" => 6,
        "Site d'invasion prioritaire : seigneur des abîmes Vilemus" => 6,
        "Chambre du Cœur" => 7,
        "Le sanctum Obsidien" => 9, "Passage du Temps" => 9,
        "Festival de Brasse-Lune" => 0,
    ];

    /**
     * Normalize apostrophes/quotes: replace curly/smart quotes with straight ones.
     * Blizzard API uses U+2019 (RIGHT SINGLE QUOTATION MARK) but PHP source uses U+0027.
     */
    public static function normalizeApostrophes(string $str): string
    {
        return str_replace(
            ["\u{2019}", "\u{2018}", "\u{201C}", "\u{201D}", "\u{00A0}"],
            ["'", "'", '"', '"', ' '],
            $str
        );
    }

    /**
     * Parse BTW addon Lua files to extract quest/achievement IDs with metadata.
     *
     * @return array{quests: list<array{id: int, expansion_id: int, zone_name: string}>, achievements: list<array{id: int, expansion_id: int, category_name: string}>}
     */
    public function parseAllAddons(): array
    {
        $questsPath = storage_path('app/blizzard/mappings/processed/quests.json');
        $achievementsPath = storage_path('app/blizzard/mappings/processed/achievements.json');

        /** @var array<int|string, array<string, mixed>> $quests */
        $quests = File::exists($questsPath) ? json_decode(File::get($questsPath), true) : [];
        /** @var array<int|string, array<string, mixed>> $achievements */
        $achievements = File::exists($achievementsPath) ? json_decode(File::get($achievementsPath), true) : [];

        return [
            'quests' => $this->normalizeQuests($quests),
            'achievements' => $this->normalizeAchievements($achievements),
        ];
    }

    /**
     * Normalize quests data from BTW format.
     *
     * @param array<int|string, array<string, mixed>> $data
     * @return list<array{id: int, expansion_id: int, zone_name: string}>
     */
    private function normalizeQuests(array $data): array
    {
        $normalized = [];

        foreach ($data as $expansionId => $expansionData) {
            if (!isset($expansionData['zones'])) {
                continue;
            }

            /** @var array<string, array{ids: list<int>}> $zones */
            $zones = $expansionData['zones'];
            foreach ($zones as $zoneName => $zoneInfo) {
                foreach ($zoneInfo['ids'] as $questId) {
                    $normalized[] = [
                        'id' => (int) $questId,
                        'expansion_id' => (int) $expansionId,
                        'zone_name' => $zoneName,
                    ];
                }
            }
        }

        return $normalized;
    }

    /**
     * Normalize achievements data from BTW format.
     *
     * @param array<int|string, array<string, mixed>> $data
     * @return list<array{id: int, expansion_id: int, category_name: string}>
     */
    private function normalizeAchievements(array $data): array
    {
        $normalized = [];

        foreach ($data as $expansionId => $expansionData) {
            if (!isset($expansionData['categories'])) {
                continue;
            }

            /** @var array<string, array{ids: list<int>}> $categories */
            $categories = $expansionData['categories'];
            foreach ($categories as $categoryName => $categoryInfo) {
                foreach ($categoryInfo['ids'] as $achievementId) {
                    $normalized[] = [
                        'id' => (int) $achievementId,
                        'expansion_id' => (int) $expansionId,
                        'category_name' => $categoryName,
                    ];
                }
            }
        }

        return $normalized;
    }

    /**
     * Build a zone_name (lowercase) → expansion_id map from:
     * 1. Processed quests.json zone names
     * 2. BTW addon Index.frFR.lua files (French zone names per expansion)
     *
     * @return array<string, int> [lowercase_zone_name => expansion_id]
     */
    public function getZoneExpansionMap(): array
    {
        $map = [];

        // Source 1: processed quests.json
        $questsPath = storage_path('app/blizzard/mappings/processed/quests.json');
        if (File::exists($questsPath)) {
            /** @var array<int|string, array{zones?: array<string, mixed>}> $data */
            $data = json_decode(File::get($questsPath), true);
            foreach ($data as $expansionId => $expansionData) {
                $zones = $expansionData['zones'] ?? [];
                foreach (array_keys($zones) as $zoneName) {
                    $map[mb_strtolower(self::normalizeApostrophes((string) $zoneName))] = (int) $expansionId;
                }
            }
        }

        // Source 2: BTW addon Index.frFR.lua files (French zone names)
        $btwMap = $this->parseBtwZoneNames();
        foreach ($btwMap as $zoneName => $expansionId) {
            $map[mb_strtolower(self::normalizeApostrophes($zoneName))] = $expansionId;
        }

        // Source 3: Supplementary static map for zones not covered by addon/BTW
        foreach (self::SUPPLEMENTARY_ZONE_MAP as $zoneName => $expansionId) {
            $key = mb_strtolower(self::normalizeApostrophes($zoneName));
            if (!isset($map[$key])) {
                $map[$key] = $expansionId;
            }
        }

        return $map;
    }

    /**
     * Parse BTW addon Index.frFR.lua files to extract French zone names per expansion.
     * Format: entries with type="category" and an expansion link like "btwquests:expansion:X".
     *
     * @return array<string, int> [zone_name => expansion_id]
     */
    private function parseBtwZoneNames(): array
    {
        $btwDir = storage_path('app/blizzard/mappings/BTW');
        if (!File::isDirectory($btwDir)) {
            return [];
        }

        // Map BTW directory names to expansion IDs
        $expansionDirs = [
            'BtWQuestsClassic' => 0,
            'BtWQuestsTheBurningCrusade' => 1,
            'BtWQuestsWrathOfTheLichKing' => 2,
            'BtWQuestsCataclysm' => 3,
            'BtWQuestsMistsOfPandaria' => 4,
            'BtWQuestsWarlordsOfDraenor' => 5,
            'BtWQuestsLegion' => 6,
            'BtWQuestsBattleForAzeroth' => 7,
            'BtWQuestsBattleForAzerothPrologue' => 7,
            'BtWQuestsShadowlands' => 8,
            'BtWQuestsShadowlandsPrologue' => 8,
            'BtWQuestsDragonflight' => 9,
            'BtWQuestsDragonflightPrologue' => 9,
            'BtWQuestsTheWarWithin' => 10,
            'BtWQuestsMidnightPrologue' => 11,
        ];

        $map = [];

        foreach ($expansionDirs as $dirName => $expansionId) {
            $indexPaths = [
                sprintf('%s/%s/Database/Index.frFR.lua', $btwDir, $dirName),
                sprintf('%s/%s/Index.frFR.lua', $btwDir, $dirName),
            ];

            foreach ($indexPaths as $indexPath) {
                if (!File::exists($indexPath)) {
                    continue;
                }

                $content = File::get($indexPath);

                // Extract entries with type = "category" and their name
                // Pattern: name = "Zone Name",\n        type = "category",
                preg_match_all(
                    '/name\s*=\s*"([^"]+)",\s*\n\s*type\s*=\s*"category"/',
                    $content,
                    $matches
                );

                foreach ($matches[1] as $zoneName) {
                    $map[$zoneName] = $expansionId;
                }
            }
        }

        return $map;
    }

    /**
     * Build a quest_id → expansion_id map for MODERN EXPANSION OVERRIDES ONLY.
     *
     * This map is used to override zone-based mapping when a quest in a modern zone
     * (TWW, Midnight, etc.) actually belongs to a different modern expansion.
     * Only returns entries where ContentTuning ExpansionID >= 10.
     *
     * Zone-based mapping (AREA_EXPANSION_MAP) remains the primary source.
     * This map only provides per-quest overrides for modern multi-expansion zones.
     *
     * @return array<int, int> [quest_id => expansion_id] (only modern expansion overrides)
     */
    public function getQuestExpansionMap(): array
    {
        $contentTuningMap = $this->parseContentTuningCsv();
        $questContentTuning = $this->parseBtwQuestContentTuningIds();

        // Also add QuestV2CliTask data for additional coverage
        $cliTaskData = $this->parseQuestV2CliTaskCsv();
        foreach ($cliTaskData as $questId => $ctId) {
            if (!isset($questContentTuning[$questId])) {
                $questContentTuning[$questId] = $ctId;
            }
        }

        // Only return quests where ContentTuning says expansion >= 10 (TWW, Midnight, ...)
        $map = [];
        foreach ($questContentTuning as $questId => $contentTuningId) {
            if (!isset($contentTuningMap[$contentTuningId])) {
                continue;
            }

            $expansion = $contentTuningMap[$contentTuningId];
            if ($expansion >= 10) {
                $map[$questId] = $expansion;
            }
        }

        return $map;
    }

    /**
     * Build area_id → expansion_id map from DB2 data (AreaTable + Map + ContentTuning).
     *
     * Algorithm:
     * 1. If area has a manual override → use it
     * 2. If area's continent/map has ExpansionID > 0 → use Map.ExpansionID
     * 3. If area is on a Classic continent (EK/Kalimdor, MapExp=0):
     *    - If ContentTuning.ExpansionID > 0 → use it (post-Classic zone on old continent)
     *    - Else → Classic (0)
     * 4. Walk parent chain if map not found
     *
     * @return array<int, int> [area_id => expansion_id]
     */
    public function buildAreaExpansionMap(): array
    {
        $areaTable = $this->parseAreaTableCsv();
        $contentTuningMap = $this->parseContentTuningCsv();
        $mapTable = $this->parseMapCsv();

        $result = [];
        foreach (array_keys($areaTable) as $areaId) {
            // Check override first
            if (isset(self::AREA_EXPANSION_OVERRIDES[$areaId])) {
                $result[$areaId] = self::AREA_EXPANSION_OVERRIDES[$areaId];
                continue;
            }

            $result[$areaId] = $this->resolveAreaExpansion($areaId, $areaTable, $mapTable, $contentTuningMap);
        }

        return $result;
    }

    /**
     * Resolve a single area's expansion from DB2 data.
     *
     * @param array<int, array{continent_id: int, parent_id: int, ct_id: int}> $areaTable
     * @param array<int, int> $mapTable
     * @param array<int, int> $contentTuningMap
     */
    private function resolveAreaExpansion(int $areaId, array $areaTable, array $mapTable, array $contentTuningMap): int
    {
        if (!isset($areaTable[$areaId])) {
            return 0;
        }

        $area = $areaTable[$areaId];
        $continentId = $area['continent_id'];
        $mapExp = $mapTable[$continentId] ?? -1;
        $ctId = $area['ct_id'];
        $ctExp = ($ctId > 0 && isset($contentTuningMap[$ctId])) ? $contentTuningMap[$ctId] : -99;

        // Map expansion > 0 → expansion-specific continent/instance
        if ($mapExp > 0) {
            return $mapExp;
        }

        // Classic continent (MapExp = 0) → check ContentTuning
        if ($mapExp === 0) {
            return max($ctExp, 0);
        }

        // Map not found → walk parent chain
        if ($area['parent_id'] > 0) {
            return $this->resolveAreaExpansion($area['parent_id'], $areaTable, $mapTable, $contentTuningMap);
        }

        return 0;
    }

    /**
     * Manual overrides for area_ids where DB2 data gives incorrect expansions.
     * Only ~25 entries instead of the old 445-entry AREA_EXPANSION_MAP.
     */
    private const AREA_EXPANSION_OVERRIDES = [
        // === Zones on Classic continents (EK/Kalimdor) that are post-Classic ===
        // DB2 gives Classic because ContentTuning reflects Classic-era level scaling
        2037 => 1,    // Quel'thalas → TBC
        4706 => 3,    // Ruines de Gilnéas → Cata
        4709 => 3,    // Tarides du Sud → Cata

        // === Dungeon remakes (old map reused, quests are from remake expansion) ===
        6052 => 4,    // Salles Écarlates → MoP remake
        6066 => 4,    // Scholomance → MoP remake
        6109 => 4,    // Monastère Écarlate → MoP remake
        6298 => 4,    // Arène de Castagn'ar → MoP
        8161 => 2,    // Ulduar → WotLK (not WoD timewalking version)

        // === Legion areas with wrong DB2 resolution ===
        7969 => 6,    // Karazhan (Return to Karazhan) → Legion
        7978 => 6,    // Repos du Vigilant → Legion
        8124 => 6,    // Épée de l'Aube → Legion

        // === BfA areas on old continents ===
        4411 => 7,    // Port de Hurlevent → BfA (War Campaign hub)
        8044 => 7,    // Clairières de Tirisfal → BfA version
        8317 => 7,    // Clairières de Tirisfal → BfA
        8318 => 7,    // Clairières de Tirisfal → BfA
        8839 => 7,    // Île de Theramore → BfA
        9136 => 7,    // Rivage Bouillonnant → BfA
        9310 => 7,    // Silithus : la Plaie → BfA

        // === DF areas with wrong DB2 resolution ===
        13625 => 9,   // Répit du gardien → DF
        13769 => 9,   // Confins Interdits → DF
        13983 => 9,   // Clairières de Tirisfal (DF version) → DF

        // === TWW areas ===
        15058 => 10,  // Hautes-terres Arathies (TWW Void zone) → TWW
        15542 => 10,  // Quartier prototype Logis des PNJ → TWW

        // === Midnight areas ===
        // Harandar, Tempête du Vide, etc. are Midnight zones (DB2 is correct)
        9313 => 11,   // Lune-d'Argent → Midnight
        16092 => 11,  // Duos infâmes → Midnight
        16432 => 11,  // Hautes-terres Arathies (Midnight RPE) → Midnight
    ];

    /**
     * Parse AreaTable CSV (wago.tools) → area_id → {continent_id, parent_id, ct_id}.
     *
     * @return array<int, array{continent_id: int, parent_id: int, ct_id: int}>
     */
    private function parseAreaTableCsv(): array
    {
        $csvPath = storage_path('app/blizzard/area_table.csv');
        if (!File::exists($csvPath)) {
            return [];
        }

        $map = [];
        $handle = fopen($csvPath, 'r');
        if ($handle === false) {
            return [];
        }

        $headers = fgetcsv($handle, 0, ',', '"', '');
        if ($headers === false) {
            fclose($handle);
            return [];
        }

        $idIdx = (int) array_search('ID', $headers, true);
        $contIdx = (int) array_search('ContinentID', $headers, true);
        $parentIdx = (int) array_search('ParentAreaID', $headers, true);
        $ctIdx = (int) array_search('ContentTuningID', $headers, true);

        while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            $map[(int) $row[$idIdx]] = [
                'continent_id' => (int) $row[$contIdx],
                'parent_id' => (int) $row[$parentIdx],
                'ct_id' => (int) $row[$ctIdx],
            ];
        }

        fclose($handle);

        return $map;
    }

    /**
     * Parse Map CSV (wago.tools) → map_id → ExpansionID.
     *
     * @return array<int, int> [map_id => ExpansionID]
     */
    private function parseMapCsv(): array
    {
        $csvPath = storage_path('app/blizzard/map.csv');
        if (!File::exists($csvPath)) {
            return [];
        }

        $map = [];
        $handle = fopen($csvPath, 'r');
        if ($handle === false) {
            return [];
        }

        $headers = fgetcsv($handle, 0, ',', '"', '');
        if ($headers === false) {
            fclose($handle);
            return [];
        }

        $idIdx = (int) array_search('ID', $headers, true);
        $expIdx = (int) array_search('ExpansionID', $headers, true);

        while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            $map[(int) $row[$idIdx]] = (int) $row[$expIdx];
        }

        fclose($handle);

        return $map;
    }

    /**
     * Parse ContentTuning CSV (wago.tools) → contentTuningID → ExpansionID.
     *
     * @return array<int, int> [contentTuningID => ExpansionID]
     */
    private function parseContentTuningCsv(): array
    {
        $csvPath = storage_path('app/blizzard/content_tuning.csv');
        if (!File::exists($csvPath)) {
            return [];
        }

        $map = [];
        $handle = fopen($csvPath, 'r');
        if ($handle === false) {
            return [];
        }

        $headers = fgetcsv($handle, 0, ',', '"', '');
        if ($headers === false) {
            fclose($handle);
            return [];
        }

        $idIdx = (int) array_search('ID', $headers, true);
        $expIdx = (int) array_search('ExpansionID', $headers, true);

        while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            $map[(int) $row[$idIdx]] = (int) $row[$expIdx];
        }

        fclose($handle);

        return $map;
    }

    /**
     * Parse all BtWQuests Lua files for quest_id → contentTuningID.
     * Scans ALL expansion folders — the expansion is NOT determined by folder,
     * but by the contentTuningID which maps to the real expansion via ContentTuning.
     *
     * @return array<int, int> [quest_id => contentTuningID]
     */
    private function parseBtwQuestContentTuningIds(): array
    {
        $btwDir = storage_path('app/blizzard/mappings/BTW');
        if (!File::isDirectory($btwDir)) {
            return [];
        }

        $expansionDirs = [
            'BtWQuestsClassic', 'BtWQuestsTheBurningCrusade', 'BtWQuestsWrathOfTheLichKing',
            'BtWQuestsCataclysm', 'BtWQuestsMistsOfPandaria', 'BtWQuestsWarlordsOfDraenor',
            'BtWQuestsLegion', 'BtWQuestsBattleForAzeroth', 'BtWQuestsBattleForAzerothPrologue',
            'BtWQuestsShadowlands', 'BtWQuestsShadowlandsPrologue',
            'BtWQuestsDragonflight', 'BtWQuestsDragonflightPrologue',
            'BtWQuestsTheWarWithin', 'BtWQuestsMidnightPrologue',
        ];

        $map = [];

        foreach ($expansionDirs as $expansionDir) {
            foreach ([sprintf('%s/%s/Database/Quests.lua', $btwDir, $expansionDir), sprintf('%s/%s/Quests.lua', $btwDir, $expansionDir)] as $path) {
                if (!File::exists($path)) {
                    continue;
                }

                $content = File::get($path);

                // Extract quest ID and contentTuningID pairs
                // Pattern: [questId] = {\n        name = "...",\n        contentTuningID = 42,
                preg_match_all(
                    '/^\s*\[(\d+)\]\s*=\s*\{[^}]*?contentTuningID\s*=\s*(\d+)/ms',
                    $content,
                    $matches,
                    PREG_SET_ORDER
                );

                foreach ($matches as $match) {
                    $questId = (int)$match[1];
                    $ctId = (int)$match[2];
                    // Keep first occurrence (earlier expansion files take priority)
                    if (!isset($map[$questId])) {
                        $map[$questId] = $ctId;
                    }
                }
            }
        }

        return $map;
    }

    /**
     * Parse QuestV2CliTask CSV (wago.tools) for quest_id → contentTuningID.
     * Covers world quests, daily quests, and other client-side tasks.
     *
     * @return array<int, int> [quest_id => contentTuningID]
     */
    private function parseQuestV2CliTaskCsv(): array
    {
        $csvPath = storage_path('app/blizzard/quest_v2_cli_task.csv');
        if (!File::exists($csvPath)) {
            return [];
        }

        $map = [];
        $handle = fopen($csvPath, 'r');
        if ($handle === false) {
            return [];
        }

        $headers = fgetcsv($handle, 0, ',', '"', '');
        if ($headers === false) {
            fclose($handle);
            return [];
        }

        $idIdx = (int) array_search('ID', $headers, true);
        $ctIdx = (int) array_search('ContentTuningID', $headers, true);

        while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            $questId = (int) $row[$idIdx];
            $ctId = (int) $row[$ctIdx];
            if ($ctId > 0) {
                $map[$questId] = $ctId;
            }
        }

        fclose($handle);

        return $map;
    }

    /**
     * Parse QuestV2CliTask CSV for quest_id → faction from FiltRaces bitmask.
     *
     * Known FiltRaces values (compared as strings to avoid 64-bit precision issues):
     * - '6130900294268439629'  → Alliance races only
     * - '-6184943489809468494' → Horde races only
     * - '-1' or anything else  → both factions (not included in map)
     *
     * @return array<int, string> [quest_id => 'Alliance'|'Horde']
     */
    public function getQuestFactionMap(): array
    {
        $csvPath = storage_path('app/blizzard/quest_v2_cli_task.csv');
        if (!File::exists($csvPath)) {
            return [];
        }

        $handle = fopen($csvPath, 'r');
        if ($handle === false) {
            return [];
        }

        $headers = fgetcsv($handle, 0, ',', '"', '');
        if ($headers === false) {
            fclose($handle);
            return [];
        }

        $idIdx = (int) array_search('ID', $headers, true);
        $filtRacesIdx = (int) array_search('FiltRaces', $headers, true);

        $allianceBitmask = '6130900294268439629';
        $hordeBitmask = '-6184943489809468494';

        $map = [];
        while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            $questId = (int) $row[$idIdx];
            $filtRaces = trim((string) $row[$filtRacesIdx]);

            if ($filtRaces === $allianceBitmask) {
                $map[$questId] = 'Alliance';
            } elseif ($filtRaces === $hordeBitmask) {
                $map[$questId] = 'Horde';
            }
        }

        fclose($handle);

        return $map;
    }

    /**
     * Build area_id → faction map from AreaTable FactionGroupMask.
     *
     * FactionGroupMask values:
     * - 0 → no faction restriction (neutral)
     * - 2 → Alliance only
     * - 4 → Horde only
     * - 6 → sanctuary/both factions
     *
     * @return array<int, string> [area_id => 'Alliance'|'Horde']
     */
    public function getZoneFactionMap(): array
    {
        $csvPath = storage_path('app/blizzard/area_table.csv');
        if (!File::exists($csvPath)) {
            return [];
        }

        $handle = fopen($csvPath, 'r');
        if ($handle === false) {
            return [];
        }

        $headers = fgetcsv($handle, 0, ',', '"', '');
        if ($headers === false) {
            fclose($handle);
            return [];
        }

        $idIdx = (int) array_search('ID', $headers, true);
        $fgmIdx = (int) array_search('FactionGroupMask', $headers, true);

        $map = [];
        while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            $fgm = (int) $row[$fgmIdx];
            if ($fgm === 2) {
                $map[(int) $row[$idIdx]] = 'Alliance';
            } elseif ($fgm === 4) {
                $map[(int) $row[$idIdx]] = 'Horde';
            }
        }

        fclose($handle);

        return $map;
    }

    /**
     * Build reputation_faction_id → 'Alliance'|'Horde' map from Faction.csv.
     *
     * Uses ReputationBase values: if Alliance base >= 0 and Horde base < 0, it's Alliance.
     * If Horde base >= 0 and Alliance base < 0, it's Horde.
     *
     * @return array<int, string> [reputation_faction_id => 'Alliance'|'Horde']
     */
    public function getReputationFactionMap(): array
    {
        $csvPath = storage_path('app/blizzard/faction.csv');
        if (!File::exists($csvPath)) {
            return [];
        }

        $handle = fopen($csvPath, 'r');
        if ($handle === false) {
            return [];
        }

        $headers = fgetcsv($handle, 0, ',', '"', '');
        if ($headers === false) {
            fclose($handle);
            return [];
        }

        $idIdx = (int) array_search('ID', $headers, true);
        $base0Idx = (int) array_search('ReputationBase_0', $headers, true);
        $base1Idx = (int) array_search('ReputationBase_1', $headers, true);

        $map = [];
        while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            $allianceBase = (int) $row[$base0Idx];
            $hordeBase = (int) $row[$base1Idx];

            if ($allianceBase >= 0 && $hordeBase < 0) {
                $map[(int) $row[$idIdx]] = 'Alliance';
            } elseif ($hordeBase >= 0 && $allianceBase < 0) {
                $map[(int) $row[$idIdx]] = 'Horde';
            }
        }

        fclose($handle);

        return $map;
    }

    /**
     * Build an achievement_id → expansion_id map from addon total_ids.
     * Used to assign expansion to achievements fetched from Blizzard API.
     *
     * @return array<int, int> [achievement_id => expansion_id]
     */
    public function getAchievementExpansionMap(): array
    {
        $achievementsPath = storage_path('app/blizzard/mappings/processed/achievements.json');
        if (!File::exists($achievementsPath)) {
            return [];
        }

        /** @var array<int|string, array{total_ids: list<int>}> $data */
        $data = json_decode(File::get($achievementsPath), true);
        $map = [];

        foreach ($data as $expansionId => $expansionData) {
            $totalIds = $expansionData['total_ids'];
            foreach ($totalIds as $totalId) {
                $map[(int) $totalId] = (int) $expansionId;
            }
        }

        return $map;
    }

    /**
     * Get all unique quest IDs across all expansions.
     *
     * @return list<int>
     */
    public function getAllQuestIds(): array
    {
        $data = $this->parseAllAddons();
        return array_values(array_unique(array_column($data['quests'], 'id')));
    }

    /**
     * Get all unique achievement IDs across all expansions.
     *
     * @return list<int>
     */
    public function getAllAchievementIds(): array
    {
        $data = $this->parseAllAddons();
        return array_values(array_unique(array_column($data['achievements'], 'id')));
    }
}
