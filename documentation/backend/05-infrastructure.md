# Couche Infrastructure

Adaptateurs techniques : client API Blizzard, parsers de fichiers DB2/CSV/JSON, mappings statiques d'extensions. Cette couche isole les détails d'implémentation du domaine métier.

---

## API Blizzard (`app/Infrastructure/Blizzard/`)

### `BlizzardApiClient`

Client HTTP vers l'API Blizzard (OAuth2 client credentials). Gère automatiquement l'obtention et le renouvellement du token d'accès.

**Méthodes**

| Méthode | Paramètres | Retour | Description |
|---|---|---|---|
| `getAccessToken()` | — | `string` | Retourne le token d'accès, le renouvelle si expiré. |
| `get()` | `string $endpoint, array $query` | `array<string, mixed>` | Requête GET synchrone avec token application. |
| `getWithUserToken()` | `string $endpoint, string $userToken, array $query` | `array<string, mixed>` | Requête GET avec token utilisateur (OAuth2 user flow). |
| `getAsync()` | `string $endpoint, array $query` | `PromiseInterface` | Requête GET asynchrone (Guzzle promise). |
| `getClient()` | — | `Client` | Retourne le client Guzzle sous-jacent. |
| `getRegion()` | — | `string` | Région configurée (ex. : `eu`). |
| `getCurrentMythicSeasonId()` | — | `int` | ID de la saison Clé mythique+ courante. |

---

### `BlizzardBatchImporter`

Façade qui délègue chaque type d'import à l'importeur spécialisé correspondant.

**Méthodes**

| Méthode | Description |
|---|---|
| `importAchievements()` | Lance l'import des hauts-faits. |
| `importQuests(array $areaExpansionMap, ...)` | Lance l'import des quêtes avec les cartes d'expansion fournies. |
| `importMounts()` | Lance l'import des montures. |
| `importPets(array $spellNameMap)` | Lance l'import des mascottes. |
| `importDecor()` | Lance l'import des décorations. |
| `importProfessions(array $spellNameMap, array $recipeFactionMap)` | Lance l'import des professions et recettes. |
| `tagMirrorQuestFactions(array $reputationFactionMap)` | Identifie et tague les quêtes miroirs Alliance/Horde. |
| `tagMirrorRecipeFactions()` | Identifie et tague les recettes miroirs Alliance/Horde. |

---

### Trait `ImportsFromBlizzardApi`

Mutualisé par les importeurs spécialisés. Fournit les mécanismes de requêtes batch asynchrones avec rate limiting et retry.

**Constantes**

| Constante | Valeur | Description |
|---|---|---|
| `RATE_LIMIT_WAIT_S` | `10` | Attente en secondes lors d'un 429. |
| `MAX_RETRIES` | `5` | Tentatives max par requête. |
| `CONCURRENT_BATCH_SIZE` | `20` | Requêtes simultanées par batch. |
| `MAX_RATE_LIMIT_RETRIES` | `3` | Retry max sur 429. |
| `MAX_SERVER_ERROR_RETRIES` | `1` | Retry max sur 5xx. |

**Méthodes protégées**

| Méthode | Description |
|---|---|
| `fetchWithRetry(string $endpoint, int $attempt)` | Requête avec retry automatique. |
| `fetchBatchAsync(array $endpoints, int $batchSize)` | Exécute un batch de requêtes en parallèle. Retourne `array<key, result\|null>`. |

---

### `RateLimitingMiddleware`

Middleware Guzzle qui limite les requêtes à 80/seconde en insérant des pauses (`BACKOFF_US = 50 000 µs`).

---

### `ExpansionTierMatcher`

Utilitaire statique qui mappe les noms de tier de réputation français/anglais à un ID d'extension (0–11).

**Méthode**

```
ExpansionTierMatcher::match(string $name): ?int
```

---

### Importeurs spécialisés (`Blizzard/Importers/`)

Chaque importeur lit les données sources, les transforme et les sauvegarde via `upsert`.

| Classe | Source principale | Modèle cible |
|---|---|---|
| `AchievementImporter` | `achievements.json` (SimpleArmory) + `achievement.csv` (DB2) | `WowAchievement` |
| `MountImporter` | `mounts.json` + `mount.csv` | `WowMount` |
| `PetImporter` | `pets.json` + `battle_pet_species.csv` | `WowPet` |
| `DecorImporter` | `decors.json` + `house_decor.csv` | `WowDecor` |
| `QuestImporter` | API Blizzard (liste par zone) + DB2 area/quest maps | `WowQuest` |
| `ProfessionImporter` | `skill_line_ability.csv` + API Blizzard | `WowProfession`, `WowRecipe` |

---

### `Blizzard/Support/Db2CsvLoader`

Utilitaire de lecture de fichiers CSV DB2. Les CSV sont stockés dans `storage/app/blizzard/`.

**Méthodes statiques**

| Méthode | Paramètres | Retour |
|---|---|---|
| `loadMap` | `string $filename, int $keyCol, int $valueCol` | `array<int, int>` — carte indexée par position de colonne |
| `loadMapByHeaders` | `string $filename, string $keyHeader, string $valueHeader` | `array<int, int>` — carte indexée par nom d'en-tête (int→int) |
| `loadStringMapByHeaders` | `string $filename, string $keyHeader, string $valueHeader` | `array<int, string>` — carte indexée par nom d'en-tête (int→string) |

---

## Parsers (`app/Infrastructure/Parsers/`)

### `LuaAddonParser`

Façade des parsers de données DB2. Orchestre `Db2AreaExpansionMapper` et `AddonDataParser`.

**Méthodes**

| Méthode | Retour | Description |
|---|---|---|
| `buildAreaExpansionMap()` | `array<int, int>` | Construit la carte `zone_id → expansion_id` depuis les CSV DB2. |
| `getQuestExpansionMap()` | `array<int, int>` | Overrides d'extension pour des quêtes spécifiques. |
| `getQuestFactionMap()` | `array<int, string>` | Faction par quête (depuis bitmask de race). |
| `getZoneFactionMap()` | `array<int, string>` | Faction par zone. |
| `getReputationFactionMap()` | `array<int, string>` | Faction par réputation (pour détection des quêtes miroirs). |
| `getSpellNameMap()` | `array<int, string>` | Noms français des sorts (pour mascottes et recettes). |
| `getRecipeFactionMap()` | `array<int, string>` | Faction par recette. |
| `normalizeApostrophes(string)` | `string` | Normalise les apostrophes typographiques. (statique) |

---

### `AddonDataParser`

Parse les fichiers CSV DB2 pour en extraire des cartes d'expansion et de faction, notamment en décodant les bitmasks de race Alliance/Horde.

**Constantes**

| Constante | Description |
|---|---|
| `ALLIANCE_BITMASK` | Bitmask indiquant une race Alliance |
| `HORDE_BITMASK` | Bitmask indiquant une race Horde |
| `ALLIANCE_RACE_IDS` | IDs des races Alliance |
| `HORDE_RACE_IDS` | IDs des races Horde |
| `STORMWIND_FACTION_ID` | ID de la faction Hurlevent (référence Alliance) |

**Méthodes publiques**

| Méthode | Retour | Description |
|---|---|---|
| `parseQuestCsvFull()` | `array{quests, expansionMap, factionMap}` | Parse le CSV des quêtes complet. |
| `getQuestExpansionMap()` | `array<int, int>` | Carte `quest_id → expansion_id`. |
| `getQuestFactionMap()` | `array<int, string>` | Carte `quest_id → faction`. |
| `getQuestList()` | `list<array{id, name_fr}>` | Liste des quêtes avec noms français. |
| `getRecipeFactionMap()` | `array<int, string>` | Carte `recipe_id → faction`. |
| `getZoneFactionMap()` | `array<int, string>` | Carte `zone_id → faction`. |
| `getReputationFactionMap()` | `array<int, string>` | Carte `faction_id → Alliance\|Horde`. |
| `getZoneExpansionMap()` | `array<string, int>` | Carte `zone_name → expansion_id`. |

---

### `Db2AreaExpansionMapper`

Détermine l'extension d'une zone en remontant la hiérarchie des zones (zone → continent → expansion) à travers les CSV `area_table.csv`, `map.csv` et `content_tuning.csv`.

**Méthode principale** : `build(): array<int, int>`

Constante `AREA_EXPANSION_OVERRIDES` : corrections manuelles pour les zones mal classées automatiquement.

---

### `Db2FactionExpansionMapper`

Détermine l'extension d'une faction de réputation et calcule le niveau de renom maximum.

**Méthodes**

| Méthode | Retour | Description |
|---|---|---|
| `build()` | `array<int, int>` | Carte `faction_id → expansion_id`. |
| `buildFactionNamesMap()` | `array<int, string>` | Carte `faction_id → name_fr`. |
| `buildMaxRenownMap()` | `array<int, int>` | Carte `faction_id → max_renown_level`. |
| `buildAccountWideFactionIds()` | `array<int, true>` | Ensemble des IDs de factions valables sur tout le compte. |

---

### `Db2ProfessionMapper`

Construit la structure complète professions + recettes depuis les CSV DB2.

**Méthode principale** : `build(array $spellNameMap): array{professions, recipes}` (statique)

Constante `SECONDARY_PROFESSION_IDS` = `[185, 356, 794]`.

---

### `Db2QuestZoneMapper`

Associe chaque quête à une zone à partir des CSV `quest_poi_blob.csv` et `ui_map.csv`.

**Méthode principale** : `build(): array<int, string>` (statique) — retourne `quest_id → zone_name`.

---

### `SimpleArmoryParser`

Parse les fichiers JSON de [SimpleArmory](https://simplearmory.com) (mounts, pets, achievements, decors).

**Constante** : `ICON_BASE_URL = 'https://wow.zamimg.com/images/wow/icons/medium/'`

**Méthodes statiques**

| Méthode | Description |
|---|---|
| `parseAchievements()` | Parse `achievements.json`, retourne `array<int, array{category, subcategory, expansion_id, icon, points, faction}>`. |
| `parseCollection(string $filename)` | Parse `mounts.json`, `pets.json` ou `decors.json`. |
| `buildIconUrl(string $iconName)` | Construit l'URL d'icône Wowhead. |
| `resolveExpansionId(string $categoryName)` | Résout l'extension depuis le nom de catégorie SimpleArmory. |

---

## Mappings (`app/Infrastructure/Mappings/`)

### `ExpansionMapping` (interface)

Contrat pour accéder aux mappings statiques zones/quêtes/hauts-faits par extension.

| Méthode | Description |
|---|---|
| `getZoneMapping()` | `zone_id → expansion_id` |
| `getQuestMapping()` | `quest_id → expansion_id` (overrides manuels) |
| `getAchievementCategoryMapping()` | `category_id → expansion_id` |
| `getAchievementMapping()` | `achievement_id → expansion_id` (overrides) |
| `getMasterList(int $expansionId, string $type)` | Liste d'IDs d'une extension pour un type donné |
| `getQuestsByExpansion(int $expansionId)` | Structure de progression des quêtes par extension |
| `getAchievementsByExpansion(int $expansionId)` | Structure de progression des hauts-faits par extension |

### `StaticExpansionMapping`

Implémentation concrète de `ExpansionMapping` basée sur des tableaux PHP statiques chargés depuis `storage/app/blizzard/`. Met en cache les structures en mémoire (lazy loading via propriétés nullable).
