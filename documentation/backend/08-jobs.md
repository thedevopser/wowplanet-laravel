# Jobs de queue

Les jobs s'exécutent sur la queue `imports` via le worker dédié (`php artisan queue:work --queue=imports`). Ils communiquent leur état via le cache Laravel (`Cache::put`).

---

## `RunImportJob`

Exécute une commande Artisan de façon asynchrone et stocke la sortie dans le cache.

**Propriétés**

| Propriété | Type | Description |
|---|---|---|
| `$jobId` | `readonly string` | UUID identifiant ce job |
| `$command` | `readonly string` | Nom de la commande Artisan (ex. : `app:wow-data-import`) |
| `$parameters` | `readonly array<string, mixed>` | Paramètres passés à la commande |
| `$timeout` | `int` | `1200` secondes (20 min) |

**Cycle de vie**

1. À la création : clé `admin_import:{jobId}` → `{status: 'running'}`
2. En cas de succès : clé → `{status: 'completed', output: string}`
3. En cas d'erreur : clé → `{status: 'failed', output: message}`

Toutes les entrées de cache expirent après **3600 s** (1 h).

**Consulter l'état** : `AdminService::getImportJobStatus(string $jobId)`

---

## `ComputeCrossCharacterJob`

Récupère les données de tous les personnages d'un compte et calcule la progression agrégée.

**Propriétés**

| Propriété | Type | Description |
|---|---|---|
| `$jobId` | `readonly string` | UUID identifiant ce job |
| `$bnetUserId` | `readonly string` | Identifiant Battle.net de l'utilisateur |
| `$characters` | `readonly list<array<string, mixed>>` | Liste des personnages à traiter |
| `$accessToken` | `readonly string` | Token OAuth2 utilisateur pour l'API Blizzard |
| `$timeout` | `int` | `600` secondes (10 min) |

**Cycle de vie**

1. À la création : clé `cross_character:{jobId}` → `{status: 'running'}`
2. Appelle `CrossCharacterService::fetchAndMergeCharacters()`
3. Persiste le résultat dans `CrossCharacterData` (upsert sur `bnet_user_id`)
4. En cas de succès : clé → `{status: 'completed'}`
5. En cas d'erreur : clé → `{status: 'failed'}`

> La limite mémoire est portée à 256 Mo via `ini_set('memory_limit', '256M')` car le calcul cross-personnage peut traiter des dizaines de personnages en parallèle.

**Consulter l'état** : `CrossCharacterService::getJobStatus(string $jobId)`
