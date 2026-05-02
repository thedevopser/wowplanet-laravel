# Services de la couche Application

Les services orchestrent la logique métier : ils appellent l'API Blizzard, agrègent les données et renvoient des résultats structurés. Ils n'ont pas accès direct à `Request` ni à la couche Http.

---

## `AccountScoreService`

Calcule et met en cache le score de compte multi-personnages d'un utilisateur connecté.

**Constantes**

| Constante | Valeur | Description |
|---|---|---|
| `BATCH_SIZE` | `1` | Nombre de personnages traités par batch |
| `CACHE_TTL` | `86400` | Durée de cache du résultat (24 h) |
| `PROGRESS_TTL` | `3600` | Durée de cache de la progression en cours (1 h) |

**Dépendances injectées** : `CharacterProfileService`, `UserCharacterService`

**Méthodes**

| Méthode | Retour | Description |
|---|---|---|
| `getOrCompute()` | `array{status, data?, progress?}` | Retourne le score depuis le cache ou le recalcule. `status` peut être `ready`, `computing` ou `no_characters`. |
| `invalidate()` | `void` | Supprime le cache du score pour l'utilisateur courant. |

---

## `AdminService`

Pilote les opérations d'administration : import de données, gestion du cache, maintenance, Discord.

**Commandes autorisées** (`ALLOWED_COMMANDS`) : `app:download-db2`, `app:wow-data-import`, `app:wow-data-refresh`, `app:wow-quest-faction-tag`

**Méthodes**

| Méthode | Paramètres | Retour | Description |
|---|---|---|---|
| `runImportCommand` | `string $command, array $parameters` | `string` | Dispatche un `RunImportJob` et retourne le `jobId`. |
| `clearCaches` | — | `string` | Vide tous les caches Laravel. |
| `toggleMaintenance` | `bool $enable, ?string $secret` | `string` | Active ou désactive le mode maintenance. |
| `isInMaintenance` | — | `bool` | Vérifie si le mode maintenance est actif. |
| `sendDiscordEmbed` | `string $channel, array $embed` | `bool` | Envoie un embed Discord sur le webhook configuré. |
| `getImportJobStatus` | `string $jobId` | `array{status, output}` | Lit l'état d'un job d'import depuis le cache. |

---

## `CharacterProfileService`

Récupère et assemble le profil complet d'un personnage depuis l'API Blizzard.

**Constantes**

| Constante | Valeur |
|---|---|
| `MAX_ASYNC_RETRIES` | `2` |
| `RETRY_DELAY_S` | `3` |

**Méthodes publiques**

| Méthode | Paramètres | Retour | Description |
|---|---|---|---|
| `getProfile` | `string $realm, string $name` | `CharacterProfileDTO` | Point d'entrée principal. Orchestre tous les appels API et retourne un DTO complet. |
| `fetchCrossCharacterRawData` | `string $realm, string $name` | `array{questIds, achievementIds, reputations, professions}` | Récupère uniquement les données nécessaires au calcul cross-personnage. |

---

## `CharacterSeoService`

Génère les métadonnées SEO et les sitemaps pour les pages personnage et les pages statiques.

**Méthodes**

| Méthode | Paramètres | Retour | Description |
|---|---|---|---|
| `getHomeMeta` | — | `array<string, string\|null>` | Méta pour la page d'accueil. |
| `getStaticPageMeta` | `string $page` | `array<string, string\|null>` | Méta pour une page statique (`faq`, `privacy`, `cgu`). |
| `getCharacterMeta` | `string $realm, string $name` | `array<string, string\|null>` | Méta OG + Twitter pour un personnage. Appelle l'API si nécessaire, cache le résultat. |
| `generateSitemapIndex` | — | `string` | Génère le sitemap index XML. |
| `generatePagesSitemap` | — | `string` | Génère le sitemap des pages statiques. |
| `generateCharactersSitemap` | — | `string` | Génère le sitemap des personnages visités. |

---

## `CharacterTaskService`

Gère les tâches personnalisées associées aux personnages d'un utilisateur (tâches quotidiennes, hebdomadaires, mensuelles).

**Méthodes**

| Méthode | Paramètres | Retour | Description |
|---|---|---|---|
| `getTasksForUser` | `string $bnetUserId` | `Collection<int, CharacterTask>` | Liste toutes les tâches de l'utilisateur. |
| `createTask` | `string $bnetUserId, array $data` | `CharacterTask` | Crée une nouvelle tâche. |
| `toggleTask` | `int $taskId, string $bnetUserId` | `CharacterTask` | Bascule l'état complété/non complété. |
| `deleteTask` | `int $taskId, string $bnetUserId` | `void` | Supprime une tâche (vérifie la propriété). |
| `resetTask` | `int $taskId, string $bnetUserId` | `CharacterTask` | Remet la tâche à l'état non complété. |

---

## `CrossCharacterService`

Orchestre le calcul de progression agrégée sur tous les personnages d'un compte Battle.net.

**Constantes**

| Constante | Valeur |
|---|---|
| `CACHE_TTL_HOURS` | `24` |
| `MAX_RETRIES` | `3` |
| `RETRY_BASE_DELAY_S` | `5` |

**Méthodes publiques**

| Méthode | Retour | Description |
|---|---|---|
| `compute()` | `array{status, data?, characterCount?, jobId?}` | Déclenche ou retourne le calcul cross-personnage. Si déjà calculé, retourne `ready`. Sinon, dispatche un `ComputeCrossCharacterJob` et retourne `computing` + `jobId`. |
| `getJobStatus` | `array{status}` | Lit l'état d'un job cross-personnage depuis le cache. |
| `getStoredData` | `?array{data, character_count}` | Retourne les données déjà calculées et stockées en base. |
| `mergeCurrentCharacter` | `CharacterProfileDTO` | Fusionne les données du personnage courant dans une progression cross-personnage. |
| `fetchAndMergeCharacters` | `array, CrossCharacterProgress, ?string` | Récupère et fusionne tous les personnages (utilisé depuis le job). |

---

## `DatabaseContentRenderer`

Génère le contenu HTML statique des pages de la base de données WoW pour les robots d'indexation (SEO). Le contenu est injecté dans la SPA via balise `<noscript>`.

**Méthodes**

| Méthode | Paramètres | Description |
|---|---|---|
| `renderDatabaseIndex` | `string $appUrl` | Page d'index de la base de données. |
| `renderMounts` | `string $appUrl, ?string $categorySlug` | Liste des montures filtrées par catégorie. |
| `renderAchievements` | `string $appUrl, ?string $expansionSlug` | Liste des hauts-faits filtrés par extension. |
| `renderQuests` | `string $appUrl, ?string $expansionSlug` | Liste des quêtes filtrées par extension. |
| `renderPets` | `string $appUrl, ?string $categorySlug` | Liste des mascottes filtrées par catégorie. |
| `renderDecors` | `string $appUrl, ?string $categorySlug` | Liste des décorations filtrées par catégorie. |
| `renderProfessions` | `string $appUrl, ?string $professionSlug` | Liste des professions/recettes filtrées. |

---

## `DatabaseSeoService`

Calcule les métadonnées SEO pour chaque section de la base de données.

**Méthodes**

| Méthode | Paramètres | Retour |
|---|---|---|
| `getIndexMeta` | — | `array<string, string\|null>` |
| `getMountsMeta` | `?string $categorySlug` | `?array<string, string\|null>` |
| `getAchievementsMeta` | `?string $expansionSlug` | `?array<string, string\|null>` |
| `getQuestsMeta` | `?string $expansionSlug` | `?array<string, string\|null>` |
| `getPetsMeta` | `?string $categorySlug` | `?array<string, string\|null>` |
| `getDecorsMeta` | `?string $categorySlug` | `?array<string, string\|null>` |
| `getProfessionsMeta` | `?string $professionSlug` | `?array<string, string\|null>` |
| `getSitemapUrls` | — | `array<array{url, label, lastmod}>` |
| `generateSitemap` | — | `string` (XML) |

---

## `SeoContentRenderer`

Génère le contenu HTML statique pour la page d'accueil et les pages personnage (SEO/no-JS).

**Méthodes**

| Méthode | Paramètres | Description |
|---|---|---|
| `renderHome` | `string $appUrl` | Contenu HTML de la page d'accueil. |
| `renderCharacter` | `string $appUrl, array $charData, string $realm, string $name` | Contenu HTML de la page personnage. |

---

## `UserCharacterService`

Gère l'authentification OAuth2 Battle.net et récupère la liste des personnages du compte.

**Méthodes**

| Méthode | Retour | Description |
|---|---|---|
| `isAuthenticated()` | `bool` | Vérifie si l'utilisateur a une session Battle.net active. |
| `isAdmin()` | `bool` | Vérifie si l'utilisateur est administrateur. |
| `logout()` | `void` | Déconnecte l'utilisateur. |
| `getUserCharacters()` | `array<int, array<string, mixed>>` | Retourne la liste des personnages avec avatars, classe, race, faction. |
