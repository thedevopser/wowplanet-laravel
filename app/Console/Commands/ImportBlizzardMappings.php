<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\ValueObjects\ExpansionId;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ImportBlizzardMappings extends Command
{
    protected $signature = 'blizzard:import-mappings';
    protected $description = 'Parse LUA addon data to extract achievement and quest IDs per expansion';

    private const BASE_PATH = 'storage/app/blizzard/mappings';
    private const OUTPUT_PATH = 'storage/app/blizzard/mappings/processed';

    public function handle(): void
    {
        $this->info('Starting LUA mapping extraction...');

        if (!File::exists(base_path(self::OUTPUT_PATH))) {
            File::makeDirectory(base_path(self::OUTPUT_PATH), 0755, true);
        }

        $this->processAchievements();
        $this->processQuests();

        $this->info('Extraction completed successfully!');
    }

    private function processAchievements(): void
    {
        $this->info('Processing Achievements (Krowi)...');
        $achievements = [];

        // Load French achievement names from Krowi's localization first
        $frenchAchievementNames = [];
        $frPath = base_path(self::BASE_PATH . "/Krowi_AchievementFilter/Localization/frFR.lua");
        if (File::exists($frPath)) {
            $frContent = File::get($frPath);
            // Krowi format: L["English Name"] = "French Name"
            preg_match_all('/L\["([^"]+)"\]\s*=\s*"([^"]+)"/', $frContent, $frMatches);
            foreach ($frMatches[1] as $index => $enName) {
                $frenchAchievementNames[$enName] = $frMatches[2][$index];
            }
        }

        $expansionMap = [
            '01_Vanilla' => ExpansionId::CLASSIC,
            '02_TheBurningCrusade' => ExpansionId::BURNING_CRUSADE,
            '03_WrathOfTheLichKing' => ExpansionId::WRATH_OF_THE_LICH_KING,
            '04_Cataclysm' => ExpansionId::CATACLYSM,
            '05_MistsOfPandaria' => ExpansionId::MISTS_OF_PANDARIA,
            '06_WarlordsOfDaenor' => ExpansionId::WARLORDS_OF_DRAENOR,
            '07_Legion' => ExpansionId::LEGION,
            '08_BattleForAzeroth' => ExpansionId::BATTLE_FOR_AZEROTH,
            '09_Shadowlands' => ExpansionId::SHADOWLANDS,
            '10_Dragonflight' => ExpansionId::DRAGONFLIGHT,
            '11_TheWarWithin' => ExpansionId::THE_WAR_WITHIN,
            '12_Midnight' => ExpansionId::MIDNIGHT,
        ];

        foreach ($expansionMap as $folder => $expansionId) {
            $path = base_path(self::BASE_PATH . "/Krowi_AchievementFilter/DataAddons/Retail/{$folder}/CategoryData.lua");

            if (!File::exists($path)) {
                continue;
            }

            $content = File::get($path);
            $expansionData = [
                'total_ids' => [],
                'categories' => []
            ];

            $currentCategory = "Général";
            $lines = explode("\n", $content);

            foreach ($lines as $line) {
                if (preg_match('/--\s*([A-Z][a-zA-Z\s\':]+)/', $line, $catMatch)) {
                    $cat = trim($catMatch[1]);
                    if (!in_array($cat, ["Achievements", "Zones", "Exploration", "Reputation", "Quests"])) {
                        // Manual mapping for categories Krowi often misses or doesn't translate
                        $manualCatMap = [
                            'War Within' => 'The War Within',
                            'War Within Superior' => 'The War Within (Supérieur)',
                            'War Within Epic' => 'The War Within (Épique)',
                            'One Warband Mentor: The War Within' => 'Un mentor de bataillon : The War Within',
                            'Two Warband Mentors: The War Within' => 'Deux mentors de bataillon : The War Within',
                            'Three Warband Mentors: The War Within' => 'Trois mentors de bataillon : The War Within',
                            'Four Warband Mentors: The War Within' => 'Quatre mentors de bataillon : The War Within',
                            'Five Warband Mentors: The War Within' => 'Cinq mentors de bataillon : The War Within',
                            'Harbinger of the Weathered' => 'Messager de l\'altéré',
                            'The War Within' => 'The War Within',
                            'Dragon Isles' => 'Îles aux Dragons',
                            // ...
                            'Shadowlands' => 'Ombreterre',
                            'Battle for Azeroth' => 'Battle for Azeroth',
                            'Legion' => 'Legion',
                            'Draenor' => 'Draenor',
                            'Pandaria' => 'Pandarie',
                            'Cataclysm' => 'Cataclysm',
                            'Northrend' => 'Norfendre',
                            'Outland' => 'Outreterre',
                            'World' => 'Mondial',
                        ];
                        $cat = $manualCatMap[$cat] ?? $cat;
                        // Translate category if possible
                        $currentCategory = $frenchAchievementNames[$cat] ?? $cat;
                    }
                }

                if (preg_match('/(\d+),\s*--\s*(.+)/', $line, $idMatch)) {
                    $id = (int)$idMatch[1];
                    $enName = trim($idMatch[2]);
                    $frName = $frenchAchievementNames[$enName] ?? $enName;

                    if (!isset($expansionData['categories'][$currentCategory])) {
                        $expansionData['categories'][$currentCategory] = ['ids' => [], 'names' => []];
                    }

                    $expansionData['categories'][$currentCategory]['ids'][] = $id;
                    $expansionData['categories'][$currentCategory]['names'][$id] = $frName;
                    $expansionData['total_ids'][] = $id;
                }
            }

            $expansionData['total_ids'] = array_values(array_unique($expansionData['total_ids']));
            $achievements[$expansionId] = $expansionData;
        }

        File::put(base_path(self::OUTPUT_PATH . '/achievements.json'), json_encode($achievements, JSON_PRETTY_PRINT));
    }

    private function processQuests(): void
    {
        $this->info('Processing Quests (BtWQuests)...');
        $quests = [];

        $btwMap = [
            'BtWQuestsClassic' => ExpansionId::CLASSIC,
            'BtWQuestsTheBurningCrusade' => ExpansionId::BURNING_CRUSADE,
            'BtWQuestsWrathOfTheLichKing' => ExpansionId::WRATH_OF_THE_LICH_KING,
            'BtWQuestsCataclysm' => ExpansionId::CATACLYSM,
            'BtWQuestsMistsOfPandaria' => ExpansionId::MISTS_OF_PANDARIA,
            'BtWQuestsWarlordsOfDraenor' => ExpansionId::WARLORDS_OF_DRAENOR,
            'BtWQuestsLegion' => ExpansionId::LEGION,
            'BtWQuestsBattleForAzeroth' => ExpansionId::BATTLE_FOR_AZEROTH,
            'BtWQuestsShadowlands' => ExpansionId::SHADOWLANDS,
            'BtWQuestsDragonflight' => ExpansionId::DRAGONFLIGHT,
            'BtWQuestsTheWarWithin' => ExpansionId::THE_WAR_WITHIN,
        ];

        foreach ($btwMap as $folder => $expansionId) {
            $folderPath = base_path(self::BASE_PATH . "/BTW/{$folder}");

            if (!File::isDirectory($folderPath)) {
                continue;
            }

            $expansionData = [
                'total_ids' => [],
                'zones' => []
            ];

            // Load French names as a flat map for this expansion
            $frenchNames = [];
            $frFile = $folderPath . "/Database/Quests.frFR.lua";
            if (File::exists($frFile)) {
                $frContent = File::get($frFile);
                preg_match_all('/\[(\d+)\]\s*=\s*{\s*name\s*=\s*"([^"]+)"/', $frContent, $nameMatches);
                foreach ($nameMatches[1] as $index => $id) {
                    $frenchNames[(int)$id] = $nameMatches[2][$index];
                }
            }

            foreach (File::allFiles($folderPath) as $file) {
                if ($file->getExtension() !== 'lua' || str_contains($file->getPathname(), '/Database/') || str_contains($file->getPathname(), '/Localization.'))
                    continue;

                $enZoneName = $file->getBasename('.lua');
                if ($enZoneName === 'Defines' || $enZoneName === 'General')
                    continue;

                // Filter out version-numbered files (e.g., 11.1, 11.2.7)
                if (preg_match('/^\d+(\.\d+)*$/', $enZoneName)) {
                    $frZoneName = 'Général / Patchs';
                }
                else {
                    $frZoneName = $this->translateZoneName($enZoneName);
                }

                $content = File::get($file->getRealPath());

                $zoneIds = [];
                preg_match_all('/id\s*=\s*(\d+)/', $content, $matches);
                foreach ($matches[1] as $id) {
                    $zoneIds[] = (int)$id;
                }

                preg_match_all('/ids\s*=\s*\{\s*([\d\s,]+)\s*\}/', $content, $listMatches);
                foreach ($listMatches[1] as $list) {
                    $listIds = explode(',', $list);
                    foreach ($listIds as $id) {
                        $id = trim($id);
                        if (is_numeric($id)) {
                            $zoneIds[] = (int)$id;
                        }
                    }
                }

                $zoneIds = array_values(array_unique($zoneIds));
                if (!empty($zoneIds)) {
                    $expansionData['zones'][$frZoneName] = [
                        'ids' => $zoneIds,
                        'names' => array_intersect_key($frenchNames, array_flip($zoneIds))
                    ];
                    $expansionData['total_ids'] = array_merge($expansionData['total_ids'], $zoneIds);
                }
            }

            $expansionData['total_ids'] = array_values(array_unique($expansionData['total_ids']));
            $quests[$expansionId] = $expansionData;
        }

        File::put(base_path(self::OUTPUT_PATH . '/quests.json'), json_encode($quests, JSON_PRETTY_PRINT));
    }

    private function translateZoneName(string $name): string
    {
        $map = [
            'AzjKahet' => 'Azj-Kahet',
            'Hallowfall' => 'Sainte-Chute',
            'IsleOfDorn' => 'Île de Dorn',
            'TheRingingDeeps' => 'Les Abîmes Retentissants',
            'SirenIsle' => 'Île aux Sirènes',
            'TheWakingShores' => 'Le Rivage de l\'Éveil',
            'OhnahranPlains' => 'Plaines d\'Ohn\'ahra',
            'TheAzureSpan' => 'La Travée d\'Azur',
            'Thaldraszus' => 'Thaldraszus',
            'ForbiddenReach' => 'Confins Interdits',
            'ZaralekCavern' => 'Grotte de Zaralek',
            'EmeraldDream' => 'Le Rêve d\'Émeraude',
            'Bastion' => 'Bastion',
            'Maldraxxus' => 'Maldraxxus',
            'Ardenweald' => 'Sylvarden',
            'Revendreth' => 'Revendreth',
            'Korthia' => 'Korthia',
            'ZerethMortis' => 'Zereth Mortis',
            'Nazjatar' => 'Nazjatar',
            'Mechagon' => 'Mécagone',
            'StormsongValley' => 'Vallée de Chantorage',
            'Drustvar' => 'Drustvar',
            'TiragardeSound' => 'Rade de Tiragarde',
            'Zuldazar' => 'Zuldazar',
            'Nazmir' => 'Nazmir',
            'Voldun' => 'Vol\'dun',
            'Suramar' => 'Suramar',
            'Highmountain' => 'Haut-Roc',
            'Stormheim' => 'Tornheim',
            'Valsharah' => 'Val\'sharah',
            'Azsuna' => 'Azsuna',
            'ShadowmoonValley' => 'Vallée d\'Ombrelune',
            'FrostfireRidge' => 'Crête de Givrefeu',
            'Gorgrond' => 'Gorgrond',
            'Talador' => 'Talador',
            'SpiresOfArak' => 'Flèches d\'Arak',
            'Nagrand' => 'Nagrand',
            'TanaanJungle' => 'Jungle de Tanaan',
            'TheJadeForest' => 'La forêt de Jade',
            'ValleyOfTheFourWinds' => 'Vallée des Quatre vents',
            'KrasarangWilds' => 'Étendues sauvages de Krasarang',
            'KunLaiSummit' => 'Sommet de Kun-Lai',
            'TownlongSteppes' => 'Steppes de Tanglong',
            'DreadWastes' => 'Terres de l\'Angoisse',
            'ValeOfEternalBlossoms' => 'Val de l\'Éternel printemps',
            'MountHyjal' => 'Mont Hyjal',
            'Vashjir' => 'Vashj\'ir',
            'Deepholm' => 'Le Tréfonds',
            'Uldum' => 'Uldum',
            'TwilightHighlands' => 'Hautes-terres du Crépuscule',
            'BoreanTundra' => 'Toundra Boréenne',
            'HowlingFjord' => 'Fjord Hurlant',
            'Dragonblight' => 'Désolation des dragons',
            'GrizzlyHills' => 'Les Grisonnes',
            'ZulDrak' => 'Zul\'Drak',
            'SholazarBasin' => 'Bassin de Sholazar',
            'TheStormPeaks' => 'Les Pics Foudroyés',
            'Icecrown' => 'La Couronne de glace',
            'HellfirePeninsula' => 'Péninsule des Flammes infernales',
            'Zangarmarsh' => 'Marécage de Zangar',
            'TerokkarForest' => 'Forêt de Terokkar',
            'BladeEdgeMountains' => 'Les Tranchantes',
            'Netherstorm' => 'Raz-de-Néant',
        ];

        return $map[$name] ?? preg_replace('/(?<!^)([A-Z])/', ' $1', $name);
    }
}