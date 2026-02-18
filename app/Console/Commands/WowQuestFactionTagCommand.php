<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Infrastructure\Blizzard\BlizzardBatchImporter;
use App\Infrastructure\Parsers\LuaAddonParser;
use App\Models\WowQuest;
use Illuminate\Console\Command;

class WowQuestFactionTagCommand extends Command
{
    protected $signature = 'app:wow-quest-faction-tag';

    protected $description = 'Tag mirror quest pairs (same name+zone, no faction) by checking Blizzard API reputation rewards';

    public function handle(BlizzardBatchImporter $blizzardBatchImporter, LuaAddonParser $luaAddonParser): void
    {
        $this->info('Building reputation→faction map from Faction.csv...');
        $reputationFactionMap = $luaAddonParser->getReputationFactionMap();
        $this->info(sprintf('  %d reputation factions mapped (Alliance/Horde).', count($reputationFactionMap)));

        $blizzardBatchImporter->tagMirrorQuestFactions($reputationFactionMap);

        $this->newLine();
        $this->table(
            ['Faction', 'Count'],
            [
                ['Alliance', WowQuest::query()->where('faction', 'Alliance')->count()],
                ['Horde', WowQuest::query()->where('faction', 'Horde')->count()],
                ['Neutral (null)', WowQuest::query()->whereNull('faction')->count()],
            ],
        );
    }
}
