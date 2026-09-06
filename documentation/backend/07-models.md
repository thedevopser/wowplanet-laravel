# Modèles Eloquent

Tous les modèles utilisent PostgreSQL. Les modèles WoW ont `$incrementing = false` car leur ID est celui de Blizzard, et n'ont donc pas de séquence associée.

---

## `User`

Modèle d'authentification standard Laravel. Non utilisé directement pour l'authentification Battle.net (qui passe par la session), mais présent pour les guards Laravel.

**Attributs** : `name`, `email`, `password`  
**Cachés** : `password`, `remember_token`

---

## `CharacterVisit`

Enregistre les personnages consultés (pour le sitemap et les statistiques).

| Colonne | Type | Description |
|---|---|---|
| `realm_slug` | `string` | Slug du royaume (ex. : `hyjal`) |
| `character_name` | `string` | Nom en minuscules |
| `display_name` | `string` | Nom avec casse d'origine |
| `display_realm` | `string` | Nom du royaume affiché |
| `class_name` | `string` | Nom de la classe |
| `level` | `int` | Niveau au moment de la visite |
| `last_visited_at` | `datetime` | Horodatage de la dernière consultation |

---

## `CharacterTask`

Tâche récurrente créée par un utilisateur pour suivre une activité sur un personnage donné.

| Colonne | Type | Description |
|---|---|---|
| `bnet_user_id` | `string` | Identifiant Battle.net de l'utilisateur |
| `realm_slug` | `string` | Royaume du personnage |
| `character_name` | `string` | Nom du personnage |
| `name` | `string` | Libellé de la tâche |
| `reset_type` | `string` | `daily`, `weekly` ou `monthly` |
| `is_completed` | `bool` | État courant |
| `completed_at` | `datetime?` | Date de complétion |
| `sort_order` | `int` | Ordre d'affichage |

---

## `CrossCharacterData`

Stocke le résultat du calcul cross-personnage pour un compte Battle.net. Pas de timestamps.

| Colonne | Type | Description |
|---|---|---|
| `bnet_user_id` | `string` (PK) | Identifiant Battle.net (clé primaire non auto-incrémentée) |
| `data` | `array` (JSON) | Résultat complet du calcul (quêtes, hauts-faits, réputations, professions) |
| `character_count` | `int` | Nombre de personnages inclus dans le calcul |
| `fetched_at` | `datetime` | Date du dernier calcul |

---

## `WowAchievement`

Haut-fait WoW. ID Blizzard comme clé primaire.

| Colonne | Type | Description |
|---|---|---|
| `id` | `int` (PK) | ID Blizzard |
| `name_fr` | `string` | Nom en français |
| `expansion_id` | `int` | Extension (0–11) |
| `category_name` | `string` | Catégorie (ex. : `Quêtes`, `Donjons & raids`) |
| `icon_url` | `string?` | URL de l'icône Wowhead |
| `points` | `int` | Points de haut-fait |
| `faction` | `string?` | `Alliance`, `Horde`, ou `null` (neutre) |
| `is_active` | `bool` | `false` si le haut-fait a été supprimé du jeu |

---

## `WowMount`

Monture WoW. ID Blizzard comme clé primaire.

| Colonne | Type | Description |
|---|---|---|
| `id` | `int` (PK) | ID Blizzard |
| `name_fr` | `string` | Nom en français |
| `source` | `string?` | Source d'obtention (ex. : `PvP`, `Raid`) |
| `category` | `string?` | Catégorie (ex. : `Aérien`, `Terrestre`) |
| `source_spell_id` | `int?` | ID du sort d'invocation |
| `icon_url` | `string?` | URL de l'icône |
| `is_active` | `bool` | Toujours disponible dans le jeu |

---

## `WowPet`

Mascotte de combat WoW. ID Blizzard comme clé primaire.

| Colonne | Type | Description |
|---|---|---|
| `id` | `int` (PK) | ID Blizzard |
| `name_fr` | `string` | Nom en français |
| `category` | `string?` | Catégorie (ex. : `Magique`, `Mécanique`) |
| `source` | `string?` | Source d'obtention |
| `creature_id` | `int?` | ID de créature associée |
| `icon_url` | `string?` | URL de l'icône |
| `is_active` | `bool` | Toujours disponible |

---

## `WowQuest`

Quête WoW notable (hauts-faits ou longue chaîne). ID Blizzard comme clé primaire.

| Colonne | Type | Description |
|---|---|---|
| `id` | `int` (PK) | ID Blizzard |
| `name_fr` | `string` | Nom en français |
| `expansion_id` | `int` | Extension (0–11) |
| `zone_name` | `string` | Zone où se trouve la quête |
| `faction` | `string?` | `Alliance`, `Horde`, ou `null` |
| `is_active` | `bool` | Toujours disponible |

---

## `WowDecor`

Décoration de logement WoW. ID Blizzard comme clé primaire.

| Colonne | Type | Description |
|---|---|---|
| `id` | `int` (PK) | ID Blizzard |
| `name_fr` | `string` | Nom en français |
| `category` | `string?` | Catégorie de décoration |
| `source` | `string?` | Source d'obtention |
| `item_id` | `int?` | ID de l'objet associé |
| `icon_url` | `string?` | URL de l'icône |
| `is_active` | `bool` | Toujours disponible |

---

## `WowProfession`

Métier WoW. ID Blizzard comme clé primaire.

| Colonne | Type | Description |
|---|---|---|
| `id` | `int` (PK) | ID Blizzard |
| `name_fr` | `string` | Nom en français |
| `type` | `string` | `primary` ou `secondary` |
| `max_skill_levels` | `array?` | Niveaux max par extension `{expansion_id: max}` |
| `is_active` | `bool` | Toujours disponible |

**Relation** : `recipes()` → `HasMany<WowRecipe>` (via `profession_id`)

---

## `WowRecipe`

Recette de métier. ID Blizzard comme clé primaire.

| Colonne | Type | Description |
|---|---|---|
| `id` | `int` (PK) | ID Blizzard |
| `name_fr` | `string` | Nom en français |
| `profession_id` | `int` | FK vers `WowProfession` |
| `expansion_id` | `int` | Extension (0–11) |
| `category_name` | `string?` | Sous-catégorie (ex. : `Armure`, `Arme`) |
| `faction` | `string?` | `Alliance`, `Horde`, ou `null` |
| `wowhead_spell_id` | `int?` | ID du sort pour les liens Wowhead |
| `is_active` | `bool` | Toujours disponible |

**Relation** : `profession()` → `BelongsTo<WowProfession>`
