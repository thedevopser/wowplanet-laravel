# Couche Domain

Contient la logique métier pure, sans dépendance vers Laravel ou la base de données.

---

## Entités (`app/Domain/Entities/`)

### `Character`

Représente un personnage World of Warcraft tel que retourné par l'API Blizzard.

**Propriétés**

| Propriété | Type | Description |
|---|---|---|
| `$name` | `string` | Nom du personnage |
| `$realm` | `string` | Nom du royaume |
| `$race` | `string` | Race (ex. : Humain, Troll) |
| `$class` | `string` | Classe (ex. : Guerrier, Mage) |
| `$level` | `int` | Niveau actuel |
| `$ilvl` | `int` | Niveau d'objet moyen |
| `$faction` | `string` | `Alliance` ou `Horde` |
| `$media` | `CharacterMedia` | URLs des images du personnage |

---

### `CollectionItem` (interface)

Interface polymorphique pour tout élément de collection WoW (monture, mascotte, décoration, etc.).

**Méthodes**

| Méthode | Retour | Description |
|---|---|---|
| `getExpansionId()` | `ExpansionId` | Extension à laquelle appartient l'élément |
| `getName()` | `string` | Nom de l'élément |
| `getId()` | `int` | Identifiant Blizzard |

---

## Value Objects (`app/Domain/ValueObjects/`)

### `CharacterMedia`

Encapsule les trois URLs d'images d'un personnage. Objet immuable (`readonly`).

| Propriété | Type | Description |
|---|---|---|
| `$avatarUrl` | `string` | Avatar (portrait carré) |
| `$insetUrl` | `string` | Inset (buste) |
| `$mainUrl` | `string` | Image complète |

---

### `ExpansionId`

Value Object immuable représentant une extension WoW. Lance `InvalidArgumentException` si la valeur est hors plage.

**Constantes**

| Constante | Valeur | Extension |
|---|---|---|
| `CLASSIC` | `0` | World of Warcraft Classic |
| `BURNING_CRUSADE` | `1` | The Burning Crusade |
| `WRATH_OF_THE_LICH_KING` | `2` | Wrath of the Lich King |
| `CATACLYSM` | `3` | Cataclysm |
| `MISTS_OF_PANDARIA` | `4` | Mists of Pandaria |
| `WARLORDS_OF_DRAENOR` | `5` | Warlords of Draenor |
| `LEGION` | `6` | Legion |
| `BATTLE_FOR_AZEROTH` | `7` | Battle for Azeroth |
| `SHADOWLANDS` | `8` | Shadowlands |
| `DRAGONFLIGHT` | `9` | Dragonflight |
| `THE_WAR_WITHIN` | `10` | The War Within |
| `MIDNIGHT` | `11` | Midnight |

**Méthodes**

| Méthode | Retour | Description |
|---|---|---|
| `toString()` | `string` | Nom complet de l'extension (ex. : `"The Burning Crusade"`) |
| `toOrdinal()` | `string` | Rang en français (ex. : `"la 1re extension"`) |
| `toSlug()` | `string` | Slug URL (ex. : `"burning-crusade"`) |
| `fromSlug(string $slug)` | `?self` | Construit depuis un slug, `null` si inconnu (statique) |
| `allSlugs()` | `array<int, string>` | Tableau complet `id → slug` (statique) |

---

## Services (`app/Domain/Services/`)

### `ExpansionClassifier`

Détermine à quelle extension appartient une zone, une quête ou un haut-fait en consultant les mappings statiques.

**Dépendance injectée** : `ExpansionMapping`

**Méthodes**

| Méthode | Paramètres | Retour | Description |
|---|---|---|---|
| `classifyZone` | `int $zoneId` | `ExpansionId` | Extension de la zone. Retourne Classic si inconnue. |
| `classifyQuest` | `int $questId, ?int $zoneId` | `ExpansionId` | Extension de la quête. Fallback sur la zone si pas de mapping direct. |
| `classifyAchievement` | `int $achievementId` | `ExpansionId` | Extension du haut-fait. Retourne Classic si inconnu. |
| `classifyAchievementCategory` | `int $categoryId` | `ExpansionId` | Extension d'une catégorie de haut-fait. |
