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

### `SearchIdWindow`

La grille de fenêtres d'identifiants, partagée par tous les balayages de recherche. Une fenêtre est un index `w` désignant l'intervalle `[w × 1000, w × 1000 + 999]` ; mille identifiants tenant toujours sous la page maximale de l'API, une fenêtre ne pagine jamais. La grille est fixe, ce qui permet de désigner une fenêtre par un entier et donc de reprendre un balayage interrompu — et de réutiliser le même offset d'une passe à la suivante.

| Méthode | Retour | Description |
|---|---|---|
| `countFor(int $highestId)` | `int` | Nombre de fenêtres couvrant les identifiants jusqu'à celui-ci. |
| `holding(int $id)` | `int` | Index de la fenêtre dont l'intervalle contient cet identifiant. |
| `query(int $window)` | `string` | Paramètres de recherche de la fenêtre, à concaténer à un endpoint. |

Un identifiant ou un index négatif lève `InvalidArgumentException` : c'est un appelant qui s'est trompé, pas une donnée à corriger silencieusement.

Le trait `SweepsIdWindows` porte la boucle commune aux balayages — construire les endpoints, lancer le lot, réduire chaque réponse puis la libérer aussitôt. Une réponse de recherche porte toutes les locales quelle que soit celle demandée, soit jusqu'à 1,2 Mo par fenêtre : garder les corps décodés d'un lot entier multiplierait le pic mémoire par le nombre de fenêtres.

---

### `ItemSearchSweep`

Balayage du catalogue d'items sur cette grille. Un document de recherche d'item porte déjà nom, qualité, media et apparences, là où le détail unitaire demandait un appel par apparence.

| Méthode | Retour | Description |
|---|---|---|
| `windowCountFor(int $highestId)` | `int` | Nombre de fenêtres couvrant le catalogue jusqu'à cet identifiant. |
| `highestItemId()` | `?int` | Borne du balayage, lue sur une recherche triée par identifiant décroissant. |
| `sweepItems(array $windows, callable $onDocument)` | `void` | Balaie des fenêtres de `data/wow/search/item` et remet chaque document à l'appelant. |
| `sweepItemMedia(array $windows)` | `array<int, MediaSearchDocument>` | Icônes des items de ces fenêtres, indexées par identifiant de media. |

L'appelant choisit combien de fenêtres il traite d'un coup : c'est ce nombre qui fixe le pic mémoire (`services.blizzard.appearance_window_batch`, 5 par défaut).

---

### `MediaSearchSweep`

Balayage des media sur la même grille : une fenêtre rend l'icône de tout ce que l'espace du tag contient dans son intervalle, là où le media unitaire demanderait un appel par entrée.

```
sweep(array $windows, MediaSearchTag $tag): array<int, MediaSearchDocument>
```

`MediaSearchTag` énumère les espaces filtrables — `Item`, `Achievement`. Chacun est son propre espace d'identifiants : un media d'item porte l'identifiant de l'item, un media de haut fait celui du haut fait, et rien ne garantit qu'un identifiant désigne la même chose d'un tag à l'autre.

---

### `CollectionSearchSweep`

Balayage des montures et des décorations sur la même grille.

| Méthode | Retour | Description |
|---|---|---|
| `sweepMounts(array $windows)` | `array<int, MountSearchDocument>` | Montures de ces fenêtres, indexées par identifiant. |
| `sweepDecors(array $windows)` | `array<int, DecorSearchDocument>` | Décorations de ces fenêtres, indexées par identifiant. |

Ces deux recherches portent le même contenu que le détail unitaire correspondant : quatre fenêtres rendent les 1 669 montures et vingt-huit les 2 124 décorations, contre 3 793 appels de détail. Les mascottes n'ont pas d'équivalent — `data/wow/search/pet` répond 404 — et passent donc par leur détail, un appel par mascotte.

Contrairement au balayage d'items, les documents sont petits et les fenêtres peu nombreuses : le résultat tient en mémoire d'un bloc et n'a pas à être remis au fil de l'eau.

---

### `RenderedIconProbe`

```
servedUrls(array $urls): list<string>
```

Contrôle qu'une icône composée est bien servie par le CDN de rendu, et ne rend que celles qui répondent.

Les icônes de montures sont les seules que l'application compose elle-même, à partir du `SpellIconFileDataID` du socle. Or le CDN ne publie pas tous les identifiants de fichier du client : **70 montures sur 1 659 répondent 403**, et aucune variante de taille, de région ou d'icône active ne répond à leur place. Une URL qui échoue est **pire qu'une absence d'URL** — le front rend son gabarit de repli sur un `null`, et une image brisée sur un lien mort.

Ce CDN n'est pas l'API Blizzard et ne consomme pas son quota, mais le contrôle reste borné : les URL en double ne sont demandées qu'une fois, par lots de cinquante, et `MountImporter` ne soumet que celles qui ne figurent pas déjà en base. Une URL déjà écrite a déjà passé le contrôle : en régime stable, une passe n'envoie aucune requête.

---

### Hiérarchie des hauts faits

`AchievementCategorySweep` récupère l'arborescence complète : l'index des catégories, puis le détail de chacune — cent soixante-dix appels aujourd'hui.

```
fetchTaxonomy(): ?AchievementTaxonomy
```

**C'est tout ou rien.** Une catégorie manquante, c'est un pan entier du catalogue absent du lot, que le balayage des lignes périmées supprimerait ensuite : un échec rend `null` et l'importer abandonne sans toucher au catalogue. Les catégories de guilde vivent dans une autre liste de l'index et ne sont pas demandées.

`AchievementTaxonomy` en déduit le rangement, et c'est une fonction pure de la liste des `AchievementCategoryDocument` :

| Méthode | Retour | Description |
|---|---|---|
| `fromCategories(array $categories)` | `self` | Construit la taxonomie (statique). |
| `placements()` | `list<AchievementPlacement>` | Un `AchievementPlacement` par haut fait : `id`, `name`, `categoryName`, `expansionId`. |
| `unrankedCategories()` | `array<string, int>` | Catégories dont rien ne date les hauts faits, et combien chacune en porte. |
| `staleDatingSignals()` | `list<string>` | Signes que la datation par la catégorie se périme. |

**La catégorie racine nomme, la sous-catégorie date.** L'extension est celle du premier ancêtre daté, la racine exclue — aucune racine ne porte de nom d'extension. L'appariement passe par `ExpansionTierMatcher`, sans second mécanisme concurrent.

Ces catégories suivent la sortie du contenu et non la géographie : « Reprise des Chants éternels », situé en Quel'Thalas, est rangé sous Midnight et non sous Royaumes de l'Est. **Une extension ne se déduit donc jamais d'un nom de lieu.** Le garde-fou est posé dans la taxonomie elle-même : une sous-catégorie à nom de continent — `Kalimdor`, `Royaumes de l'Est`, `Outreterre`, `Norfendre` — qui reçoit un haut fait d'identifiant supérieur ou égal à 40 000 sort dans `staleDatingSignals()`, et l'import le signale. Ces quatre catégories sont les seaux Classic de l'onglet Quêtes, et n'ont jamais reçu de contenu récent.

Ce que rien ne date tombe dans `ExpansionId::UNCLASSIFIED`, pas dans l'extension 0, et figure au rapport d'import : un rangement qu'on ignore doit se lire, jamais se découvrir par un tri devenu faux. Un haut fait listé sous deux catégories est arbitré par un ordre total — le rangement daté d'abord, puis la plus petite catégorie — pour qu'un import le range toujours au même endroit.

---

### Documents de recherche (`Blizzard/Responses/`)

Lecture typée des documents rendus par les endpoints de recherche, construite sur `ResponsePayload`. Le `mixed` sorti du décodage JSON s'arrête là.

| Classe | Champs exposés |
|---|---|
| `ItemSearchDocument` | `id`, `nameFr`, `quality` (OverallQualityID numérique), `mediaId`, `categoryFr`, `appearanceIds` |
| `MediaSearchDocument` | `id`, `iconUrl`, `fileDataId` |
| `AchievementCategoryDocument` | `id`, `name`, `parentId`, `achievements` (identifiant → nom) |
| `AchievementDocument` | `id`, `points`, `faction` |
| `MountSearchDocument` | `id`, `nameFr`, `sourceType` |
| `DecorSearchDocument` | `id`, `nameFr`, `itemId` |
| `PetDocument` | `id`, `nameFr`, `iconUrl`, `creatureId`, `sourceType` |

Le nom français tombe sur le nom anglais quand la locale française manque. Un media sans asset `icon` est un cas normal, traité par un repli côté appelant, pas une réponse invalide.

Une catégorie porte ses propres hauts faits **et** des sous-catégories qui portent les leurs : une racine n'est pas un simple conteneur, et « Quêtes » en compte trente-quatre en propre. `AchievementDocument` est réduit aux deux champs que la hiérarchie ne porte pas — les points, et la faction lue dans `requirements.faction.type` : ce sont les seules raisons d'appeler le détail d'un haut fait.

Les trois documents de collection sont réduits de la même façon. `MountSearchDocument` ignore délibérément la faction que le document porte : aucune colonne ne l'accueille. `PetDocument` lit un détail, pas une recherche, donc son nom est du texte et non une carte de locales — et il porte l'icône en clair, ce qu'aucun autre endpoint de collection ne fait.

`TrimmedText::firstNonEmpty(?string ...$candidates)` rend la première valeur utilisable parmi plusieurs, débarrassée de son remplissage. Deux besoins s'y rejoignent : le français manque parfois là où l'américain est rempli, et certains libellés de l'API traînent un CRLF — le haut fait 13503 en est le cas connu.

---

### Importeurs spécialisés (`Blizzard/Importers/`)

Chaque importeur lit les données sources, les transforme et les sauvegarde via `upsert`.

| Classe | Source principale | Modèle cible |
|---|---|---|
| `AchievementImporter` | hiérarchie `achievement-category` + détail de chaque haut fait + balayage des media | `WowAchievement` |
| `MountImporter` | `mount/index` + balayage `search/mount` + taxonomie curée + socle pour le sort et l'icône | `WowMount` |
| `PetImporter` | `pet/index` + détail de chaque mascotte + taxonomie curée | `WowPet` |
| `DecorImporter` | `decor/index` + balayage `search/decor` + balayage des media d'items + taxonomie curée | `WowDecor` |
| `QuestImporter` | API Blizzard (liste par zone) + DB2 area/quest maps | `WowQuest` |
| `ProfessionImporter` | `skill_line_ability.csv` + API Blizzard | `WowProfession`, `WowRecipe` |

Pour les trois collections, le partage d'autorité est explicite : **l'API tranche l'existence**, la **taxonomie curée tranche le rangement**. Une entrée que l'API ignore n'entre pas au catalogue ; une entrée que la taxonomie ne range pas entre avec le type de source de l'API en valeur d'attente, et figure au rapport d'entrées à arbitrer. Voir [Taxonomie des collections](#taxonomie-des-collections-appinfrastructuretaxonomy).

**L'index est obligatoire, l'enrichissement ne l'est pas.** Un index manquant ferait disparaître du lot des entrées que le balayage des lignes périmées supprimerait ensuite : l'import s'interrompt sans toucher au catalogue. Une fenêtre de recherche ou un détail manquant, en revanche, laisse la ligne avec ce qu'elle avait. Une ligne identique à celle déjà en base n'est pas réécrite.

**Les montures sont le seul cas où le socle de référence est indispensable.** L'API n'expose l'icône d'une monture nulle part — `data/wow/media/mount/{id}` répond 404, `search/media?tags=mount` rend zéro résultat, et l'espace de media des sorts est creux —, ni le sort source qui porte son lien Wowhead. `Mount.SourceSpellID` puis `SpellMisc.SpellIconFileDataID` sont le seul chemin, et ils couvrent 1 686 montures sur 1 689.

**Les mascottes sont le seul cas sans endpoint de recherche.** `data/wow/search/pet` répond 404 : leur identité vient du détail, un appel par mascotte. C'est sans regret, ce détail étant le seul des trois à porter l'icône en clair, avec l'identifiant de créature du lien Wowhead.

**Le marqueur d'obtention des décorations est de la curation, jamais de l'API.** L'API atteste qu'une décoration existe, jamais qu'un joueur peut encore l'obtenir : un événement de pré-lancement clos ou une promotion retirée laissent une entrée hors d'atteinte, qui compterait au dénominateur et rendrait le 100 % inatteignable. Il vit donc dans la taxonomie, colonne `obtainable`. Les montures et les mascottes ne le lisent pas : leurs fichiers curés le portent, mais aucun import ne l'a jamais appliqué sur ces deux entités.

---

### `AppearanceImporter`

La garde-robe se construit par balayage du catalogue d'items, sans aucun appel unitaire par apparence. Un document de recherche d'item porte déjà le nom, la qualité, le media, la classe d'objet et les apparences liées : quelques centaines de fenêtres remplacent les 22 000 requêtes de l'ancien pipeline.

Deux autorités, jamais mélangées. Les 18 index de slots de l'`Item Appearance API` disent ce qui est collectionnable ; le balayage dit ce que chaque apparence contient. Une apparence portée par un item mais absente des index n'entre pas au catalogue. Si un seul de ces index ne répond pas, l'import s'interrompt sans rien supprimer — un index partiel effacerait tout un slot. Même posture si la borne du balayage est illisible : une plage devinée manquerait les identifiants les plus hauts, donc le contenu le plus récent.

**Deux passes sur la même grille de fenêtres**, et c'est l'unité de reprise portée par `AppearanceImportProgress` : les `n` premières fenêtres balaient les items, les `n` suivantes les media. Les lignes en base servent d'accumulateur d'une fenêtre à l'autre, ce qui évite de porter quoi que ce soit d'une passe à la suivante.

L'item représentatif d'une apparence est choisi par un ordre total : **meilleure qualité, puis plus petit identifiant d'item**. Le départage par identifiant n'est pas cosmétique — un critère dépendant de l'ordre de parcours ne rendrait pas le même représentant après une reprise. Un changement de représentant remet l'icône à nul, ce qui suffit à la faire reprendre par la passe media ; hors `--full`, cette passe ne vise que les lignes sans icône.

Une ligne identique à ce qui est déjà en base n'est pas réécrite, sans quoi chaque passe toucherait les 22 000 lignes et le mode incrémental ne voudrait plus rien dire.

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

Le rangement des montures, mascottes et décorations — catégorie de niveau 1, source de niveau 2 — est de la curation éditoriale que ni l'API Blizzard ni les DB2 ne portent. L'API n'expose qu'un vocabulaire de douze valeurs de `source.type`, là où la curation compte 170 sources pour les seules montures. Cette taxonomie est donc **notre donnée** : amorcée une fois depuis SimpleArmory, puis enrichie sans jamais être réécrite.

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

Déclare les huit tables DB2 retenues et, pour chacune, les seules colonnes utiles : `Faction`, `ContentTuning`, `AreaTable`, `QuestV2CliTask`, `SkillLineAbility`, `CurrencyTypes`, `Mount`, `SpellMisc`.

`SpellMisc` est la plus lourde du socle — 417 583 lignes pour 45 Mo — et n'est retenue que pour deux colonnes, `SpellID` et `SpellIconFileDataID`. C'est le prix de l'icône des montures, que l'API n'expose nulle part.

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
| `mountSpells()` | `array<int, int>` | `wow_ref_mount`, `SourceSpellID` |
| `mountIcons()` | `array<int, string>` | `wow_ref_mount` joint à `wow_ref_spell_misc` par le sort source |

Les cartes sont construites une fois en début de passe et gardées en mémoire : quelques dizaines de milliers d'entiers ne pèsent rien, là où un aller-retour SQL par quête coûterait la passe entière. Une quête sans titre est ignorée partout, comme le faisait la lecture du CSV : elle n'entre au catalogue sous aucune forme.

Les recettes sont indexées par `SkillLineAbility.ID`, qui est bien l'identifiant de recette que l'API retourne.

L'icône d'une monture se compose à partir de `SpellIconFileDataID` sur le gabarit `https://render.worldofwarcraft.com/{région}/icons/56/{fileDataId}.jpg`, celui-là même que l'API sert pour les mascottes et les hauts faits. Un sort porte parfois plusieurs lignes `SpellMisc`, une par difficulté : la plus petite tranche l'égalité pour que deux imports rendent la même icône.

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
