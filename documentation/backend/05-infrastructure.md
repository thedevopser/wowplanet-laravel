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
| `MountImporter` | `mount/index` (API) + taxonomie curée + `mounts.json` pour l'icône | `WowMount` |
| `PetImporter` | `pet/index` (API) + taxonomie curée + `pets.json` pour l'icône | `WowPet` |
| `DecorImporter` | `decor/index` (API) + taxonomie curée + `decors.json` pour l'icône | `WowDecor` |
| `QuestImporter` | API Blizzard (liste par zone) + DB2 area/quest maps | `WowQuest` |
| `ProfessionImporter` | `skill_line_ability.csv` + API Blizzard | `WowProfession`, `WowRecipe` |

Pour les trois collections, le partage d'autorité est explicite : **l'API tranche l'existence**, la **taxonomie curée tranche le rangement**. Une entrée que l'API ignore n'entre pas au catalogue ; une entrée que la taxonomie ne range pas entre sans catégorie ni source, et figure au rapport d'entrées à arbitrer. Voir [Taxonomie des collections](#taxonomie-des-collections-appinfrastructuretaxonomy).

---

## Parsers (`app/Infrastructure/Parsers/`)

Le parsing de fichiers à l'exécution a disparu : l'extension et la faction viennent du socle,
le rangement des collections de la taxonomie. Il ne reste ici que la lecture des fichiers curés.


### `SimpleArmoryParser`

Parse les fichiers JSON de [SimpleArmory](https://simplearmory.com) (mounts, pets, achievements, decors).

**Constante** : `ICON_BASE_URL = 'https://wow.zamimg.com/images/wow/icons/medium/'`

**Méthodes statiques**

| Méthode | Description |
|---|---|
| `parseAchievements()` | Parse `achievements.json`, retourne `array<int, array{category, subcategory, expansion_id, icon, points, faction}>`. |
| `parseCollection(string $filename)` | Parse `mounts.json`, `pets.json` ou `decors.json`. Un identifiant listé sous plusieurs catégories garde sa **dernière** occurrence. |
| `buildIconUrl(string $iconName)` | Construit l'URL d'icône Wowhead. |
| `resolveExpansionId(string $categoryName)` | Résout l'extension depuis le nom de catégorie SimpleArmory. |

Les fichiers de collection ne servent plus qu'à **amorcer la taxonomie** et à fournir icône et identifiants secondaires. Le rangement affiché, lui, vit en base.

---

## Taxonomie des collections (`app/Infrastructure/Taxonomy/`)

Le rangement des montures, mascottes et décorations — catégorie de niveau 1, source de niveau 2 — est de la curation éditoriale que ni l'API Blizzard ni les DB2 ne portent. L'API n'expose qu'un vocabulaire de onze valeurs de `source.type`, là où la curation compte 170 sources pour les seules montures. Cette taxonomie est donc **notre donnée** : amorcée une fois depuis SimpleArmory, puis enrichie sans jamais être réécrite.

Elle vit dans la table `wow_collection_taxonomy`, hors de la famille `wow_ref_*` pour qu'aucun traitement balayant les tables de référence DB2 ne puisse vider la curation.

### `CollectionEntity`

Énumération des trois collections curées. La valeur de chaque cas (`mount`, `pet`, `decor`) est le discriminant stocké en base : la changer invaliderait la taxonomie existante.

| Méthode | Description |
|---|---|
| `fromOption(string $name): self` | Résout l'entité d'une option de commande, insensible à la casse. Lève `InvalidArgumentException` en listant les entités connues. |
| `simpleArmoryFile(): string` | Nom du fichier curé dont cette collection s'amorce. |

### `TaxonomyEntry`

Objet de valeur immuable portant le rangement d'une entrée : `?string $category` et `?string $source`, en anglais brut, traduits à l'affichage par les onglets de collection. Les deux peuvent être nuls — c'est une entrée rangée nulle part **en connaissance de cause**, à ne pas confondre avec l'absence d'entrée, qui est à arbitrer.

### `CollectionTaxonomyLoader`

Amorçage et enrichissement depuis les fichiers curés.

**Méthode** : `load(CollectionEntity $collectionEntity): array{read: int, inserted: int, skipped: int}`

Le chargement est strictement additif : `insertOrIgnore` laisse en place toute ligne connue, si bien que la première exécution amorce et que les suivantes n'ajoutent que les entrées d'un nouveau patch. C'est ce qui fait survivre un arbitrage manuel. Le dédoublonnage est délégué à `SimpleArmoryParser`, qui garde la dernière occurrence d'un identifiant : c'est ce qu'a fait chaque import jusqu'ici, donc ce qui a produit le rangement en place.

### `CollectionTaxonomyReader`

**Méthode** : `for(CollectionEntity $collectionEntity): array<int, TaxonomyEntry>`

Charge la taxonomie d'une collection d'un seul coup, indexée par identifiant Blizzard. Quelques milliers de lignes de deux libellés coûtent moins qu'une requête par entrée pendant l'import.

### `ApiSourceTypeVocabulary`

Conversion du vocabulaire de source de l'API vers celui de la taxonomie, comme **valeur d'attente** pour une entrée non encore rangée — jamais comme remplacement d'une source curée.

| Méthode | Description |
|---|---|
| `toPendingSource(?string $sourceType): ?string` | Convertit l'un des onze types de l'API ; rend `null` pour un type inconnu plutôt que d'inventer un libellé. |
| `pendingSources(): list<string>` | Les onze libellés produits. |

Les onze libellés existent déjà dans les dictionnaires de traduction des trois onglets de collection, ce qu'un test vérifie. N'en ajouter un douzième qu'en l'ajoutant aussi côté front, sans quoi il s'afficherait en anglais.

### `TaxonomySourceUnavailableException`

Levée quand le fichier curé est absent, illisible ou vide. Un amorçage qui ne trouve rien et se tait laisserait croire la taxonomie à jour alors qu'elle est restée vide.

---

## Mappings (`app/Infrastructure/Mappings/`)

### `FrozenAreaExpansionMap`

Carte `zone → extension` figée dans `database/data/area_expansion_map.json`, versionnée avec le dépôt.

**Méthode** : `load(): array<int, int>` (statique)

Elle survit au socle de référence, et ce n'est pas un oubli : `AreaTable` ne porte pas l'extension d'une zone. Cette carte a été générée une fois en croisant `AreaTable`, `Map` et `ContentTuning`, corrections manuelles comprises, et ce croisement n'est pas reproductible depuis les seules colonnes du socle. Elle ne lit rien dans `storage/app/blizzard/`.

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

---

## Socle de référence DB2 (`app/Infrastructure/Reference/`)

Les correspondances que l'API Blizzard n'expose sur aucun endpoint — extension d'une quête, faction d'une zone, renom d'une réputation — viennent des tables DB2 publiées par wago.tools. Elles sont chargées une fois par patch dans des tables `wow_ref_*` par la commande [`app:wow-reference-sync`](09-commands.md), au lieu d'être reparsées depuis le disque à chaque import.

### `ReferenceCatalog`

Déclare les six tables DB2 retenues et, pour chacune, les seules colonnes utiles : `Faction`, `ContentTuning`, `AreaTable`, `QuestV2CliTask`, `SkillLineAbility`, `CurrencyTypes`.

Les noms de colonnes sources appartiennent à un build donné et changent d'un patch à l'autre. Blizzard a par exemple scindé les masques de race en deux moitiés — `RaceMask` est devenu `RaceMasks_0` et `RaceMasks_1` — le jour où les identifiants de race ont dépassé la largeur d'origine. Une colonne déclarée ici mais absente de la source fait échouer la synchronisation, ce qui est le comportement recherché : c'est le seul moment où un renommage se voit.

### `ReferenceTable`, `ReferenceColumn`, `ReferenceColumnType`

Descripteurs en lecture seule, sans dépendance au framework. `ReferenceTable` porte le nom de la table DB2, son slug, la locale à demander et ses colonnes ; elle en dérive le nom de la table PostgreSQL (`wow_ref_` + slug) et le nom du fichier stocké (slug + build). `ReferenceColumnType` distingue les colonnes numériques des colonnes textuelles, seule information dont le projecteur a besoin pour décider si une cellule vide vaut `NULL` ou chaîne vide.

### `Db2CsvProjector`

Réduit un CSV DB2 aux colonnes déclarées, dans l'ordre de la table cible, et rend un générateur de lignes déjà encodées. Les colonnes sont repérées par nom, un réordonnancement de la source est donc sans effet, et une colonne absente lève `MissingColumnException`.

### `CopyText`

Encodage d'une ligne au format texte de `COPY`. Ce format sépare par tabulation, marque le nul par `\N` et n'accorde aucun sens aux guillemets ni aux virgules : seuls la barre oblique inverse et les caractères de mise en page sont neutralisés, la barre oblique en premier pour ne pas ré-échapper les échappements produits ensuite.

`NULL_MARKER_SQL` existe parce que `copyFromArray()` recopie le marqueur dans la clause `NULL AS '…'` sans l'échapper : une barre oblique simple y est consommée par l'analyseur SQL, et PostgreSQL finit par chercher un nul écrit `N`.

### `ReferenceLoader`

Remplace le contenu d'une table de référence par `COPY`, cinq mille lignes à la fois, sur une connexion `Pdo\Pgsql`. `TRUNCATE` étant transactionnel sur PostgreSQL, un chargement qui casse en cours de route laisse la table telle qu'elle était, sans passer par une table de transit.

### `ReferenceStore`

Magasin des CSV téléchargés, sur le disque `reference` (`storage/app/wow-reference/`), **distinct de `storage/app/blizzard/`**. Le nom de fichier porte le build : deux synchronisations d'un même build écrivent le même fichier, deux builds différents en laissent deux.

### `WagoClient`

Frontière wago.tools. `liveBuild()` lit la version LIVE sur `/api/builds`, `fetch()` télécharge une table sur `/db2/{table}/csv`. Le produit est épinglé sur `wow` dans les deux cas : sans lui, wago sert son dernier build tous produits confondus, souvent un PTR dont la localisation française est incomplète.

### `RaceMask`

Lecture de la faction dans un masque de race DB2.

| Méthode | Retour | Description |
|---|---|---|
| `combine(?int $low, ?int $high)` | `?int` | Recompose le masque complet à partir de ses deux moitiés. |
| `faction(?int $low, ?int $high)` | `?string` | `Alliance`, `Horde`, ou `null` quand le masque ne tranche pas. |

Blizzard a scindé ces masques en deux moitiés de 32 bits le jour où les identifiants de race ont dépassé la largeur d'origine : `FiltRaceMasks_0` / `_1`, `RaceMasks_0` / `_1`, `ReputationRaceMasks0_0` / `_1`. La première porte les bits de poids faible, la seconde ceux de poids fort, et leur recomposition rend exactement les masques complets d'avant la scission.

**Une moitié lue seule ne veut rien dire** : c'est un entier quelconque, souvent négatif, dont l'analyse par bits produit une faction plausible et fausse. Les deux moitiés sont donc toujours exigées ensemble.

---

### `ReferenceMaps`

Les correspondances que l'import tire du socle.

| Méthode | Retour | Source |
|---|---|---|
| `questExpansions()` | `array<int, int>` | `wow_ref_quest_v2_cli_task` joint à `wow_ref_content_tuning` |
| `questFactions()` | `array<int, string>` | `wow_ref_quest_v2_cli_task`, masque de race |
| `recipeFactions()` | `array<int, string>` | `wow_ref_skill_line_ability`, masque de race |
| `zoneFactions()` | `array<int, string>` | `wow_ref_area_table`, `FactionGroupMask` : 2 pour l'Alliance, 4 pour la Horde |

Les cartes sont construites une fois en début de passe et gardées en mémoire : quelques dizaines de milliers d'entiers ne pèsent rien, là où un aller-retour SQL par quête coûterait la passe entière. Une quête sans titre est ignorée partout, comme le faisait la lecture du CSV : elle n'entre au catalogue sous aucune forme.

Les recettes sont indexées par `SkillLineAbility.ID`, qui est bien l'identifiant de recette que l'API retourne.

---

### `FactionReference`

Tout ce que le socle sait des réputations, servi à l'import comme à l'exécution.

| Méthode | Retour | Description |
|---|---|---|
| `expansions()` | `array<int, int>` | Extension d'une réputation, par remontée de la hiérarchie des factions parentes. |
| `names()` | `array<int, string>` | Nom localisé d'une réputation. |
| `maxRenownLevels()` | `array<int, int>` | Renom maximal, via `RenownCurrencyID` et `MaxQty`. |
| `accountWideIds()` | `array<int, true>` | Réputations valables sur tout le compte : renom, amitié, ou extension ≥ Dragonflight. |
| `factions()` | `array<int, string>` | Camp des réputations exclusives à une faction. |

Une réputation est exclusive quand elle porte un plafond pour un camp et pas pour l'autre. Son camp se lit alors par recoupement avec le masque de race de Hurlevent, pris comme référence Alliance : Blizzard ne nomme les camps nulle part.

Les réputations que l'endpoint `/reputations` ne retourne jamais sont exclues — parangon, saisons de gouffres et de traque, entrées `DEPRECATED`, `[DNT]` et `JOUEUR` — sans quoi elles compteraient au dénominateur et rendraient le 100 % inatteignable.

Les lectures sont mémorisées pour la durée de l'instance : l'agrégateur de progression des réputations interroge cinq de ces cartes à chaque profil de personnage.

---

### `ReferenceValue`

Rétrécissement des valeurs qui sortent du socle : `int()`, `nullableInt()` et `string()`. Le constructeur de requêtes rend des objets aux propriétés non typées, et ce `mixed` est converti dès la ligne qui le reçoit plutôt que de traverser le code.

---

### Exceptions

Toutes descendent de `ReferenceSyncException`, ce qui permet à la commande de rattraper la famille entière et de rendre un message plutôt qu'une pile.

| Exception | Levée quand |
|---|---|
| `BuildUnavailableException` | wago ne rend pas de version pour le produit configuré. |
| `DownloadFailedException` | Téléchargement refusé, ou corps vide. |
| `MalformedSourceException` | Fichier servi sans ligne d'en-tête. |
| `MissingColumnException` | Une colonne déclarée a disparu de la source. |
| `TruncatedSourceException` | Volumétrie effondrée sous la moitié du dernier chargement. |
| `UnknownTableException` | `--table` désigne une table absente du catalogue. |
