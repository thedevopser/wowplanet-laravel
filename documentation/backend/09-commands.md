# Commandes Artisan

Toutes les commandes s'exécutent via Docker :
```bash
docker compose exec app php artisan <commande>
```

---

## `app:download-db2`

Télécharge les fichiers CSV DB2 depuis [wago.tools](https://wago.tools) et les JSON SimpleArmory. Les fichiers sont sauvegardés dans `storage/app/blizzard/`.

**Signature** : `app:download-db2 {--table=}`

**Option** : `--table=NomTable` pour télécharger une seule table (ex. : `--table=AreaTable`)

**Fichiers DB2 téléchargés** (16 tables)

| Table Wago | Fichier local | Locale |
|---|---|---|
| `AreaTable` | `area_table.csv` | `frFR` |
| `Map` | `map.csv` | — |
| `ContentTuning` | `content_tuning.csv` | — |
| `QuestV2CliTask` | `quest_v2_cli_task.csv` | `frFR` |
| `SkillLineAbility` | `skill_line_ability.csv` | — |
| `Faction` | `faction.csv` | `frFR` |
| `Mount` | `mount.csv` | `frFR` |
| `BattlePetSpecies` | `battle_pet_species.csv` | `frFR` |
| `Achievement` | `achievement.csv` | `frFR` |
| `HouseDecor` | `housetdecor.csv` | `frFR` |
| `QuestPOIBlob` | `quest_poi_blob.csv` | — |
| `UiMap` | `ui_map.csv` | `frFR` |
| `SpellName` | `spell_name.csv` | `frFR` |
| `SkillLine` | `skill_line.csv` | `frFR` |
| `TradeSkillCategory` | `trade_skill_category.csv` | `frFR` |
| `CurrencyTypes` | `currency_types.csv` | — |

**Fichiers SimpleArmory téléchargés** : `achievements.json`, `mounts.json`, `pets.json`, `decors.json`

---

## `app:wow-data-import`

Enchaîne les huit étapes d'un import complet, du socle de référence à la garde-robe, et rend un rapport par étape : lignes créées, mises à jour et supprimées, appels API consommés, durée. Écrit par `upsert` — une ligne absente du catalogue servi par l'API est supprimée par le balayage de rebut de chaque importer, jamais par une troncature.

**Signature** : `app:wow-data-import {--type=all} {--force} {--full} {--limit=}` — **Classe** : `WowDataImportCommand`

| Option | Rôle |
|---|---|
| `--type` | `all` (défaut) ou une étape seule : `reference`, `achievements`, `quests`, `professions`, `mounts`, `pets`, `decor`, `appearances` |
| `--force` | Réimporte même si le build WoW n'a pas changé depuis le dernier import |
| `--full` | Garde-robe : rafraîchit toutes les icônes au lieu des seules manquantes |
| `--limit` | Borne le nombre de fenêtres balayées par passe, pour un smoke-test sans consommer le quota |

**Ordre des opérations** et détail du pipeline : voir [Orchestration de l'import](11-import.md). Les étapes déjà à jour pour le build courant sont ignorées, une étape qui échoue n'interrompt pas les suivantes, et la commande sort en échec si l'une d'elles a échoué.

Le panneau d'administration lance exactement la même chaîne via `RunImportJob`, qui rend la main entre deux étapes au lieu de dormir.

---

## `app:wow-data-refresh`

Comme `app:wow-data-import` mais **tronque** les tables avant de réimporter. Demande confirmation interactive sauf avec `--force`.

**Signature** : `app:wow-data-refresh {--type=all} {--force}`

> À utiliser uniquement quand les données sont corrompues ou lors d'une mise à jour majeure de patch WoW.

---

## `app:wow-quest-faction-tag`

Identifie les paires de quêtes miroirs (même nom + même zone, factions différentes) en interrogeant les récompenses de réputation via l'API Blizzard.

**Signature** : `app:wow-quest-faction-tag`

Cette commande est appelée automatiquement à la fin de l'étape des quêtes de `app:wow-data-import`.

---

## `app:generate-favicons`

Génère tous les formats de favicon à partir de `public/images/logo.png`.

**Signature** : `app:generate-favicons`

**Fichiers générés dans `public/`**

| Fichier | Taille |
|---|---|
| `favicon-16x16.png` | 16×16 |
| `favicon-32x32.png` | 32×32 |
| `apple-touch-icon.png` | 180×180 |
| `mstile-150x150.png` | 150×150 |
| `android-chrome-192x192.png` | 192×192 |
| `android-chrome-512x512.png` | 512×512 |
| `favicon.ico` | Multi-taille (16+32+48) |

Supporte les sources PNG, JPEG et WebP. Utilise l'extension GD de PHP.

---

## `app:ping-search-engines`

Notifie les moteurs de recherche de l'existence du sitemap.

**Signature** : `app:ping-search-engines`

- **Bing** : ping via `https://www.bing.com/ping?sitemap=<url>`
- **Google** : affiche les instructions de soumission manuelle (Google a supprimé le ping automatique en 2023)

---

## `app:wow-reference-sync`

Charge les tables de référence DB2 depuis wago.tools dans les tables `wow_ref_*`.

**Signature** : `app:wow-reference-sync {--table=}` — **Classe** : `WowReferenceSyncCommand`

Elle lit le build LIVE, télécharge les huit tables du `ReferenceCatalog`, les écrit dans le magasin `storage/app/wow-reference/` puis les charge par `COPY`. La sortie rend la volumétrie table par table, avec l'écart au chargement précédent.

`SpellMisc` pèse à elle seule 45 Mo pour 417 583 lignes, contre une quarantaine de milliers pour les six premières : c'est le prix de l'icône des montures, que l'API n'expose sur aucun endpoint.

Rien n'est écrit en base avant que **tous** les téléchargements ne soient acquis, et le chargement tient dans une seule transaction. Un téléchargement refusé, un fichier sans en-tête, une colonne disparue ou une volumétrie effondrée sous la moitié du dernier chargement interrompent la commande sans toucher au socle existant : un socle à moitié chargé est pire qu'un socle périmé.

`--table` restreint la synchronisation à une seule table DB2, désignée par son nom chez wago.

Elle remplace `app:download-db2` pour la partie DB2. Le détail des classes est dans [Couche Infrastructure](05-infrastructure.md).

---

## `app:collection-taxonomy-sync`

Charge la taxonomie curée des montures, mascottes et décorations dans `wow_collection_taxonomy`.

**Signature** : `app:collection-taxonomy-sync {--entity=}` — **Classe** : `CollectionTaxonomySyncCommand`

La même commande sert à l'amorçage et au rafraîchissement : le chargement étant additif, la première exécution amorce une taxonomie vide et les suivantes n'ajoutent que les entrées d'un nouveau patch. **Aucune valeur déjà en base n'est réécrite**, de sorte qu'un arbitrage manuel survit à autant de rafraîchissements qu'on voudra.

Les trois collections sont chargées dans une seule transaction : un fichier curé manquant laisse la taxonomie exactement dans l'état où elle était, plutôt qu'à moitié amorcée. La sortie rend, par collection, le nombre d'entrées curées lues, le total en base et le nombre d'ajouts.

`--entity` restreint la synchronisation à une collection (`mount`, `pet` ou `decor`).

Elle n'est pas dans le chemin d'un import : elle sert à reconstruire la taxonomie de zéro et à intégrer les entrées d'un nouveau patch.

---

## `app:collection-taxonomy-report`

Liste les entrées de catalogue que la taxonomie ne range pas encore.

**Signature** : `app:collection-taxonomy-report {--entity=} {--limit=20}` — **Classe** : `CollectionTaxonomyReportCommand`

Le rapport n'est pas stocké : l'absence de ligne de taxonomie pour une ligne de catalogue *est* le rapport, et une jointure gauche le reconstitue à tout moment. Une entrée rangée nulle part en connaissance de cause porte une ligne de taxonomie aux deux libellés nuls : elle est curée, donc hors de ce rapport.

La sortie rend, par collection, le nombre d'entrées à arbitrer sur le total du catalogue, puis les premières par identifiant. `--limit` règle ce détail, `--entity` restreint à une collection.

---

## `docs:coverage`

Mesure la part du code nommée dans les pages de `documentation/`, et échoue quand elle recule.

**Signature** : `docs:coverage` — **Classe** : `DocsCoverageCommand`

Elle rend le pourcentage de couverture, puis la liste des classes documentées nulle part, groupées par couche. Son code de sortie ne dépend pas du pourcentage mais d'un **compte** : elle échoue dès que le nombre de classes non documentées dépasse le plafond de `config/documentation.php`.

Le plafond ne remonte jamais. Une classe neuve non documentée le fait monter d'un, et le pipeline passe au rouge — c'est tout l'objet de la commande. Un ratio, lui, aurait aussi bougé en supprimant une classe documentée, et aurait donc échoué sans faute.

| Clé de `config/documentation.php` | Rôle |
|---|---|
| `source_paths` | Répertoires parcourus, chacun enraciné sur le namespace `App\`. |
| `pages_path` | Arborescence Markdown fouillée, sous-répertoires compris. |
| `exclude` | Classes hors périmètre, chacune avec la raison qui l'en sort. |
| `max_undocumented` | Plafond de classes tolérées sans page. |

Une classe compte comme documentée quand son nom court ouvre une portion entre backticks. La correspondance porte sur le jeton entier : `CharacterMedia` ne satisfait pas `Character`.

La commande ne modifie aucun fichier, et une étape bloquante du pipeline l'exécute. Voir [Qualité et CI](10-qualite-ci.md).
