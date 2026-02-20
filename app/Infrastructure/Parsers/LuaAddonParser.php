<?php

declare(strict_types=1);

namespace App\Infrastructure\Parsers;

use App\Infrastructure\Blizzard\Support\Db2CsvLoader;

class LuaAddonParser
{
    public function __construct(
        private readonly Db2AreaExpansionMapper $db2AreaExpansionMapper,
        private readonly Db2AchievementExpansionMapper $db2AchievementExpansionMapper,
        private readonly AddonDataParser $addonDataParser,
    ) {}

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
     * @return array<int, int> [area_id => expansion_id]
     */
    public function buildAreaExpansionMap(): array
    {
        return $this->db2AreaExpansionMapper->build();
    }

    /**
     * @return array<int, int> [achievement_id => expansion_id]
     */
    public function getAchievementExpansionMap(): array
    {
        return $this->db2AchievementExpansionMapper->build();
    }

    /**
     * @return array<int, int> [quest_id => expansion_id]
     */
    public function getQuestExpansionMap(): array
    {
        return $this->addonDataParser->getQuestExpansionMap();
    }

    /**
     * @return array<int, string> [quest_id => zone_name]
     */
    public function getQuestZoneMap(): array
    {
        return Db2QuestZoneMapper::build();
    }

    /**
     * @return array<int, string> [quest_id => 'Alliance'|'Horde']
     */
    public function getQuestFactionMap(): array
    {
        return $this->addonDataParser->getQuestFactionMap();
    }

    /**
     * @return array<int, string> [recipe_id => 'Alliance'|'Horde']
     */
    public function getRecipeFactionMap(): array
    {
        return $this->addonDataParser->getRecipeFactionMap();
    }

    /**
     * @return array<int, string> [area_id => 'Alliance'|'Horde']
     */
    public function getZoneFactionMap(): array
    {
        return $this->addonDataParser->getZoneFactionMap();
    }

    /**
     * @return array<int, string> [reputation_faction_id => 'Alliance'|'Horde']
     */
    public function getReputationFactionMap(): array
    {
        return $this->addonDataParser->getReputationFactionMap();
    }

    /**
     * @return array<string, int> [lowercase_zone_name => expansion_id]
     */
    public function getZoneExpansionMap(): array
    {
        return $this->addonDataParser->getZoneExpansionMap();
    }

    /**
     * Load spell_id → spell_name map from SpellName.csv.
     *
     * @return array<int, string>
     */
    public function getSpellNameMap(): array
    {
        return Db2CsvLoader::loadStringMapByHeaders('spell_name.csv', 'ID', 'Name_lang');
    }
}
