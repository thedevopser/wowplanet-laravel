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

---

## `WowImportState`

Build WoW du dernier import réussi, une ligne par entité importée. Table `wow_import_states`, clé primaire `entity`, sans séquence ni horodatage Eloquent.

| Colonne | Rôle |
|---|---|
| `entity` | Entité importée : `quests`, `mounts`, `appearances`… |
| `build` | Build WoW servi par l'API au moment de l'import. |
| `last_modified` | En-tête `Last-Modified` du dernier index revalidé, renvoyé tel quel en `If-Modified-Since`. |
| `imported_at` | Date de l'import. |

C'est la seule table applicative sans `bnet_user_id` : elle ne porte pas de donnée utilisateur mais un état de pipeline. Lue et écrite par `ImportBuildGate`.

---

## `WowReferenceDownload`

Inventaire du magasin de fichiers de référence : un fichier DB2 téléchargé, une ligne. Table `wow_reference_downloads`, clé primaire `filename`, sans séquence ni horodatage Eloquent.

| Colonne | Rôle |
|---|---|
| `filename` | Nom du fichier dans le magasin, slug de la table et build. |
| `source_table` | Nom de la table DB2 chez wago (`Faction`, `AreaTable`…). |
| `build` | Build WoW LIVE au moment du téléchargement. |
| `bytes` | Taille du fichier téléchargé. |
| `row_count` | Nombre de lignes effectivement chargées en base. |
| `downloaded_at` | Date du téléchargement. |

Les lignes s'accumulent d'un build à l'autre : c'est cet inventaire que la purge du magasin consommera. `row_count` sert de garde-fou à la synchronisation suivante, qui refuse une source dont la volumétrie s'effondre sous la moitié du dernier chargement plutôt que d'écraser le socle.

Le nom échappe volontairement au préfixe `wow_ref_`, pour qu'aucun traitement balayant la famille des tables de référence ne vide l'inventaire avec elles.

---

## `WowCollectionTaxonomy`

Table `wow_collection_taxonomy`. Rangement curé d'une entrée de collection : sa catégorie de niveau 1, sa source de niveau 2, et le marqueur `obtainable` qui dit si un joueur peut encore l'obtenir.

| Colonne | Rôle |
|---|---|
| `entity` | Collection concernée : `mount`, `pet` ou `decor`. |
| `entry_id` | Identifiant Blizzard — id de monture, species id de mascotte, id de décoration. |
| `category` | Catégorie de niveau 1, libellé anglais brut, nullable. |
| `source` | Source de niveau 2, libellé anglais brut, nullable. |

Clé primaire composite `(entity, entry_id)` : deux collections peuvent curer le même identifiant sans se gêner, et la table n'a pas de séquence — les identifiants viennent de Blizzard. **Toutes les écritures passent par `insertOrIgnore` au niveau du constructeur de requête** ; un `save()` sur une instance chargée ne saurait pas la retrouver.

C'est notre donnée, pas celle de l'API, qui n'expose qu'un vocabulaire de onze valeurs là où la curation en compte 170 pour les seules montures. Amorcée une fois depuis SimpleArmory, elle n'est ensuite qu'enrichie : un rafraîchissement ajoute les entrées inconnues et ne touche jamais à une ligne existante, pour qu'un ajustement manuel y survive.

Une ligne dont la catégorie est nulle est une entrée rangée nulle part **en connaissance de cause** ; l'absence de ligne est une entrée à arbitrer, que `app:collection-taxonomy-report` liste. Ne pas confondre les deux.

Le nom échappe au préfixe `wow_ref_` pour la même raison que `wow_reference_downloads` : aucun balayage des tables de référence DB2 ne doit pouvoir vider la curation.
