# Data Transfer Objects (DTOs)

Les DTOs sont des conteneurs immuables qui transportent les données entre les couches. Ils ne contiennent pas de logique métier.

---

## `CharacterProfileDTO`

DTO principal représentant le profil complet d'un personnage après traitement. Créé par `CharacterProfileService::getProfile()` et consommé par les controllers et les agrégateurs de compte.

**Propriétés `readonly`**

| Propriété | Type | Description |
|---|---|---|
| `$name` | `string` | Nom du personnage |
| `$realm` | `string` | Nom du royaume |
| `$race` | `string` | Race |
| `$class` | `string` | Classe |
| `$classId` | `int` | ID de classe Blizzard (1–13) |
| `$level` | `int` | Niveau |
| `$ilvl` | `int` | Niveau d'objet moyen |
| `$faction` | `string` | `Alliance` ou `Horde` |
| `$avatarUrl` | `string` | URL de l'avatar |
| `$classIconUrl` | `string` | URL de l'icône de classe |
| `$collections` | `array<int, array<string, mixed>>` | Progression par extension (`expId → {quests, achievements, reputations}`) |
| `$mountsCount` | `int` | Nombre de montures possédées |
| `$petsCount` | `int` | Nombre de mascottes possédées |
| `$achievementPoints` | `int` | Points de haut-fait totaux |
| `$guild` | `string` | Nom de la guilde |
| `$mounts` | `array` | Liste complète des montures avec `is_completed` |
| `$pets` | `array` | Liste complète des mascottes avec `is_completed` |
| `$professions` | `list<array<string, mixed>>` | Métiers avec progression par extension |
| `$decorCount` | `int` | Nombre de décorations possédées |
| `$decor` | `array` | Liste complète des décorations avec `is_completed` |
| `$exaltedCount` | `int` | Nombre de réputations exaltées |
| `$mythicKeystone` | `?array` | Données de progression Clé mythique+ (saison courante) |
| `$completedQuestIds` | `list<int>` | IDs des quêtes complétées |
| `$completedAchievementIds` | `list<int>` | IDs des hauts-faits complétés |
| `$equipment` | `list<array>` | Équipement par emplacement avec niveau d'objet et icône |

---

## `AccountScoreProgress`

Agrège la progression de plusieurs personnages pour calculer le score de compte. Muable durant le calcul, puis figé via `buildResult()`.

**Propriétés**

| Propriété | Type | Description |
|---|---|---|
| `$processed` | `int` | Nombre de personnages traités |
| `$errors` | `list<string>` | Erreurs rencontrées |
| `$completedQuestIds` | `array<int, true>` | Union des IDs de quêtes complétées |
| `$completedAchievementIds` | `array<int, true>` | Union des IDs de hauts-faits complétés |
| `$bestReputations` | `array<int, array{completed, total}>` | Meilleure progression de réputation par extension |
| `$accountMounts` | `?array` | Montures du compte (depuis le 1er personnage) |
| `$accountPets` | `?array` | Mascottes du compte (depuis le 1er personnage) |
| `$accountDecor` | `?array` | Décorations du compte (depuis le 1er personnage) |
| `$bestProfessionStats` | `?array{completed, total}` | Meilleur ratio de recettes parmi tous les personnages |
| `$characters` | `array<array{realmSlug, name}>` | Liste des personnages du compte |

**Méthodes**

| Méthode | Paramètres | Description |
|---|---|---|
| `mergeProfile` | `CharacterProfileDTO` | Fusionne les données d'un personnage dans la progression agrégée |
| `buildResult` | — | Construit et retourne le tableau final de résultats |

---

## `CrossCharacterProgress`

Agrège les progressions cross-personnages dans le contexte d'un job asynchrone. Suit quel personnage a complété quoi (pour les infobulles "complété sur X").

**Propriétés**

| Propriété | Type | Description |
|---|---|---|
| `$completedQuestIds` | `array<int, string>` | `quest_id → character_name` |
| `$completedAchievementIds` | `array<int, string>` | `achievement_id → character_name` |
| `$completedRecipeIds` | `array<int, true>` | IDs des recettes connues sur au moins un personnage |
| `$bestFactionStandings` | `array<int, array{...}>` | Meilleur standing de réputation par faction |
| `$recipeOwners` | `array<int, string>` | `recipe_id → character_name` |
| `$skillPointOwners` | `array<int, array<int, array{...}>>` | `[profId][expId] → {character_name, skill_points, max_skill_points}` |

**Méthodes**

| Méthode | Paramètres | Description |
|---|---|---|
| `mergeCharacter` | `string $name, array $rawData` | Fusionne les données brutes d'un personnage |
| `mergeFromProfile` | `string $name, CharacterProfileDTO` | Fusionne depuis un DTO complet |
| `buildResult` | — | Construit le tableau résultat stocké en base |
