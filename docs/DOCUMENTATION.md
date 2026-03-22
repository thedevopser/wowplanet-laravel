# Documentation technique - WowPlanet

Application de suivi de progression World of Warcraft (Laravel 11 + Vue 3 + Pinia + Tailwind CSS v4 + SQLite).

---

## Table des matières

1. [Vue d'ensemble](#1-vue-densemble)
   - [Stack technique](#stack-technique)
   - [Architecture Clean](#architecture-clean)
   - [Arborescence](#arborescence)
   - [Commandes de développement](#commandes-de-développement)
2. [Tables de référence des routes](#2-tables-de-référence-des-routes)
   - [Routes Web](#21-routes-web-routeswebphp)
   - [Routes API publiques](#22-routes-api-publiques-throttleapi)
   - [Routes API authentifiées](#23-routes-api-authentifiées-throttleauthenticated)
   - [Routes API admin](#24-routes-api-admin-throttleauthenticated--admin)
   - [Routes Vue Router (frontend)](#25-routes-vue-router-frontend)
3. [Middleware](#3-middleware)
   - [SecurityHeaders](#31-securityheaders)
   - [EnsureIsAdmin](#32-ensureisadmin)
   - [Rate Limiters](#33-rate-limiters-appserviceprovider)
4. [Contrôleurs](#4-contrôleurs)
   - [AuthController](#41-authcontroller)
   - [SeoController](#42-seocontroller)
   - [DatabaseController](#43-databasecontroller)
   - [CharacterController](#44-charactercontroller)
   - [DatabaseApiController](#45-databaseapicontroller)
   - [UserCharacterController](#46-usercharactercontroller)
   - [CharacterTaskController](#47-charactertaskcontroller)
   - [AdminController](#48-admincontroller)
5. [Services (couche Application)](#5-services-couche-application)
   - [CharacterProfileService](#51-characterprofileservice)
   - [UserCharacterService](#52-usercharacterservice)
   - [AccountScoreService](#53-accountscoreservice)
   - [CrossCharacterService](#54-crosscharacterservice)
   - [CharacterTaskService](#55-charactertaskservice)
   - [CharacterSeoService](#56-characterseoservice)
   - [DatabaseSeoService](#57-databaseseoservice)
   - [SeoContentRenderer](#58-seocontentrenderer)
   - [AdminService](#59-adminservice)
6. [Couche Domain](#6-couche-domain)
   - [ExpansionClassifier](#61-expansionclassifier)
   - [Character (entité)](#62-character-entité)
   - [CollectionItem (interface)](#63-collectionitem-interface)
7. [DTOs et Value Objects](#7-dtos-et-value-objects)
   - [CharacterProfileDTO](#71-characterprofiledto)
   - [CrossCharacterProgress](#72-crosscharacterprogress)
   - [AccountScoreProgress](#73-accountscoreprogress)
   - [ExpansionId](#74-expansionid-value-object)
   - [CharacterMedia](#75-charactermedia-value-object)
8. [Agrégateurs de progression](#8-agrégateurs-de-progression)
   - [QuestProgressAggregator](#81-questprogressaggregator)
   - [AchievementProgressAggregator](#82-achievementprogressaggregator)
   - [CollectionProgressAggregator](#83-collectionprogressaggregator)
   - [ProfessionProgressAggregator](#84-professionprogressaggregator)
   - [ReputationProgressAggregator](#85-reputationprogressaggregator)
9. [Modèles Eloquent](#9-modèles-eloquent)
10. [Infrastructure](#10-infrastructure)
    - [BlizzardApiClient](#101-blizzardapiclient)
    - [BlizzardBatchImporter](#102-blizzardbatchimporter)
    - [Importateurs individuels](#103-importateurs-individuels)
    - [Parseurs](#104-parseurs)
11. [Commandes Artisan](#11-commandes-artisan)
12. [Jobs (file d'attente)](#12-jobs-file-dattente)
13. [Frontend Vue](#13-frontend-vue)
    - [Structure principale](#131-structure-principale)
    - [Pages](#132-pages)
    - [Composants onglets](#133-composants-onglets-de-progression)
    - [Composants utilitaires](#134-composants-utilitaires)
    - [Stores Pinia](#135-stores-pinia)
    - [Utilitaires JavaScript](#136-utilitaires-javascript)
14. [Flux d'authentification](#14-flux-dauthentification-blizzard-oauth-20)
15. [Pipeline d'import de données](#15-pipeline-dimport-de-données)
16. [Stratégie de cache](#16-stratégie-de-cache)

---

## 1. Vue d'ensemble

### Stack technique

- **Backend** : Laravel 11, PHP 8.2+, SQLite
- **Frontend** : Vue 3.5, Pinia 3.0, Vue Router 4.6, Tailwind CSS v4
- **Build** : Vite 7.0, Docker
- **Tests** : Vitest (frontend), PHPUnit (backend)
- **API externes** : Blizzard API (OAuth + Game Data), SimpleArmory JSON, DB2 CSVs (wago.tools), Wowhead CDN

### Architecture Clean

L'application suit une architecture en 3 couches :

- **Domain** : entités, value objects, services de domaine
- **Application** : services applicatifs, DTOs, agrégateurs
- **Infrastructure** : client API Blizzard, parseurs, importateurs

### Arborescence

```
app/
├── Console/Commands/          # Commandes Artisan
├── Domain/
│   ├── Entities/              # Character, CollectionItem
│   ├── ValueObjects/          # ExpansionId, CharacterMedia
│   └── Services/              # ExpansionClassifier
├── Application/
│   ├── DTOs/                  # CharacterProfileDTO, CrossCharacterProgress, AccountScoreProgress
│   └── Services/
│       └── Progress/          # Agrégateurs (Quests, Achievements, Collections, Professions, Reputations)
├── Infrastructure/
│   ├── Blizzard/              # BlizzardApiClient, BlizzardBatchImporter, Importers/
│   ├── Parsers/               # SimpleArmoryParser, LuaAddonParser, Db2*Mapper
│   └── Mappings/              # ExpansionMapping
├── Http/
│   ├── Controllers/           # 8 contrôleurs
│   └── Middleware/            # SecurityHeaders, EnsureIsAdmin
├── Jobs/                      # RunImportJob, ComputeCrossCharacterJob
└── Models/                    # 11 modèles Eloquent
resources/js/
├── components/                # 24 composants Vue
├── pages/                     # 16 pages
├── stores/                    # 2 stores Pinia (character, tasks)
└── utils/                     # classColors, scoreCalculator, etc.
```

### Commandes de développement

- `make build-assets` : Build des assets frontend via Docker
- `make quality` : Vérification qualité (linting, tests)
- `docker compose exec app php artisan ...` : Commandes Artisan

---

## 2. Tables de référence des routes

### 2.1 Routes Web (routes/web.php)

| Méthode | URI | Contrôleur | Méthode | Description |
|---------|-----|------------|---------|-------------|
| GET | `/auth/blizzard/redirect` | AuthController | `redirect()` | Redirection OAuth vers Battle.net |
| GET | `/auth/blizzard/callback` | AuthController | `callback()` | Callback OAuth, échange du code pour un token |
| GET | `/robots.txt` | SeoController | `robots()` | Fichier robots.txt pour les moteurs de recherche |
| GET | `/sitemap.xml` | SeoController | `sitemap()` | Index du sitemap XML |
| GET | `/sitemap-pages.xml` | SeoController | `sitemapPages()` | Sitemap des pages statiques |
| GET | `/sitemap-characters.xml` | SeoController | `sitemapCharacters()` | Sitemap des pages personnages |
| GET | `/character/{realm}/{name}` | SeoController | `characterPage()` | Page personnage avec SEO (redirection 301 si majuscules) |
| GET | `/base-de-donnees` | DatabaseController | `index()` | Page d'accueil base de données |
| GET | `/base-de-donnees/montures/{category?}` | DatabaseController | `mounts()` | Page montures (filtre catégorie optionnel) |
| GET | `/base-de-donnees/hauts-faits/{expansion?}` | DatabaseController | `achievements()` | Page hauts-faits (filtre extension optionnel) |
| GET | `/base-de-donnees/quetes/{expansion?}/{zone?}` | DatabaseController | `quests()` | Page quêtes (filtres extension et zone optionnels) |
| GET | `/base-de-donnees/mascottes/{category?}` | DatabaseController | `pets()` | Page mascottes (filtre catégorie optionnel) |
| GET | `/base-de-donnees/decorations/{category?}` | DatabaseController | `decors()` | Page décorations (filtre catégorie optionnel) |
| GET | `/base-de-donnees/professions/{profession?}` | DatabaseController | `professions()` | Page professions (filtre profession optionnel) |
| GET | `/{any?}` | SeoController | `spa()` | Fallback SPA (tout ce qui n'est pas /api/) |

### 2.2 Routes API publiques (throttle:api)

| Méthode | URI | Contrôleur | Méthode | Description |
|---------|-----|------------|---------|-------------|
| GET | `/api/character/{realm}/{name}` | CharacterController | `show()` | Profil complet d'un personnage |
| GET | `/api/database/counts` | DatabaseApiController | `counts()` | Compteurs de toutes les collections |
| GET | `/api/database/mounts` | DatabaseApiController | `mounts()` | Liste des montures (filtre ?category=) |
| GET | `/api/database/achievements` | DatabaseApiController | `achievements()` | Liste des hauts-faits (filtre ?expansion=) |
| GET | `/api/database/quests` | DatabaseApiController | `quests()` | Liste des quêtes (filtres ?expansion=&zone=) |
| GET | `/api/database/pets` | DatabaseApiController | `pets()` | Liste des mascottes (filtre ?category=) |
| GET | `/api/database/decors` | DatabaseApiController | `decors()` | Liste des décorations (filtre ?category=) |
| GET | `/api/database/professions` | DatabaseApiController | `professions()` | Liste des professions avec compteurs |
| GET | `/api/database/professions/recipes` | DatabaseApiController | `professionRecipes()` | Recettes par profession (filtres ?profession=&expansion=) |

### 2.3 Routes API authentifiées (throttle:authenticated)

| Méthode | URI | Contrôleur | Méthode | Description |
|---------|-----|------------|---------|-------------|
| GET | `/api/auth/status` | UserCharacterController | `authStatus()` | Statut d'authentification {authenticated, isAdmin} |
| POST | `/api/auth/logout` | UserCharacterController | `logout()` | Déconnexion |
| GET | `/api/user/characters` | UserCharacterController | `index()` | Liste des personnages du compte |
| GET | `/api/class-icons` | UserCharacterController | `classIcons()` | Mapping classId → icône |
| GET | `/api/account/score` | UserCharacterController | `accountScore()` | Score du compte (calcul progressif) |
| POST | `/api/account/score/refresh` | UserCharacterController | `refreshAccountScore()` | Invalide le cache du score |
| GET | `/api/account/cross-character` | UserCharacterController | `crossCharacter()` | Lance le calcul cross-personnage |
| GET | `/api/account/cross-character/{jobId}` | UserCharacterController | `crossCharacterStatus()` | Statut du job cross-personnage |
| GET | `/api/account/cross-character-data` | UserCharacterController | `crossCharacterData()` | Données cross-personnage calculées |
| GET | `/api/character-tasks` | CharacterTaskController | `index()` | Tâches du joueur |
| POST | `/api/character-tasks` | CharacterTaskController | `store()` | Créer une tâche |
| PUT | `/api/character-tasks/{id}` | CharacterTaskController | `update()` | Basculer l'état d'une tâche |
| DELETE | `/api/character-tasks/{id}` | CharacterTaskController | `destroy()` | Supprimer une tâche |

### 2.4 Routes API admin (throttle:authenticated + admin)

| Méthode | URI | Contrôleur | Méthode | Description |
|---------|-----|------------|---------|-------------|
| GET | `/api/admin/status` | AdminController | `status()` | Statut maintenance |
| POST | `/api/admin/import` | AdminController | `import()` | Lancer un import (queue) |
| GET | `/api/admin/import/{jobId}` | AdminController | `importStatus()` | Statut d'un job d'import |
| POST | `/api/admin/clear-cache` | AdminController | `clearCache()` | Vider tous les caches |
| POST | `/api/admin/maintenance` | AdminController | `maintenance()` | Activer/désactiver la maintenance |
| POST | `/api/admin/discord` | AdminController | `discord()` | Envoyer un embed Discord |

### 2.5 Routes Vue Router (frontend)

| Path | Nom | Composant | Lazy-loaded |
|------|-----|-----------|-------------|
| `/` | home | HomePage | Non |
| `/character/:realm/:name` | character | CharacterPage | Non |
| `/my-characters` | my-characters | MyCharactersPage | Non |
| `/class-stats` | class-stats | ClassStatsPage | Non |
| `/my-score` | my-score | AccountScorePage | Non |
| `/base-de-donnees` | database-index | DatabaseIndexPage | Oui |
| `/base-de-donnees/montures/:category?` | database-mounts | DatabaseMountsPage | Oui |
| `/base-de-donnees/hauts-faits/:expansion?` | database-achievements | DatabaseAchievementsPage | Oui |
| `/base-de-donnees/quetes/:expansion?/:zone?` | database-quests | DatabaseQuestsPage | Oui |
| `/base-de-donnees/mascottes/:category?` | database-pets | DatabasePetsPage | Oui |
| `/base-de-donnees/decorations/:category?` | database-decors | DatabaseDecorsPage | Oui |
| `/base-de-donnees/professions/:profession?` | database-professions | DatabaseProfessionsPage | Oui |
| `/privacy` | privacy | PrivacyPage | Non |
| `/cgu` | cgu | CguPage | Non |
| `/faq` | faq | FaqPage | Non |
| `/admin` | admin | AdminPage | Oui |
| `/:pathMatch(.*)*` | — | Redirect → `/` | — |

---

## 3. Middleware

### 3.1 SecurityHeaders

**Fichier** : `app/Http/Middleware/SecurityHeaders.php`

Appliqué à toutes les réponses via `bootstrap/app.php`.

Headers appliqués :

- `X-Frame-Options: DENY`
- `X-Content-Type-Options: nosniff`
- `Referrer-Policy: strict-origin-when-cross-origin`
- `Permissions-Policy: camera=(), microphone=(), geolocation=()`

Headers production uniquement :

- `Strict-Transport-Security: max-age=31536000; includeSubDomains`
- Content-Security-Policy avec : `default-src 'self'`, `script-src 'self' https://umami.wowplanet.fr`, `img-src 'self' https://wow.zamimg.com https://render.worldofwarcraft.com data:`, etc.

### 3.2 EnsureIsAdmin

**Fichier** : `app/Http/Middleware/EnsureIsAdmin.php`

Alias : `admin`

Vérifie que `session('is_admin')` est `true`. Retourne 403 `{error: 'Forbidden'}` sinon.

### 3.3 Rate Limiters (AppServiceProvider)

- `api` : 60 requêtes/minute par IP
- `authenticated` : 30 requêtes/minute par ID de session

---

## 4. Contrôleurs

### 4.1 AuthController

**Fichier** : `app/Http/Controllers/AuthController.php`

**Dépendances** : Config Blizzard (client_id, client_secret, redirect_uri, region)

#### `redirect()` → RedirectResponse

Génère un token state aléatoire (40 caractères), le stocke en session (`blizzard_oauth_state`), et redirige vers `https://{region}.battle.net/oauth/authorize` avec le scope `wow.profile`.

#### `callback(Request $request)` → RedirectResponse

1. Vérifie le paramètre `state` contre la session
2. Échange le `code` pour un access token via POST à `https://{region}.battle.net/oauth/token`
3. Stocke le token en session (`blizzard_user_token`)
4. Appelle `fetchAndStoreUserInfo()` pour récupérer les infos utilisateur
5. Redirige vers `/`

#### `fetchAndStoreUserInfo(string $accessToken)` → void (privée)

Récupère les infos depuis `https://{region}.battle.net/oauth/userinfo`. Stocke en session : `bnet_user_id`, `bnet_battletag`. Définit `is_admin` si le `sub` correspond à `services.blizzard.admin_bnet_id`.

### 4.2 SeoController

**Fichier** : `app/Http/Controllers/SeoController.php`

**Dépendances** : CharacterSeoService, SeoContentRenderer

#### `spa()` → View

Page d'accueil SPA. Génère les métadonnées SEO via `CharacterSeoService::getHomeMeta()` et le HTML serveur via `SeoContentRenderer::renderHome()`.

#### `characterPage(string $realm, string $name)` → View|RedirectResponse

Normalise realm/name en minuscules (301 si nécessaire). Génère les métadonnées SEO et les données personnage en cache. Retourne la vue `welcome` avec le HTML pré-rendu.

#### `sitemap()` → Response (XML)

#### `sitemapPages()` → Response (XML)

#### `sitemapCharacters()` → Response (XML)

#### `robots()` → Response (text/plain)

Allow: `/`, `/base-de-donnees/`. Disallow: `/api/`, `/auth/`, `/admin`, `/my-characters`, `/my-score`, `/class-stats`.

### 4.3 DatabaseController

**Fichier** : `app/Http/Controllers/DatabaseController.php`

**Dépendances** : DatabaseSeoService, SeoContentRenderer

Toutes les méthodes retournent la vue `welcome` avec les métadonnées SEO et le HTML pré-rendu pour les crawlers.

#### `index()` → View

#### `mounts(?string $category = null)` → View

#### `achievements(?string $expansion = null)` → View

#### `quests(?string $expansion = null, ?string $zone = null)` → View

#### `pets(?string $category = null)` → View

#### `decors(?string $category = null)` → View

#### `professions(?string $profession = null)` → View

### 4.4 CharacterController

**Fichier** : `app/Http/Controllers/CharacterController.php`

**Dépendances** : CharacterProfileService, CrossCharacterService, UserCharacterService

#### `show(string $realm, string $name)` → JsonResponse

1. Appelle `CharacterProfileService::getProfile($realm, $name)`
2. Si authentifié, tente un merge cross-personnage via `CrossCharacterService::mergeCurrentCharacter($profile)`
3. Retourne le profil en JSON (200) ou `{error: '...'}` (404)

### 4.5 DatabaseApiController

**Fichier** : `app/Http/Controllers/DatabaseApiController.php`

**Dépendances** : Aucune injection (accès direct aux modèles)

#### `counts()` → JsonResponse

Retourne les compteurs : `{mounts, achievements, quests, pets, decors, professions, recipes}` (uniquement `is_active = true`).

#### `mounts(Request $request)` → JsonResponse

Query param: `?category=slug`. Retourne `{items, categories, total}`. Items: id, name_fr, source, category, source_spell_id, icon_url.

#### `achievements(Request $request)` → JsonResponse

Query param: `?expansion=slug`. Utilise `ExpansionId::fromSlug()`. Retourne `{items, expansions, total}`.

#### `quests(Request $request)` → JsonResponse

Query params: `?expansion=slug&zone=slug`. Retourne `{items, expansions, zones, total}`. Les zones ne sont disponibles que si une expansion est sélectionnée.

#### `pets(Request $request)` → JsonResponse

Query param: `?category=slug`. Retourne `{items, categories, total}`.

#### `decors(Request $request)` → JsonResponse

Query param: `?category=slug`. Retourne `{items, categories, total}`.

#### `professions()` → JsonResponse

Retourne `{professions: [{id, name_fr, type, slug, recipe_count}], total_professions, total_recipes}`.

#### `professionRecipes(Request $request)` → JsonResponse

Query params: `?profession=slug&expansion=slug`. Retourne `{items, expansions, profession}`.

#### Helpers privés

- `slugify(string)` : Convertit un texte en slug URL (minuscules, tirets)
- `deSlugify(string, context, ?expansionId)` : Retrouve le nom original depuis un slug en cherchant dans la base
- `buildExpansionList(modelClass, ?professionId)` : Construit la liste des extensions avec compteurs
- `buildCategoryList(modelClass)` : Construit la liste des catégories avec compteurs

### 4.6 UserCharacterController

**Fichier** : `app/Http/Controllers/UserCharacterController.php`

**Dépendances** : UserCharacterService, AccountScoreService, CrossCharacterService

#### `authStatus()` → JsonResponse

Retourne `{authenticated: bool, isAdmin: bool}`.

#### `logout()` → JsonResponse

Appelle `UserCharacterService::logout()`. Retourne `{success: true}`.

#### `index()` → JsonResponse

Retourne la liste des personnages via `UserCharacterService::getUserCharacters()`. Requiert authentification (401).

#### `classIcons()` → JsonResponse

Retourne le mapping classId → URL d'icône via `UserCharacterService::getClassIcons()`.

#### `accountScore()` → JsonResponse

Appelle `AccountScoreService::getOrCompute()`. Retourne le score ou le statut de calcul en cours.

#### `refreshAccountScore()` → JsonResponse

Invalide le cache du score via `AccountScoreService::invalidate()`.

#### `crossCharacter()` → JsonResponse

Lance le calcul cross-personnage via `CrossCharacterService::compute()`. Retourne `{status, jobId}`.

#### `crossCharacterStatus(string $jobId)` → JsonResponse

Retourne le statut du job via `CrossCharacterService::getJobStatus($jobId)`.

#### `crossCharacterData()` → JsonResponse

Retourne les données cross-personnage stockées ou `{status: 'not_available'}`.

### 4.7 CharacterTaskController

**Fichier** : `app/Http/Controllers/CharacterTaskController.php`

**Dépendances** : CharacterTaskService

**Authentification** : Toutes les méthodes nécessitent `blizzard_user_token` en session.

#### `index()` → JsonResponse

Retourne les tâches de l'utilisateur via `CharacterTaskService::getTasksForUser($bnetUserId)`.

#### `store(Request $request)` → JsonResponse (201)

Validation : `realm_slug` (requis), `character_name` (requis), `name` (requis, max 255), `reset_type` (requis, daily|weekly).

#### `update(int $id)` → JsonResponse

Bascule l'état de complétion via `CharacterTaskService::toggleTask($id, $bnetUserId)`.

#### `destroy(int $id)` → JsonResponse (204)

Supprime la tâche via `CharacterTaskService::deleteTask($id, $bnetUserId)`.

### 4.8 AdminController

**Fichier** : `app/Http/Controllers/AdminController.php`

**Dépendances** : AdminService

**Middleware** : `throttle:authenticated` + `admin`

#### `status()` → JsonResponse

Retourne `{maintenance: bool}`.

#### `import(Request $request)` → JsonResponse

Validation : `command` (requis, in: app:download-db2, app:wow-data-import, app:wow-data-refresh, app:wow-quest-faction-tag), `type` (nullable, in: all, achievements, quests, mounts, pets, professions, decor). Dispatche un `RunImportJob` en file d'attente. Retourne `{jobId}`.

#### `importStatus(string $jobId)` → JsonResponse

Retourne le statut du job d'import via `AdminService::getImportJobStatus($jobId)`.

#### `clearCache()` → JsonResponse

Vide tous les caches (config, routes, views, cache). Retourne `{output}`.

#### `maintenance(Request $request)` → JsonResponse

Validation : `enable` (requis, bool), `secret` (nullable, min 8). Active/désactive le mode maintenance.

#### `discord(Request $request)` → JsonResponse

Validation : `channel` (requis, in: changelog, discussion), `title` (requis, max 256), `description` (requis, max 4096), `color` (nullable, int), `fields` (nullable, array max 25). Envoie un embed Discord via webhook.

---

## 5. Services (couche Application)

### 5.1 CharacterProfileService

**Fichier** : `app/Application/Services/CharacterProfileService.php`

**Dépendances** : BlizzardApiClient, QuestProgressAggregator, AchievementProgressAggregator, CollectionProgressAggregator, ProfessionProgressAggregator, ReputationProgressAggregator, UserCharacterService

#### `getProfile(string $realm, string $name)` → CharacterProfileDTO

Orchestre la récupération complète d'un profil personnage depuis l'API Blizzard. Appelle 9 endpoints en parallèle (summary, media, quests, achievements, mounts, pets, professions, reputations, decor, M+). Agrège la progression via les 5 agrégateurs. Retourne un DTO complet. Retry jusqu'à 3 tentatives sur timeout/500/504/429.

#### `fetchCrossCharacterRawData(string $realm, string $name)` → array

Version allégée pour le cross-personnage (quêtes, achievements, réputations, professions uniquement).

### 5.2 UserCharacterService

**Fichier** : `app/Application/Services/UserCharacterService.php`

**Dépendances** : BlizzardApiClient

#### `isAuthenticated()` → bool

Vérifie le token en session.

#### `isAdmin()` → bool

Vérifie le flag `is_admin` en session.

#### `logout()` → void

Supprime les données d'auth de la session.

#### `getUserCharacters()` → array

Récupère la liste depuis l'API profile/user/wow, ajoute les avatars.

#### `getClassIcons()` → array

Cache 30 jours, mapping classId → URL d'icône.

### 5.3 AccountScoreService

**Fichier** : `app/Application/Services/AccountScoreService.php`

**Dépendances** : CharacterProfileService, UserCharacterService

#### `getOrCompute()` → array

Calcul progressif : traite 1 personnage par requête, stocke la progression en cache (1h), retourne le résultat final en cache (24h). Statuts possibles : `computing`, `ready`, `failed`, `unauthenticated`.

#### `invalidate()` → void

Supprime le cache du score.

### 5.4 CrossCharacterService

**Fichier** : `app/Application/Services/CrossCharacterService.php`

**Dépendances** : BlizzardApiClient, UserCharacterService

#### `compute()` → array

Retourne les données en cache si fraîches (24h), sinon dispatche un `ComputeCrossCharacterJob`. Retourne `{status, jobId}` ou `{status: 'ready', data}`.

#### `getJobStatus(string $jobId)` → array

Retourne le statut du job depuis le cache.

#### `getStoredData()` → ?array

Lit les données depuis le modèle `CrossCharacterData`.

#### `mergeCurrentCharacter(CharacterProfileDTO $profile)` → void

Mise à jour opportuniste : merge les données du personnage consulté dans le CrossCharacterData existant.

### 5.5 CharacterTaskService

**Fichier** : `app/Application/Services/CharacterTaskService.php`

#### `getTasksForUser(string $bnetUserId)` → Collection

#### `createTask(string $bnetUserId, array $data)` → CharacterTask

#### `toggleTask(int $taskId, string $bnetUserId)` → CharacterTask

Bascule is_completed, met à jour completed_at.

#### `deleteTask(int $taskId, string $bnetUserId)` → void

Vérifie la propriété, 403 si non autorisé.

### 5.6 CharacterSeoService

**Fichier** : `app/Application/Services/CharacterSeoService.php`

**Dépendances** : BlizzardApiClient

#### `getHomeMeta()` → array

Métadonnées SEO pour la page d'accueil.

#### `getCharacterMeta(string $realm, string $name)` → array

Meta + JSON-LD pour un personnage (cache 1h).

#### `getCachedCharacterData(string $realm, string $name)` → ?array

Données personnage en cache.

#### `generateSitemapIndex()` → string

Index XML (cache 1h).

#### `generatePagesSitemap()` → string

Pages statiques (cache 24h).

#### `generateCharactersSitemap()` → string

Personnages récents via CharacterVisit (cache 3h).

### 5.7 DatabaseSeoService

**Fichier** : `app/Application/Services/DatabaseSeoService.php`

#### `getIndexMeta()` → array

#### `getMountsMeta(?string $categorySlug)` → array

#### `getAchievementsMeta(?string $expansionSlug)` → array

#### `getQuestsMeta(?string $expansionSlug, ?string $zoneSlug)` → array

#### `getPetsMeta(?string $categorySlug)` → array

#### `getDecorsMeta(?string $categorySlug)` → array

#### `getProfessionsMeta(?string $professionSlug)` → array

Chaque méthode retourne un array avec title, description, canonical, og:*, JSON-LD (CollectionPage, BreadcrumbList).

### 5.8 SeoContentRenderer

**Fichier** : `app/Application/Services/SeoContentRenderer.php`

Rend le HTML côté serveur pour les crawlers (même contenu que la SPA mais en HTML statique).

#### `renderHome()` → string

#### `renderCharacter(array $data)` → string

#### `renderDatabaseIndex()` → string

#### `renderMounts()` → string

#### `renderAchievements()` → string

#### `renderQuests()` → string

#### `renderPets()` → string

#### `renderDecors()` → string

#### `renderProfessions()` → string

### 5.9 AdminService

**Fichier** : `app/Application/Services/AdminService.php`

#### `runImportCommand(string $command, array $params)` → string

Exécute une commande Artisan autorisée.

#### `clearCaches()` → string

Vide config, routes, views, cache.

#### `toggleMaintenance(bool $enable, ?string $secret)` → bool

Active/désactive le mode maintenance.

#### `isInMaintenance()` → bool

#### `sendDiscordEmbed(string $channel, array $embed)` → bool

POST webhook Discord.

#### `getImportJobStatus(string $jobId)` → array

Lit le statut depuis le cache.

---

## 6. Couche Domain

### 6.1 ExpansionClassifier

**Fichier** : `app/Domain/Services/ExpansionClassifier.php`
**Dépendances** : ExpansionMapping

Service de domaine qui mappe des IDs de jeu vers des extensions.

#### `classifyZone(int $zoneId)` → ExpansionId
#### `classifyQuest(int $questId, ?int $zoneId)` → ExpansionId — Fallback vers la zone si pas de mapping direct
#### `classifyAchievement(int $achievementId)` → ExpansionId
#### `classifyAchievementCategory(int $categoryId)` → ExpansionId

### 6.2 Character (entité)

**Fichier** : `app/Domain/Entities/Character.php`
Value object immuable représentant un personnage WoW.

Propriétés : `name`, `realm`, `race`, `class`, `level`, `ilvl`, `faction`, `media` (CharacterMedia).

### 6.3 CollectionItem (interface)

**Fichier** : `app/Domain/Entities/CollectionItem.php`
Contrat pour les items avec association à une extension.

Méthodes : `getExpansionId()` → ExpansionId, `getName()` → string, `getId()` → int.

---

## 7. DTOs et Value Objects

### 7.1 CharacterProfileDTO

**Fichier** : `app/Application/DTOs/CharacterProfileDTO.php`

Classe readonly. Propriétés :

- `name` (string), `realm` (string), `race` (string), `class` (string), `classId` (int), `level` (int), `ilvl` (int), `faction` (string)
- `avatarUrl` (string), `classIconUrl` (string)
- `guild` (?string)
- `collections` (array) -- progression par extension : `[expansionId => {quests, achievements, reputations}]`
- `mounts` (array), `pets` (array), `professions` (array), `decor` (array)
- `mountsCount` (int), `petsCount` (int), `decorCount` (int), `achievementPoints` (int), `exaltedCount` (int)
- `mythicKeystone` (?array)
- `completedQuestIds` (array), `completedAchievementIds` (array)

### 7.2 CrossCharacterProgress

**Fichier** : `app/Application/DTOs/CrossCharacterProgress.php`

DTO mutable pour l'agrégation cross-personnage.

Propriétés :

- `completedQuestIds` : `[questId => characterName]`
- `completedAchievementIds` : `[achievementId => characterName]`
- `completedRecipeIds` : `[recipeId => true]`
- `bestFactionStandings` : `[factionId => {character_name, tier, raw, renown_level, standing_name, completed}]`
- `recipeOwners` : `[recipeId => characterName]`
- `skillPointOwners` : `[profId][expId] => {character_name, skill_points, max_skill_points}`

Méthodes :

- `mergeCharacter(string $name, array $rawData)` -> void
- `mergeFromProfile(string $name, CharacterProfileDTO $dto)` -> void
- `buildResult()` -> array

### 7.3 AccountScoreProgress

**Fichier** : `app/Application/DTOs/AccountScoreProgress.php`

DTO mutable pour le calcul progressif du score.

Propriétés :

- `processed` (int), `errors` (array)
- `completedQuestIds`, `completedAchievementIds`, `completedRecipeIds` (array)
- `accountMounts`, `accountPets`, `accountDecor` (array)
- `bestSkillPoints`, `professionMap` (array)
- `bestProfessionStats` : `{completed, total}`

Méthodes :

- `mergeProfile(CharacterProfileDTO $dto)` -> void
- `buildResult()` -> array

### 7.4 ExpansionId (Value Object)

**Fichier** : `app/Domain/ValueObjects/ExpansionId.php`

Constantes :

| Constante       | Valeur | Nom                      | Slug                   |
|-----------------|--------|--------------------------|------------------------|
| CLASSIC         | 0      | Classic                  | classic                |
| BURNING_CRUSADE | 1      | Burning Crusade          | burning-crusade        |
| WRATH           | 2      | Wrath of the Lich King   | wrath-of-the-lich-king |
| CATACLYSM       | 3      | Cataclysm                | cataclysm              |
| PANDARIA        | 4      | Mists of Pandaria        | mists-of-pandaria      |
| DRAENOR         | 5      | Warlords of Draenor      | warlords-of-draenor    |
| LEGION          | 6      | Legion                   | legion                 |
| BFA             | 7      | Battle for Azeroth       | battle-for-azeroth     |
| SHADOWLANDS     | 8      | Shadowlands              | shadowlands            |
| DRAGONFLIGHT    | 9      | Dragonflight             | dragonflight           |
| WAR_WITHIN      | 10     | The War Within           | the-war-within         |
| MIDNIGHT        | 11     | Midnight                 | midnight               |

Méthodes :

- `toString()` -> string -- Nom complet de l'extension
- `toSlug()` -> string -- Slug URL
- `fromSlug(string $slug)` -> ?self -- Lookup inverse
- `allSlugs()` -> array -- Tous les slugs indexés par ID

### 7.5 CharacterMedia (Value Object)

**Fichier** : `app/Domain/ValueObjects/CharacterMedia.php`

Classe readonly avec : `avatarUrl`, `insetUrl`, `mainUrl`.

---

## 8. Agrégateurs de progression

### 8.1 QuestProgressAggregator

**Fichier** : `app/Application/Services/Progress/QuestProgressAggregator.php`

#### `aggregate(array $completedQuestIds, string $faction)` -> array

1. Charge toutes les quêtes actives filtrées par faction
2. Groupe par `expansion_id` puis par `zone_name`
3. Pour chaque zone, compte les quêtes complétées
4. Retourne `[expansionId => {total, completed, zones: [{name, total, completed, items}]}]`

### 8.2 AchievementProgressAggregator

**Fichier** : `app/Application/Services/Progress/AchievementProgressAggregator.php`

#### `aggregate(array $completedAchievementIds)` -> array

Même logique que QuestProgressAggregator mais groupé par `category_name`.
Retourne `[expansionId => {total, completed, categories: [{name, total, completed, items}]}]`

### 8.3 CollectionProgressAggregator

**Fichier** : `app/Application/Services/Progress/CollectionProgressAggregator.php`

#### `aggregateMounts(array $collectedIds)` -> array

#### `aggregatePets(array $collectedIds)` -> array

#### `aggregateDecor(array $collectedIds)` -> array

Compare les IDs possédés avec la base complète. Retourne `[{id, name, is_completed, source, category, wowhead_id, icon_url}]`.

### 8.4 ProfessionProgressAggregator

**Fichier** : `app/Application/Services/Progress/ProfessionProgressAggregator.php`

#### `aggregate(array $professionsResponse, string $faction)` -> array

Parse la réponse API des professions, extrait les tiers/recettes/skill points, croise avec les recettes en base. Retourne `[{profession_id, profession_name, type, global_skill_points, global_max_skill_points, expansions}]`.

### 8.5 ReputationProgressAggregator

**Fichier** : `app/Application/Services/Progress/ReputationProgressAggregator.php`

#### `aggregate(array $reputationsResponse, string $faction)` -> array

Utilise `Db2FactionExpansionMapper` pour mapper faction -> extension. Classifie les réputations comme "started" (données API) ou "unstarted" (lookup DB2). Complétion : tier >= 7 OU renown_level >= max_renown.

---

## 9. Modèles Eloquent

| Modèle             | Table                | Clé primaire | Auto-incr. | Propriétés                                                                                   | Relations                |
|---------------------|----------------------|-------------|------------|----------------------------------------------------------------------------------------------|--------------------------|
| User                | users                | id          | Oui        | name, email, password                                                                        | --                       |
| WowAchievement      | wow_achievements     | id          | Non        | name_fr, expansion_id, category_name, icon_url, points, faction, is_active                   | --                       |
| WowQuest            | wow_quests           | id          | Non        | name_fr, expansion_id, zone_name, faction, is_active                                         | --                       |
| WowMount            | wow_mounts           | id          | Non        | name_fr, source, category, source_spell_id, icon_url, is_active                              | --                       |
| WowPet              | wow_pets             | id          | Non        | name_fr, category, source, creature_id, icon_url, is_active                                  | --                       |
| WowDecor            | wow_decors           | id          | Non        | name_fr, category, source, item_id, icon_url, is_active                                      | --                       |
| WowProfession       | wow_professions      | id          | Non        | name_fr, type, max_skill_levels (JSON), is_active                                            | hasMany(WowRecipe)       |
| WowRecipe           | wow_recipes          | id          | Non        | name_fr, profession_id, expansion_id, category_name, faction, wowhead_spell_id, is_active    | belongsTo(WowProfession) |
| CharacterTask       | character_tasks      | id          | Oui        | bnet_user_id, realm_slug, character_name, name, reset_type, is_completed, completed_at, sort_order | --                       |
| CharacterVisit      | character_visits     | id          | Oui        | realm_slug, character_name, display_name, display_realm, class_name, level, last_visited_at  | --                       |
| CrossCharacterData  | cross_character_data | bnet_user_id | Non       | data (JSON), character_count, fetched_at                                                     | --                       |

Notes :

- Les modèles WoW utilisent les IDs Blizzard (non auto-incrémentés)
- `is_active` permet une suppression logique sans supprimer les données
- `CrossCharacterData` n'utilise pas les timestamps Laravel

---

## 10. Infrastructure

### 10.1 BlizzardApiClient

**Fichier** : `app/Infrastructure/Blizzard/BlizzardApiClient.php`

Client HTTP basé sur Guzzle pour l'API Blizzard.

- `getAccessToken()` -> string -- Token client_credentials, cache 1h
- `get(string $endpoint, array $query)` -> array -- GET synchrone, namespace `profile-{region}`
- `getAsync(string $endpoint, array $query)` -> PromiseInterface -- GET asynchrone
- `getWithUserToken(string $endpoint, string $token, array $query)` -> array -- GET avec token utilisateur
- `getClient()` -> Client -- Instance Guzzle sous-jacente

Configuration (config/services.php) : `blizzard.client_id`, `blizzard.client_secret`, `blizzard.region` (défaut: eu), `blizzard.redirect_uri`, `blizzard.admin_bnet_id`

### 10.2 BlizzardBatchImporter

**Fichier** : `app/Infrastructure/Blizzard/BlizzardBatchImporter.php`

Facade d'import déléguant aux importateurs individuels.

- `importAchievements()` -> void
- `importMounts()` -> void
- `importPets(array $spellNameMap)` -> void
- `importDecor()` -> void
- `importQuests(array $areaMap, array $questMap, array $factionMap, array $zoneMap)` -> void
- `importProfessions(array $spellNameMap, array $recipeFactionMap)` -> void
- `tagMirrorQuestFactions(array $reputationMap)` -> void
- `tagMirrorRecipeFactions()` -> void

### 10.3 Importateurs individuels

#### AchievementImporter

**Fichier** : `app/Infrastructure/Blizzard/Importers/AchievementImporter.php`

**Sources** : `achievements.json` (SimpleArmory) + `achievement.csv` (DB2 frFR)

**Processus** : Parse la hiérarchie SA (4 niveaux) -> extrait catégorie, extension, icône, points, faction -> charge les noms FR depuis le CSV -> upsert `WowAchievement`

#### MountImporter

**Fichier** : `app/Infrastructure/Blizzard/Importers/MountImporter.php`

**Sources** : `mounts.json` (SimpleArmory) + `mount.csv` (DB2 frFR)

**Processus** : Parse SA (3 niveaux) -> extrait catégorie, source, icône, source_spell_id -> noms FR -> upsert `WowMount`

#### PetImporter

**Fichier** : `app/Infrastructure/Blizzard/Importers/PetImporter.php`

**Sources** : `pets.json` (SimpleArmory) + `battle_pet_species.csv` + `spell_name.csv` (DB2 frFR)

**Processus** : Parse SA -> extrait creature_id via SummonSpellID -> noms FR (retire les préfixes "Invoque") -> upsert `WowPet`

#### DecorImporter

**Fichier** : `app/Infrastructure/Blizzard/Importers/DecorImporter.php`

**Sources** : `decors.json` (SimpleArmory) + `housetdecor.csv` (DB2 frFR) + API Blizzard pour les icônes

**Processus** : Parse SA -> extrait item_id, marque is_active selon notObtainable -> noms FR -> appel API `media/item/{itemId}` pour les icônes -> upsert `WowDecor`

#### QuestImporter

**Fichier** : `app/Infrastructure/Blizzard/Importers/QuestImporter.php`

**Sources** : `quest_v2_cli_task.csv`, `QuestPOIBlob.csv`, `UiMap.csv`, ContentTuning

**Processus** : Noms depuis CSV -> zones via Db2QuestZoneMapper -> extension via ContentTuning -> faction via FiltRaces bitmask -> upsert `WowQuest`

#### ProfessionImporter

**Fichier** : `app/Infrastructure/Blizzard/Importers/ProfessionImporter.php`

**Sources** : `SkillLine.csv`, `SkillLineAbility.csv`, `TradeSkillCategory.csv`, `spell_name.csv`

**Processus** : Parse la hiérarchie SkillLine -> lie les sorts aux professions via SkillLineAbility -> catégorise par extension via TradeSkillCategory -> upsert `WowProfession` + `WowRecipe`

### 10.4 Parseurs

#### SimpleArmoryParser

**Fichier** : `app/Infrastructure/Parsers/SimpleArmoryParser.php`

Parse les fichiers JSON de simplearmory.com.

##### `parseAchievements()` -> array

Hiérarchie 4 niveaux : supercats > cats > subcats > items. Retourne `[id => {category, expansion_id, icon, points, faction}]`

##### `parseCollection(string $filename)` -> array

Hiérarchie 3 niveaux : categories > subcats > items. Retourne `[id => {category, source, icon, faction, ...}]`

##### `buildIconUrl(string $iconName)` -> ?string

Construit l'URL Wowhead CDN : `https://wow.zamimg.com/images/wow/icons/medium/{name}.jpg`

#### LuaAddonParser (facade)

**Fichier** : `app/Infrastructure/Parsers/LuaAddonParser.php`

Interface unifiée déléguant aux parseurs spécialisés.

- `buildAreaExpansionMap()` -> Db2AreaExpansionMapper
- `getQuestExpansionMap()` -> AddonDataParser
- `getQuestFactionMap()` -> AddonDataParser
- `getZoneFactionMap()` -> AddonDataParser
- `getReputationFactionMap()` -> AddonDataParser
- `getZoneExpansionMap()` -> AddonDataParser
- `getSpellNameMap()` -> Db2CsvLoader (charge SpellName.csv, ~400k lignes)
- `normalizeApostrophes(string)` -> Remplace les apostrophes courbes (U+2019) et espaces insécables (U+00A0)
- `getRecipeFactionMap()` -> AddonDataParser

#### Db2AreaExpansionMapper

**Fichier** : `app/Infrastructure/Parsers/Db2AreaExpansionMapper.php`

Résout area_id -> expansion_id via la chaîne : `AreaTable.ContinentID -> Map.ExpansionID` + `ContentTuningID -> ContentTuning.ExpansionID`. ~55 overrides manuels dans `AREA_EXPANSION_OVERRIDES`.

#### Db2QuestZoneMapper

**Fichier** : `app/Infrastructure/Parsers/Db2QuestZoneMapper.php`

Mappe quest_id -> zone_name via `QuestPOIBlob.csv` (quest -> ui_map_id) + `UiMap.csv` (hiérarchie de maps).

#### Db2ProfessionMapper

**Fichier** : `app/Infrastructure/Parsers/Db2ProfessionMapper.php`

Construit les professions + recettes depuis SkillLine (professions racine: CategoryID=11, ParentSkillLineID=0), SkillLineAbility (liens sort -> profession), TradeSkillCategory (catégorie -> extension).

#### Db2FactionExpansionMapper

**Fichier** : `app/Infrastructure/Parsers/Db2FactionExpansionMapper.php`

Mappe les factions de réputation aux extensions.

#### AddonDataParser

**Fichier** : `app/Infrastructure/Parsers/AddonDataParser.php`

Parse les données de quêtes/factions/extensions depuis les CSV. Parsing single-pass pour extraire quêtes, expansion map et faction map simultanément.
Détermination de faction via bitmask FiltRaces : Alliance (bits 1,3,4,7,11,22,25,29,30,34,36,37), Horde (bits 2,5,6,8,9,10,26,27,28,31,33,35).

#### Db2CsvLoader

**Fichier** : `app/Infrastructure/Blizzard/Support/Db2CsvLoader.php`

Parseur CSV bas niveau.

- `loadStringMapByHeaders(filename, keyCol, valueCol)` -> array
- `loadMapByHeaders(filename, keyCol, valueCol)` -> array

### 10.5 Autres classes d'infrastructure

#### RateLimitingMiddleware

**Fichier** : `app/Infrastructure/Blizzard/RateLimitingMiddleware.php`

Middleware Guzzle pour le rate limiting de l'API Blizzard.

#### ExpansionTierMatcher

**Fichier** : `app/Infrastructure/Blizzard/ExpansionTierMatcher.php`

Mappe les noms de tiers de profession aux IDs d'extension (ex: "Dragonflight" -> 9).

#### ImportsFromBlizzardApi (trait)

**Fichier** : `app/Infrastructure/Blizzard/Concerns/ImportsFromBlizzardApi.php`

Mixin fournissant des utilitaires communs d'import : info(), fetchWithRetry(), fetchBatchAsync(), saveQuests(), etc.

---

## 11. Commandes Artisan

| Commande                  | Signature                                | Description                                                                                                       |
|---------------------------|------------------------------------------|-------------------------------------------------------------------------------------------------------------------|
| DownloadDb2DataCommand    | `app:download-db2 {--table=}`            | Télécharge les CSVs DB2 depuis wago.tools et les JSONs SA depuis simplearmory.com dans `storage/app/blizzard/`     |
| WowDataImportCommand      | `app:wow-data-import {--type=all}`       | Import depuis SA JSON + DB2 CSVs. Types : all, achievements, quests, mounts, pets, professions, decor. Augmente la mémoire à 512M. |
| WowDataRefreshCommand     | `app:wow-data-refresh {--type=all} {--force}` | Truncate + ré-import complet des données                                                                          |
| WowQuestFactionTagCommand | `app:wow-quest-faction-tag`              | Tag les factions miroir des quêtes via l'API Blizzard                                                             |
| PingSearchEnginesCommand  | `app:ping-search-engines`               | Notifie Bing de la mise à jour du sitemap                                                                         |
| GenerateFaviconsCommand   | `app:generate-favicons`                 | Génère les variantes de favicon depuis une image source                                                           |

---

## 12. Jobs (file d'attente)

### 12.1 RunImportJob

**Fichier** : `app/Jobs/RunImportJob.php`

**Queue** : imports | **Timeout** : 1200s

Exécute une commande Artisan d'import. Stocke le statut et la sortie dans le cache (`admin_import:{jobId}`, TTL 3600s). Paramètres : jobId, command, params.

### 12.2 ComputeCrossCharacterJob

**Fichier** : `app/Jobs/ComputeCrossCharacterJob.php`

**Queue** : imports | **Timeout** : 600s

Récupère toutes les données des personnages du compte, fusionne via `CrossCharacterProgress`, stocke dans `CrossCharacterData`. Retry avec backoff exponentiel (max 3 tentatives par endpoint, timeout 15s).

---

## 13. Frontend Vue

### 13.1 Structure principale

#### App.vue

**Fichier** : `resources/js/components/App.vue`

Composant racine. Layout : header -> main (router-view avec transitions fade) -> footer -> TaskSidebar (si authentifié). Gère le thème dark/light, vérifie l'authentification au montage.

#### AppHeader.vue

**Fichier** : `resources/js/components/AppHeader.vue`

Barre de navigation avec logo, liens de navigation (desktop et mobile), barre de recherche realm+personnage, bouton thème. Navigation conditionnelle (admin, déconnexion). Recherche -> push vers `/character/{realm}/{name}`.

#### AppFooter.vue

**Fichier** : `resources/js/components/AppFooter.vue`

Pied de page avec copyright et liens (Privacy, CGU, FAQ, Discord).

### 13.2 Pages

| Page                      | Fichier                                  | Description                                                                           |
|---------------------------|------------------------------------------|---------------------------------------------------------------------------------------|
| HomePage                  | `pages/HomePage.vue`                     | Accueil avec hero, CTA Battle.net, grille de fonctionnalités, liens base de données   |
| CharacterPage             | `pages/CharacterPage.vue`               | Profil personnage avec CharacterCard, 9 onglets (Score, M+, Quêtes, Hauts-faits, Réputations, Métiers, Montures, Mascottes, Déco) |
| MyCharactersPage          | `pages/MyCharactersPage.vue`            | Grille des personnages du compte avec recherche, tri (nom, niveau, classe, royaume), cross-character |
| ClassStatsPage            | `pages/ClassStatsPage.vue`              | Distribution des classes : podium (1er, 2e, 3e) + grille des autres classes           |
| AccountScorePage          | `pages/AccountScorePage.vue`            | Score du compte : radar 7 axes, score global, cartes par dimension, recommandations    |
| DatabaseIndexPage         | `pages/DatabaseIndexPage.vue`           | Accueil base de données (lazy-loaded)                                                 |
| DatabaseMountsPage        | `pages/DatabaseMountsPage.vue`          | Parcours des montures par catégorie (lazy-loaded)                                     |
| DatabaseAchievementsPage  | `pages/DatabaseAchievementsPage.vue`    | Parcours des hauts-faits par extension (lazy-loaded)                                  |
| DatabaseQuestsPage        | `pages/DatabaseQuestsPage.vue`          | Parcours des quêtes par extension et zone (lazy-loaded)                               |
| DatabasePetsPage          | `pages/DatabasePetsPage.vue`            | Parcours des mascottes par catégorie (lazy-loaded)                                    |
| DatabaseDecorsPage        | `pages/DatabaseDecorsPage.vue`          | Parcours des décorations par catégorie (lazy-loaded)                                  |
| DatabaseProfessionsPage   | `pages/DatabaseProfessionsPage.vue`     | Parcours des professions et recettes (lazy-loaded)                                    |
| AdminPage                 | `pages/AdminPage.vue`                   | Administration : imports, cache, maintenance, Discord (lazy-loaded)                   |
| FaqPage                   | `pages/FaqPage.vue`                     | FAQ avec 5 sections                                                                   |
| PrivacyPage               | `pages/PrivacyPage.vue`                 | Politique de confidentialité                                                          |
| CguPage                   | `pages/CguPage.vue`                     | Conditions générales d'utilisation                                                    |

### 13.3 Composants onglets de progression

| Composant       | Fichier                              | Description                                                      | Liens Wowhead          |
|-----------------|--------------------------------------|------------------------------------------------------------------|------------------------|
| ScoreTab        | `components/ScoreTab.vue`            | Score du personnage : radar + cartes dimensions + recommandations + partage | --                     |
| MythicPlusTab   | `components/MythicPlusTab.vue`       | Données Mythique+ du personnage                                  | --                     |
| QuestsTab       | `components/QuestsTab.vue`           | Quêtes par extension/zone, progression, recherche, pagination (8/page) | `quest={id}`           |
| AchievementsTab | `components/AchievementsTab.vue`     | Hauts-faits par extension/catégorie avec icônes                  | `achievement={id}`     |
| MountsTab       | `components/MountsTab.vue`           | Montures par catégorie/source, thème ambre                       | `spell={wowhead_id}`   |
| PetsTab         | `components/PetsTab.vue`             | Mascottes par catégorie/source, thème bleu                       | `npc={creature_id}`    |
| DecorTab        | `components/DecorTab.vue`            | Décorations par catégorie/source, thème violet                   | `item={item_id}`       |
| ProfessionsTab  | `components/ProfessionsTab.vue`      | Professions et recettes par extension                            | --                     |
| ReputationsTab  | `components/ReputationsTab.vue`      | Réputations par extension avec niveaux                           | --                     |

Tous les onglets utilisent : `ExpansionSelector` (sélection d'extension), `SearchFilter` (recherche + masquer complétés), pagination par cartes expansibles (8 par page). Les indicateurs cross-personnage ("fait sur un autre personnage") utilisent les Sets du store character.

### 13.4 Composants utilitaires

| Composant         | Fichier                              | Props                                                                    | Description                                              |
|-------------------|--------------------------------------|--------------------------------------------------------------------------|----------------------------------------------------------|
| SearchFilter      | `components/SearchFilter.vue`        | search, hideCompleted, placeholder, hideLabel, showHideToggle            | Barre de recherche + toggle "masquer complétés"          |
| ExpansionSelector | `components/ExpansionSelector.vue`   | expansions, activeExpansion, collections, collectionType, activeColor     | Grille de boutons d'extension avec barres de progression |
| CollectionIcon    | `components/CollectionIcon.vue`      | src, alt, fallback, size                                                 | Icône d'item avec fallback si l'image échoue             |
| CategoryIcon      | `components/CategoryIcon.vue`        | --                                                                       | Icône de catégorie de collection (emoji mappé)           |
| CharacterCard     | `components/CharacterCard.vue`       | character (objet)                                                        | En-tête personnage : avatar, nom, guilde, niveau, race, classe, stats |
| BreadcrumbNav     | `components/BreadcrumbNav.vue`       | crumbs (array {label, to?})                                              | Fil d'Ariane                                             |
| LoadingSpinner    | `components/LoadingSpinner.vue`      | icon, title, subtitle, hint                                              | Spinner animé avec anneaux concentriques                 |
| SkeletonLoader    | `components/SkeletonLoader.vue`      | --                                                                       | Placeholder de chargement animé                          |
| ScoreBadge        | `components/ScoreBadge.vue`          | score (0-100)                                                            | Badge circulaire SVG avec progression                    |
| ScoreRadar        | `components/ScoreRadar.vue`          | axes, size, colors                                                       | Graphique radar SVG à 7 axes                             |
| ShareScoreModal   | `components/ShareScoreModal.vue`     | --                                                                       | Modal de partage du score (embed Discord)                |
| TaskSidebar       | `components/TaskSidebar.vue`         | --                                                                       | Barre latérale des tâches quotidiennes/hebdomadaires     |

### 13.5 Stores Pinia

#### Store `character` (`stores/character.js`)

**State :**

- `character` : profil du personnage courant
- `loading`, `error` : états de chargement
- `isAuthenticated`, `isAdmin` : flags d'auth
- `userCharacters` : liste des personnages du compte
- `classIcons` : mapping classId -> URL
- `theme` : 'dark'|'light' (persisté localStorage)
- `expansions` : liste des extensions
- `crossCharacter` : données cross-personnage agrégées

**Getters :**

- `latestExpansionId` : ID de la dernière extension
- `expansionNamesDesc` : noms des extensions en ordre inversé
- `crossCharQuestIds`, `crossCharAchievementIds`, `crossCharRecipeIds` : Sets pour lookup O(1)

**Actions :**

- `toggleTheme()` : bascule dark/light, persiste dans localStorage
- `checkAuth()` : GET `/api/auth/status`
- `fetchCharacter(realm, name)` : GET `/api/character/{realm}/{name}`
- `fetchUserCharacters()` : GET `/api/user/characters`
- `fetchClassIcons()` : GET `/api/class-icons`
- `logout()` : POST `/api/auth/logout`, réinitialise le state
- `computeCrossCharacter()` : lance le job cross-personnage, poll jusqu'à complétion
- `loadCrossCharacterData()` : GET `/api/account/cross-character-data`
- `isQuestCompletedElsewhere(questId)`, `isAchievementCompletedElsewhere(achievementId)`, `isRecipeKnownElsewhere(recipeId)` : lookups cross-personnage
- `getQuestOwner(questId)`, `getAchievementOwner(achievementId)`, `getRecipeOwner(recipeId)` : nom du personnage propriétaire
- `getBestFactionStanding(factionId)`, `getBestSkillPoints(profId, expId)` : meilleure progression

#### Store `tasks` (`stores/tasks.js`)

**State :**

- `tasks` : tableau des tâches
- `loading` : état de chargement
- `sidebarOpen` : toggle UI (persisté localStorage)

**Getters :**

- `charactersWithTasks` : paires realm_slug|character_name uniques
- `totalPendingCount` : nombre de tâches incomplètes
- `characterTasks(realmSlug, characterName)` : tâches d'un personnage (triées daily first)
- `pendingCount(realmSlug, characterName)` : tâches incomplètes d'un personnage

**Actions :**

- `fetchTasks()` : GET `/api/character-tasks`, applique les resets
- `createTask(realmSlug, characterName, taskName, resetType)` : POST `/api/character-tasks`
- `toggleTask(taskId)` : PUT `/api/character-tasks/{taskId}`
- `deleteTask(taskId)` : DELETE `/api/character-tasks/{taskId}`
- `applyResets()` : remet à zéro les tâches complétées si le seuil daily (5h) ou weekly (mercredi 5h) est dépassé
- `toggleSidebar()` : toggle + persist localStorage

### 13.6 Utilitaires JavaScript

#### classColors.js (`utils/classColors.js`)

Objet `classColors` : classId (1-13) -> code couleur hex.
Warrior #C79C6E, Paladin #F58CBA, Hunter #ABD473, Rogue #FFF569, Priest #FFFFFF, Death Knight #C41E3A, Shaman #0070DD, Mage #3FC7EB, Warlock #8788EE, Monk #00FF98, Druid #FF7C0A, Demon Hunter #A330C9, Evoker #33937F.

#### scoreCalculator.js (`utils/scoreCalculator.js`)

- `WEIGHTS` : quêtes 15%, hauts-faits 25%, réputations 15%, montures 15%, mascottes 10%, décorations 10%, professions 10%
- `DIMENSION_LABELS` : 7 noms de dimensions en français
- `DIMENSION_COLORS` : couleur hex par dimension
- `computeScore(character)` -> `{global: 0-100, dimensions: {quests, achievements, ...}}`
- `getScoreColor(score)` -> vert (>=75), jaune (>=50), orange (>=25), rouge (<25)
- `getRankColorHex(score)` -> orange Légendaire (>=90), violet Épique (>=75), bleu Rare (>=50), vert Commun (>=25), gris Débutant (<25)

#### accountScoreAggregator.js (`utils/accountScoreAggregator.js`)

Agrégateur côté client pour le score du compte.

#### scoreCardRenderer.js (`utils/scoreCardRenderer.js`)

Rendu de la carte de score pour le partage.

---

## 14. Flux d'authentification Blizzard OAuth 2.0

```
1. Utilisateur clique "Se connecter"
   |
2. GET /auth/blizzard/redirect
   |  -> Génère un state aléatoire (40 chars)
   |  -> Stocke en session: blizzard_oauth_state
   |  -> Redirige vers https://eu.battle.net/oauth/authorize
   |    ?client_id=...&redirect_uri=...&response_type=code&scope=wow.profile&state=...
   |
3. Utilisateur s'authentifie sur Battle.net
   |
4. GET /auth/blizzard/callback?code=...&state=...
   |  -> Vérifie state == session(blizzard_oauth_state)
   |  -> POST https://eu.battle.net/oauth/token (échange code -> access_token)
   |  -> Stocke en session: blizzard_user_token
   |
5. fetchAndStoreUserInfo(accessToken)
   |  -> GET https://eu.battle.net/oauth/userinfo
   |  -> Stocke en session: bnet_user_id, bnet_battletag
   |  -> Si bnet_user_id == config(services.blizzard.admin_bnet_id) -> session: is_admin = true
   |
6. Redirige vers /
   |
7. Frontend: GET /api/auth/status -> {authenticated: true, isAdmin: bool}
```

---

## 15. Pipeline d'import de données

```
1. TÉLÉCHARGEMENT (app:download-db2)
   |
   |-- SimpleArmory JSONs -> storage/app/blizzard/
   |   |-- achievements.json
   |   |-- mounts.json
   |   |-- pets.json
   |   +-- decors.json
   |
   +-- DB2 CSVs (wago.tools) -> storage/app/blizzard/
       |-- area_table.csv, map.csv, content_tuning.csv
       |-- quest_v2_cli_task.csv, quest_poi_blob.csv, ui_map.csv
       |-- achievement.csv (frFR)
       |-- mount.csv (frFR), battle_pet_species.csv (frFR)
       |-- spell_name.csv (frFR) (~400k lignes)
       |-- skill_line.csv, skill_line_ability.csv, trade_skill_category.csv
       |-- housetdecor.csv (frFR)
       +-- faction.csv (frFR)

2. IMPORT (app:wow-data-import)
   |
   |-- Achievements: SA JSON -> catégories/extension/icônes -> achievement.csv (noms FR) -> WowAchievement
   |-- Mounts: SA JSON -> catégories/sources/icônes -> mount.csv (noms FR) -> WowMount
   |-- Pets: SA JSON -> catégories/sources -> spell_name.csv + battle_pet_species.csv (noms FR) -> WowPet
   |-- Decor: SA JSON -> catégories/sources -> housetdecor.csv (noms FR) -> API icônes -> WowDecor
   |-- Quests: quest_v2_cli_task.csv -> ContentTuning -> QuestPOIBlob+UiMap -> FiltRaces -> WowQuest
   +-- Professions: SkillLine + SkillLineAbility + TradeSkillCategory -> spell_name.csv -> WowProfession + WowRecipe

3. TAGGING FACTIONS (app:wow-quest-faction-tag)
   +-- API Blizzard -> tag les factions miroir des quêtes
```

---

## 16. Stratégie de cache

| Clé                                    | TTL     | Service                          | Description                                  |
|----------------------------------------|---------|----------------------------------|----------------------------------------------|
| `blizzard_access_token`                | 1h      | BlizzardApiClient                | Token client_credentials Blizzard            |
| `wow_class_icons`                      | 30 jours | UserCharacterService            | URLs des icônes de classe                    |
| `seo_character_{realm}_{name}`         | 1h      | CharacterSeoService              | Métadonnées SEO d'un personnage              |
| `sitemap_index_xml`                    | 1h      | CharacterSeoService              | Index du sitemap                             |
| `sitemap_pages_xml`                    | 24h     | CharacterSeoService              | Sitemap des pages statiques                  |
| `sitemap_characters_xml`               | 3h      | CharacterSeoService              | Sitemap des personnages récents              |
| `account_score:{sessionId}`            | 24h     | AccountScoreService              | Score du compte calculé                      |
| `account_score:{sessionId}:progress`   | 1h      | AccountScoreService              | Progression du calcul en cours               |
| `cross_character:{jobId}`              | 1h      | CrossCharacterService            | Statut du job cross-personnage               |
| `admin_import:{jobId}`                 | 1h      | AdminController / RunImportJob   | Statut et sortie d'un job d'import           |

**Cache côté client (localStorage) :**

- `theme` : 'dark'|'light'
- `sidebarOpen` : état de la barre de tâches
