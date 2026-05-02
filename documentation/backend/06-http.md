# Couche Http — Controllers et Middleware

Les controllers reçoivent la requête, délèguent aux services et retournent une `JsonResponse`. Ils ne contiennent pas de logique métier.

---

## Controllers (`app/Http/Controllers/`)

### `AdminController`

Gère les opérations d'administration. Toutes les routes sont protégées par le middleware `admin`.

**Dépendance** : `AdminService`

| Méthode | Route | Description |
|---|---|---|
| `status()` | `GET /api/admin/status` | Retourne si le mode maintenance est actif. |
| `import(Request)` | `POST /api/admin/import` | Valide et dispatche une commande d'import (retourne un `jobId`). Commandes autorisées : `app:download-db2`, `app:wow-data-import`, `app:wow-data-refresh`, `app:wow-quest-faction-tag`. |
| `importStatus(string $jobId)` | `GET /api/admin/import/{jobId}` | Lit l'état d'un job d'import depuis le cache. |
| `clearCaches()` | `POST /api/admin/clear-caches` | Vide tous les caches. |
| `toggleMaintenance(Request)` | `POST /api/admin/maintenance` | Active/désactive le mode maintenance. |
| `sendDiscord(Request)` | `POST /api/admin/discord` | Envoie un embed Discord. |

---

### `AuthController`

Gère le flux OAuth2 Battle.net (redirection, callback, statut, déconnexion).

| Méthode | Route | Description |
|---|---|---|
| `status()` | `GET /api/auth/status` | Retourne `{authenticated: bool, isAdmin: bool}`. |
| `logout()` | `POST /api/auth/logout` | Déconnecte l'utilisateur (vide la session). |
| `redirect()` | `GET /auth/bnet/redirect` | Redirige vers la page d'autorisation Battle.net. |
| `callback()` | `GET /auth/bnet/callback` | Reçoit le code OAuth2, crée la session, redirige vers `/my-characters`. |

---

### `CharacterController`

Retourne les données d'un personnage depuis l'API Blizzard.

**Dépendance** : `CharacterProfileService`

| Méthode | Route | Description |
|---|---|---|
| `show(string $realm, string $name)` | `GET /api/character/{realm}/{name}` | Profil complet du personnage (JSON sérialisé du `CharacterProfileDTO`). |
| `talents(string $realm, string $name)` | `GET /api/character/{realm}/{name}/talents` | Données de talents et spécialisations uniquement. |

---

### `UserController`

Données liées à l'utilisateur connecté.

**Dépendance** : `UserCharacterService`

| Méthode | Route | Description |
|---|---|---|
| `characters()` | `GET /api/user/characters` | Liste des personnages du compte Battle.net connecté avec avatars. |
| `classIcons()` | `GET /api/class-icons` | Tableau `class_id → icon_url` pour les 13 classes. |

---

### `CharacterTaskController`

CRUD des tâches personnalisées (quotidiennes, hebdomadaires, mensuelles) associées aux personnages.

**Dépendance** : `CharacterTaskService`

| Méthode | Route | Description |
|---|---|---|
| `index()` | `GET /api/character-tasks` | Liste toutes les tâches de l'utilisateur connecté. |
| `store(Request)` | `POST /api/character-tasks` | Crée une tâche. Champs : `realm_slug`, `character_name`, `name`, `reset_type`. |
| `update(int $id)` | `PUT /api/character-tasks/{id}` | Bascule l'état complété/non complété. |
| `destroy(int $id)` | `DELETE /api/character-tasks/{id}` | Supprime une tâche. |

---

### `AccountScoreController`

Gère le calcul du score de compte et la progression cross-personnage.

**Dépendances** : `AccountScoreService`, `CrossCharacterService`

| Méthode | Route | Description |
|---|---|---|
| `index()` | `GET /api/account/score` | Score de compte (depuis cache ou recalcul). |
| `refresh()` | `POST /api/account/score/refresh` | Invalide le cache et force le recalcul. |
| `crossCharacter()` | `GET /api/account/cross-character` | Lance ou retourne le calcul cross-personnage. |
| `crossCharacterJob(string $jobId)` | `GET /api/account/cross-character/{jobId}` | État d'un job de calcul cross-personnage. |
| `crossCharacterData()` | `GET /api/account/cross-character-data` | Données stockées du dernier calcul cross-personnage. |

---

### `DatabaseController`

Retourne les données de la base WoW pour les pages de base de données.

| Méthode | Route | Description |
|---|---|---|
| `counts()` | `GET /api/database/counts` | Nombre total par type (`mounts`, `pets`, etc.). |
| `subcategories(string $section)` | `GET /api/database/subcategories/{section}` | Liste des sous-catégories d'une section. |
| `mounts(Request)` | `GET /api/database/mounts` | Liste paginée des montures avec filtres. |
| `achievements(Request)` | `GET /api/database/achievements` | Liste paginée des hauts-faits avec filtres. |
| `quests(Request)` | `GET /api/database/quests` | Liste paginée des quêtes avec filtres. |
| `pets(Request)` | `GET /api/database/pets` | Liste paginée des mascottes avec filtres. |
| `decors(Request)` | `GET /api/database/decors` | Liste paginée des décorations avec filtres. |
| `professions(Request)` | `GET /api/database/professions` | Liste des professions et recettes avec filtres. |

---

### `SeoController`

Génère les métadonnées SEO et sitemaps pour le rendu côté serveur.

**Dépendances** : `CharacterSeoService`, `DatabaseSeoService`

| Méthode | Route | Description |
|---|---|---|
| `meta(Request)` | `GET /api/seo/meta` | Métadonnées (title, description, og:image, JSON-LD) pour n'importe quelle URL. |
| `sitemapIndex()` | `GET /sitemap.xml` | Sitemap index XML. |
| `pagesSitemap()` | `GET /sitemap-pages.xml` | Sitemap des pages statiques. |
| `charactersSitemap()` | `GET /sitemap-characters.xml` | Sitemap des personnages visités. |
| `databaseSitemap()` | `GET /sitemap-database.xml` | Sitemap des pages de base de données. |

---

## Middleware (`app/Http/Middleware/`)

### `EnsureIsAdmin`

Vérifie que l'utilisateur connecté a le rôle admin. Retourne une erreur 403 sinon. Utilisé sur toutes les routes `/api/admin/*`.

### `SecurityHeaders`

Ajoute les en-têtes de sécurité HTTP à chaque réponse :

| En-tête | Valeur |
|---|---|
| `X-Frame-Options` | `SAMEORIGIN` |
| `X-Content-Type-Options` | `nosniff` |
| `Referrer-Policy` | `strict-origin-when-cross-origin` |
| `Permissions-Policy` | Restreint les fonctionnalités du navigateur |
| `Strict-Transport-Security` | Uniquement en production (HSTS) |
| `Content-Security-Policy` | Uniquement en production |
