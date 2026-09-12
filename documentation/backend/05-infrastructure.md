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

### `BlizzardNamespace`

Lecture de l'en-tête `battlenet-namespace`, renvoyé par Blizzard sur chaque réponse. Les namespaces versionnés ont la forme `static-12.1.0_68914-eu`, dont le segment central identifie le build WoW servi. Un namespace sans build — `profile-eu` — n'en rend aucun plutôt qu'une valeur approchante.

C'est le levier le moins coûteux du pipeline d'import : les données `static` ne changent qu'au patch, donc comparer ce build à celui du dernier import rend la plupart des réimports inutiles.

---

### `ImportBuildGate`

Décide s'il y a lieu de réimporter une entité, en comparant le build servi par l'API à celui du dernier import réussi.

| Méthode | Retour | Description |
|---|---|---|
| `isUpToDate(string $entity, ?string $currentBuild)` | `bool` | Faux si l'entité n'a jamais été importée, si son build diffère, ou si le build courant est inconnu — dans le doute on importe. |
| `remember(string $entity, string $build, ?string $lastModified)` | `void` | Enregistre le build d'un import réussi. |
| `lastModifiedFor(string $entity)` | `string\|null` | Date à renvoyer en `If-Modified-Since` à la prochaine revalidation. |

L'état vit dans `wow_import_states`, **en base et non en cache** : c'est un état d'import, il doit survivre à un `cache:clear`. Il est tenu **par entité et non globalement**, parce qu'un patch peut ne toucher que les recettes et que réimporter les 22 000 apparences pour autant serait absurde.

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

Mutualisé par les importeurs spécialisés. Fournit les mécanismes de requêtes asynchrones avec reprise sur erreur.

`fetchBatchAsync()` maintient une **concurrence constante** : dès qu'une requête se termine, la suivante part, au lieu d'attendre le traînard d'un lot. Les promesses sont produites par un générateur, donc créées au fur et à mesure et non toutes d'avance — c'est ce qui borne la mémoire sur les gros balayages.

La régulation par seconde n'est pas de son ressort : elle appartient à `RateLimitingMiddleware`, seul endroit où elle est correcte. Le nombre de requêtes en vol se règle par `BLIZZARD_IMPORT_CONCURRENCY`, 20 par défaut.

Une réponse 429 fait redescendre la concurrence de moitié à chaque tentative, plancher à 5, et le journal le dit. Un 304 est compté comme inchangé, jamais comme un échec.

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

Middleware Guzzle qui limite les requêtes à 80/seconde en insérant des pauses (`BACKOFF_US = 50 000 µs`). C'est le point de passage unique de tous les appels Blizzard, site et imports confondus, et c'est là que chaque requête est comptée au budget horaire.

---

### `HourlyBudgetGuard`

Second garde-fou, complémentaire du précédent : là où le middleware régule la seconde, celui-ci tient une fenêtre glissante d'une heure sur le quota Blizzard de 36 000 requêtes. `HOURLY_LIMIT` est fixé à 34 000, marge de sécurité comprise.

Un compteur par minute dans Redis, incrémenté par `INCRBY` et détruit par son propre TTL. Consommer ne lit rien : deux processus qui comptent en même temps — le worker et une requête du site — s'additionnent au lieu de s'écraser. La lecture se fait par un `MGET` des soixante clés de la fenêtre, et n'est nécessaire qu'au moment de décider d'attendre.

| Méthode | Retour | Description |
|---|---|---|
| `consume(int $count)` | `void` | Compte des requêtes. Un lot coûte une opération, pas une par requête. |
| `secondsUntilAvailable(int $count, ?int $ceiling)` | `int` | Secondes à attendre avant de pouvoir consommer `$count` sous le plafond. Les imports passent un plafond réservé, inférieur à `HOURLY_LIMIT`, pour laisser de la marge au trafic du site. |
| `usedInWindow()` | `int` | Appels comptés sur l'heure glissante. |

**Le budget a son propre index Redis**, distinct de celui du cache. Ce n'est pas cosmétique : `cache:clear` émet un `FLUSHDB`, et remettre ce compteur à zéro autoriserait un import à consommer un second quota dans la même heure réelle, alors que Blizzard, lui, ne réinitialise rien.

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

---

## Couverture de documentation (`app/Infrastructure/Documentation/`)

Outillage de la commande `docs:coverage`, décrite dans [Commandes Artisan](09-commands.md).

### `DocumentationCoverage`

Fonction pure : une liste de noms de classes, le texte des pages et une liste d'exclusions entrent, un rapport sort. Aucun accès disque — le parcours des répertoires appartient à la commande, ce qui rend le calcul testable sans monter d'arborescence.

Une classe est reconnue quand son nom court ouvre une portion entre backticks. La correspondance porte sur le jeton entier, sans quoi `CharacterMedia` satisferait `Character` et la mesure ne voudrait plus rien dire.

### `CoverageReport`

Objet en lecture seule portant le résultat : les noms complets des classes manquantes, le nombre de documentées, la taille du périmètre et le nombre d'exclusions.

| Méthode | Retour | Description |
|---|---|---|
| `percentage()` | `float` | Part documentée du périmètre. Un périmètre vide vaut 100 %, l'absence de classe à décrire n'étant pas un échec. |
| `missingByLayer()` | `array<string, list<string>>` | Classes manquantes groupées par couche, pour une sortie directement exploitable comme liste de travail. |
