# Orchestration de l'import

Un import complet enchaîne huit étapes dans un ordre explicite, publie en permanence où il en est, ce qu'il consomme et pourquoi il attend, et reprend là où il s'est arrêté si le worker redémarre.

Deux façons de le lancer, un seul déroulé : la commande `app:wow-data-import` et le job `RunImportJob` appellent le même pipeline. Seule la façon d'attendre diffère — la commande dort, le job relâche le worker et se re-dispatch.

---

## La chaîne

### `ImportStage`

Les étapes, dans leur ordre d'exécution.

| Étape | Valeur | Dépend de | Tables comptées |
|---|---|---|---|
| Socle de référence | `reference` | — | — |
| Hauts faits | `achievements` | — | `wow_achievements` |
| Quêtes | `quests` | socle | `wow_quests` |
| Métiers | `professions` | socle | `wow_professions`, `wow_recipes` |
| Montures | `mounts` | socle | `wow_mounts` |
| Mascottes | `pets` | — | `wow_pets` |
| Décorations | `decor` | — | `wow_decors` |
| Garde-robe | `appearances` | — | `wow_appearances` |

Le socle de référence ouvre la chaîne : les quêtes, les métiers et les montures tirent de `wow_ref_*` ce que l'API n'expose pas, et un import parti d'une base vide sans lui sortirait faux sans rien signaler. Ce n'est pas une entité de catalogue, d'où l'absence de table comptée : `wow_ref_*` est chargée par `COPY` dans des tables sans horodatages.

L'ordre est vérifié contre les dépendances déclarées plutôt que simplement écrit — une dépendance ajoutée à contresens de la chaîne fait tomber le test.

`ImportStage::requested($type)` traduit l'option `--type` : `all` rend la chaîne entière, un nom d'étape rend cette étape seule. Une étape nommée seule ne tire pas le socle derrière elle, pour qu'un réimport ciblé reste ce que l'exploitant a demandé.

### `ImportPipeline`

- `begin(string $jobId, array $stages, bool $force)` — ouvre le suivi, une étape par entité demandée, et marque ignorées celles que `ImportBuildGate` tient déjà pour ce build.
- `advance(ImportRun $run, bool $full, ?int $limit)` — exécute **une passe** de la première étape non aboutie, retient l'étape au build si elle a abouti, puis republie l'import.
- `isDone(ImportRun $run)` — plus aucune étape à faire.

Une passe et une seule par appel : c'est ce qui permet au job de rendre la main entre deux étapes sans que le déroulé diffère de celui de la commande.

### `ImportStageRunner` et `ImportStageResult`

Exécute une passe d'une étape et rend ce qu'elle a fait. **Une étape qui échoue est rapportée, jamais propagée** : l'échec d'une entité n'emporte pas les suivantes.

`ImportStageResult` porte l'étape mise à jour et l'attente sur laquelle elle bute. L'attente est rendue à l'appelant plutôt que subie sur place, pour qu'un job puisse relâcher le worker là où une commande préfère dormir.

Seule la garde-robe rend la main avant d'avoir fini : son offset désigne la fenêtre où reprendre, et la passe suivante repart de là.

---

## L'état publié

### `ImportRun`

L'état complet d'un import, immuable et sérialisable de bout en bout : ce que le suivi publie, ce que l'endpoint de progression rend, et ce que le rapport final archive.

| Membre | Rôle |
|---|---|
| `status()` | État global, agrégé des étapes |
| `currentStage()` | Étape en cours |
| `fraction()` | Part faite, chaque étape pesant autant que les autres |
| `elapsedSeconds()`, `etaSeconds()` | Temps écoulé, temps restant estimé |
| `summary()` | Rapport lisible, affiché par le panneau et archivable |

**Un échec d'étape ne clôt pas l'import** : il n'est visible qu'une fois toutes les étapes terminées, faute de quoi le front cesserait de suivre un import qui tourne.

`etaSeconds()` extrapole des durées réellement mesurées — moyenne des étapes faites, multipliée par le nombre d'étapes restantes — et rend `null` tant que rien n'est fait, plutôt qu'un chiffre inventé. L'estimation se rafraîchit à l'étape et non à la seconde : sur une chaîne aux étapes très inégales, extrapoler à la seconde enfle tant qu'une étape longue n'aboutit pas, puis tombe à zéro alors qu'il reste tout à faire.

### `ImportStep` et `ImportStepStatus`

Ce qu'une étape a fait : son état, ses lignes, ses appels API, son temps, et son avancement quand elle se compte en fenêtres. Chaque passe rend une nouvelle étape qui cumule la précédente.

`ImportStepStatus` vaut `pending`, `running`, `completed`, `failed` ou `skipped`. **`skipped` est terminal sans être un échec** : c'est la porte de build qui a constaté qu'il n'y avait rien à refaire.

### `RowTally` et `RowTallyCounter`

Lignes créées, mises à jour et supprimées par une étape, **mesurées sur la base plutôt que rapportées par les importers**.

Les sept importers écrivent par `upsert()` Eloquent, qui entretient `created_at` et `updated_at`, et sautent les lignes inchangées avant d'écrire : une ligne touchée est donc une ligne réellement modifiée. Le décompte se déduit de quatre nombres — lignes touchées, lignes nées, cardinalité avant, cardinalité après — et `RowTally::fromCounts()` est une fonction pure testée comme telle. Un état impossible, plus de lignes créées que touchées, lève plutôt que de rendre un chiffre faux.

Aucune étape n'écrit dans la table d'une autre : deux étapes qui se suivent dans la même seconde ne se disputent jamais une ligne, malgré des horodatages `timestamp(0)`.

### `ImportProgressStore`

Publie et relit l'avancement sous `admin_import:{jobId}`. La valeur stockée garde `status` et `output` — ce que le panneau d'administration lit déjà — et range l'état structuré à côté : le front continue de fonctionner sans une ligne de changement.

`payload()` sert `GET /api/admin/import/{jobId}` : étape en cours, avancement, budget horaire consommé, temps écoulé, temps restant estimé, attente en cours et détail par étape. Un job suivi par le chemin des commandes simples est rendu tel qu'il l'a toujours été.

**Ce magasin n'est pas l'autorité de reprise.** Il vit dans le cache, que le bouton « Vider les caches » efface : la reprise s'appuie sur la charge du job en file et sur `ImportBuildGate`, toutes deux durables.

---

## Les attentes

### `ImportWait`, `ImportWaitReason`, `ImportWaitReporter`

Une attente silencieuse est indistinguable d'un blocage. Trois raisons couvrent tout ce qui met un import en pause :

| Raison | Origine |
|---|---|
| `hourly_budget` | Plafond horaire réservé aux imports atteint |
| `rate_limit_backoff` | Recul après un 429 |
| `batch` | Lot de requêtes en vol |

`ImportWaitReporter` est le service partagé — unique exemplaire tenu par le conteneur — par lequel le client API fait remonter les deux dernières. Le pipeline lui désigne le job suivi le temps d'une passe ; hors import suivi, appel en ligne de commande ou trafic du site, il ne suit rien et ne publie rien.

Le point d'instrumentation est `ImportsFromBlizzardApi::fetchBatchAsync()`, que traversent tous les balayages : un lot en vol y publie sa taille, un recul son délai. Sept importers n'ont ainsi rien à connaître du suivi.

---

## Les appels consommés

Les appels d'une étape sont la différence du total monotone de `HourlyBudgetGuard` entre son début et sa fin. Ce total est incrémenté dans le même aller-retour Redis que le compteur par minute, au seul point de comptage de tout appel Blizzard — `RateLimitingMiddleware`. Pas de second mécanisme, et un chiffre exact là où la différence de `usedInWindow()` aurait été fausse dès qu'une minute sort de la fenêtre glissante pendant l'étape.

---

## Reprise et redémarrage

Chaque passe rendue est un job de file neuf : un worker arrêté entre deux passes reprend immédiatement, et les étapes déjà abouties sont ignorées par la porte de build.

Un worker tué **pendant** une passe est un autre cas : le job reste réservé, et la file ne le rend qu'au bout de `retry_after` (2 000 s). L'import n'est pas perdu — il repart à l'étape qui n'a pas abouti — mais il reste figé jusque-là. C'est pourquoi le conteneur du worker se voit accorder un délai d'arrêt large : `queue:work` termine sa passe sur SIGTERM, encore faut-il lui en laisser le temps.

`retry_after` doit rester supérieur au `timeout` du job, sans quoi une passe encore en vol serait rejouée en parallèle — du trafic Blizzard en double sur un quota. L'invariant a son test dans `tests/Feature/Config/QueueConfigTest.php`.
