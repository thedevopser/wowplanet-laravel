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

Importe les données WoW depuis les fichiers locaux (DB2 CSV + SimpleArmory JSON + API Blizzard). Utilise `upsert` — ne supprime pas les données existantes.

**Signature** : `app:wow-data-import {--type=all}`

**Option** `--type` : `all` (défaut), `achievements`, `quests`, `mounts`, `pets`, `professions`, `decor`

**Ordre des opérations** (pour `--type=all`)
1. Hauts-faits (SimpleArmory + DB2 `achievement.csv`)
2. Quêtes (API Blizzard + DB2 area/quest maps + tagage des miroirs)
3. Montures (SimpleArmory + DB2 `mount.csv`)
4. Mascottes (SimpleArmory + DB2 `battle_pet_species.csv`)
5. Professions (DB2 `skill_line_ability.csv` + recettes miroirs)
6. Décorations (SimpleArmory + DB2 `housetdecor.csv`)

> Limite mémoire : 512 Mo (`ini_set('memory_limit', '512M')`)

---

## `app:wow-data-refresh`

Comme `app:wow-data-import` mais **tronque** les tables avant de réimporter. Demande confirmation interactive sauf avec `--force`.

**Signature** : `app:wow-data-refresh {--type=all} {--force}`

> À utiliser uniquement quand les données sont corrompues ou lors d'une mise à jour majeure de patch WoW.

---

## `app:wow-quest-faction-tag`

Identifie les paires de quêtes miroirs (même nom + même zone, factions différentes) en interrogeant les récompenses de réputation via l'API Blizzard.

**Signature** : `app:wow-quest-faction-tag`

Cette commande est appelée automatiquement à la fin de `app:wow-data-import --type=quests`.

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
