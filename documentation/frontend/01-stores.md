# Stores Pinia

Les stores centralisent l'état global de l'application Vue. Ils sont consommés depuis les pages et composants via `useXxxStore()`.

---

## `useCharacterStore` (`stores/character.js`)

Store principal de l'application. Gère l'authentification, les données de personnage, la progression cross-compte et le thème.

### État

| Propriété | Type | Description |
|---|---|---|
| `character` | `object\|null` | Données du personnage actuellement affiché |
| `loading` | `bool` | Chargement d'un personnage en cours |
| `error` | `string\|null` | Dernière erreur de chargement |
| `isAuthenticated` | `bool` | Utilisateur Battle.net connecté |
| `isAdmin` | `bool` | Utilisateur admin |
| `userCharacters` | `array` | Personnages du compte connecté |
| `classIcons` | `object` | `class_id → icon_url` |
| `loadingCharacters` | `bool` | Chargement de la liste des personnages |
| `theme` | `string` | `dark` ou `light` (persisté en `localStorage`) |
| `crossCharacter` | `object\|null` | Données cross-personnage du compte |
| `crossCharacterStatus` | `string` | `idle`, `loading`, `ready`, `error`, `not_available` |
| `expansions` | `array` | Liste des 12 extensions WoW (id + nom) |

### Getters

| Getter | Description |
|---|---|
| `latestExpansionId` | ID de la dernière extension (actuellement `11`) |
| `expansionNamesDesc` | Noms des extensions en ordre décroissant |
| `crossCharQuestIds` | `Set<int>` des IDs de quêtes complétées sur n'importe quel personnage |
| `crossCharAchievementIds` | `Set<int>` des IDs de hauts-faits complétés ailleurs |
| `crossCharRecipeIds` | `Set<int>` des IDs de recettes connues ailleurs |

> Les Sets sont construits une seule fois (lazy) et invalidés lors d'une mise à jour des données cross-personnage.

### Actions

| Action | Description |
|---|---|
| `toggleTheme()` | Bascule entre dark/light, persiste en localStorage. |
| `checkAuth()` | Appelle `GET /api/auth/status` pour vérifier la session. |
| `fetchCharacter(realm, name)` | Charge un personnage depuis l'API. |
| `fetchUserCharacters()` | Charge la liste des personnages du compte. |
| `fetchClassIcons()` | Charge les icônes de classes (ne re-télécharge pas si déjà chargées). |
| `logout()` | Déconnecte et remet l'état à zéro. |
| `computeCrossCharacter()` | Démarre ou lit le calcul cross-personnage. Poll automatiquement si un job est en cours. |
| `loadCrossCharacterData()` | Charge les données cross-personnage déjà calculées. |
| `isQuestCompletedElsewhere(questId)` | `boolean` — la quête est complétée sur un autre personnage. |
| `isAchievementCompletedElsewhere(achievementId)` | Idem pour les hauts-faits. |
| `isRecipeKnownElsewhere(recipeId)` | Idem pour les recettes. |
| `getQuestOwner(questId)` | Nom du personnage qui a complété la quête. |
| `getAchievementOwner(achievementId)` | Nom du personnage qui a le haut-fait. |
| `getRecipeOwner(recipeId)` | Nom du personnage qui connaît la recette. |
| `getBestFactionStanding(factionId)` | Meilleur standing de réputation sur le compte. |
| `getBestSkillPoints(profId, expId)` | Meilleurs points de compétence pour un métier/extension. |

---

## `useDatabaseSidebarStore` (`stores/databaseSidebar.js`)

Gère l'état de la sidebar de navigation de la base de données (sections ouvertes/fermées, sous-catégories chargées).

### État

| Propriété | Type | Description |
|---|---|---|
| `counts` | `object` | Nombre total par section (`mounts`, `pets`, etc.) |
| `expanded` | `object` | `{sectionKey: bool}` — sections dépliées |
| `subCategories` | `object` | `{sectionKey: array}` — sous-catégories chargées |
| `loading` | `object` | `{sectionKey: bool}` — chargement en cours |

### Actions

| Action | Description |
|---|---|
| `fetchCounts()` | Charge les compteurs depuis `GET /api/database/counts` (une seule fois). |
| `fetchSubCategories(sectionKey)` | Charge les sous-catégories d'une section si non chargées. |
| `toggleSection(sectionKey)` | Ouvre/ferme une section. Déclenche `fetchSubCategories` si ouverture. |
| `expandActiveSection(routePath)` | Replie tout et déplie la section correspondant au chemin de route courant. |

---

## `useTaskStore` (`stores/tasks.js`)

Gère les tâches récurrentes associées aux personnages (suivi des resets quotidien/hebdo/mensuel).

### État

| Propriété | Type | Description |
|---|---|---|
| `tasks` | `array` | Toutes les tâches de l'utilisateur |
| `loading` | `bool` | Chargement en cours |
| `sidebarOpen` | `bool` | Sidebar des tâches visible (persisté en localStorage) |

### Getters

| Getter | Description |
|---|---|
| `charactersWithTasks` | Liste dédupliquée des personnages ayant au moins une tâche. |
| `totalPendingCount` | Nombre total de tâches non complétées sur tous les personnages. |
| `characterTasks(realmSlug, characterName)` | Tâches d'un personnage triées par type de reset (daily → weekly → monthly). |
| `pendingCount(realmSlug, characterName)` | Tâches en attente pour un personnage. |

### Actions

| Action | Description |
|---|---|
| `fetchTasks()` | Charge les tâches puis applique les resets. |
| `createTask(realmSlug, characterName, taskName, resetType)` | Crée une tâche via l'API. |
| `toggleTask(taskId)` | Bascule l'état complété/non complété. |
| `deleteTask(taskId)` | Supprime une tâche. |
| `applyResets()` | Vérifie localement si des tâches doivent être réinitialisées selon leur type de reset. |
| `toggleSidebar()` | Ouvre/ferme la sidebar des tâches. |

**Logique de reset**

| Type | Réinitialisation |
|---|---|
| `daily` | Chaque jour à 5h00 (heure locale) |
| `weekly` | Chaque mercredi à 5h00 |
| `monthly` | Le 1er de chaque mois à 5h00 |
