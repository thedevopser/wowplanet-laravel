# Qualité et intégration continue

Ce que le pipeline vérifie, ce qui fait échouer une merge request, et ce que le hook local attrape avant elle.

Le pipeline vit dans `.github/workflows/ci.yml`. Il tourne sur chaque pull request vers `main` et sur chaque push sur `main` — et sur rien d'autre : une branche avec une PR ouverte tournerait deux fois par push s'il écoutait toutes les branches, et rien n'entre dans `main` sans passer par une PR de toute façon.

---

## Le principe

**Aucune étape ne modifie de fichier.** C'est la règle qui commande le choix de chaque outil. Un formateur qui corrige en CI donne un pipeline vert sur du code non conforme, et le problème réapparaît au commit suivant. Chaque outil est donc invoqué dans son mode vérification, et l'étape échoue au moindre écart.

Une étape finale le prouve plutôt que de le supposer : elle vérifie que l'arbre de travail est resté propre. Elle attrape le jour où quelqu'un remplace `pint --test` par `pint`, ou retire un `--dry-run`.

**Toutes les étapes sont bloquantes.** Il n'y a pas d'étape informative dont on regarde le résultat de loin. Si une vérification signale quelque chose, on corrige le code.

---

## Les étapes

| # | Étape | Échoue quand |
| --- | --- | --- |
| 1 | Build des assets (`npm run build`) | Vite ou le build SSR échoue. |
| 2 | Contrôle des bundles produits | `public/build/manifest.json` ou `bootstrap/ssr/ssr.js` manque. |
| 3 | Rector (`make refactor-check`) | Une refactorisation reste à appliquer. |
| 4 | Style (`make lint-check`) | Un fichier s'écarte du preset Pint. |
| 5 | Analyse statique (`make static`) | Larastan trouve une erreur au niveau maximum. |
| 6 | Couverture de documentation (`make docs-coverage`) | Le nombre de classes documentées nulle part dépasse le plafond déclaré. |
| 7 | Collation de la base de test | La base n'est pas en ICU sur `fr-FR`. |
| 8 | Migrations | Une migration échoue sur une base neuve. |
| 9 | Pest avec couverture (`make coverage-php-ci`) | Un test échoue, ou la couverture PHP passe sous 80 %. |
| 10 | Vitest avec couverture (`make coverage-js`) | Un test échoue, ou un seuil JS n'est pas tenu. |
| 11 | Arbre de travail inchangé | Une étape a réécrit un fichier suivi. |

Trois points méritent une explication.

**Le build vient en premier, et les bundles sont contrôlés séparément.** Les tests fonctionnels Inertia rendent `app.blade.php`, qui lit le manifeste : sans build préalable, ils échouent pour une mauvaise raison. Et `npm run build` enchaîne le build client et le build SSR — le sidecar SSR tournant en production, un bundle qui ne casse que de ce côté ne doit pas passer, d'où la vérification explicite des deux fichiers.

**La couverture de documentation plafonne un compte, pas un ratio.** L'étape échoue dès qu'une classe de plus qu'hier n'est décrite nulle part. Un pourcentage aurait aussi bougé en supprimant une classe documentée, donc échoué sans faute. Le détail de la commande est dans [Commandes Artisan](09-commands.md).

**La collation est vérifiée à chaque exécution.** L'image PostgreSQL est sur Alpine, qui n'embarque aucune locale système : la collation `fr-FR` vient du fournisseur ICU, réglé par `POSTGRES_INITDB_ARGS`. C'est le tri des noms accentués qui en dépend, et une base créée sans ce réglage passerait inaperçue jusqu'à ce qu'un test d'ordre échoue de façon incompréhensible. L'étape interroge `pg_database` par de simples `SELECT`, parce que le `psql` du runner est plus ancien que le serveur et que `\l` y lit une colonne renommée en PostgreSQL 17.

---

## L'ordre Rector → Pint → Larastan

Il est fixe, et il n'est pas arbitraire.

Rector transforme la sémantique, Pint met en forme le résultat, Larastan analyse ce qu'il reste. Formater avant de transformer laisse passer du code non formaté, puisque Rector réécrira ensuite. Analyser avant de transformer revient à analyser du code qui n'existe plus.

Faire cohabiter un refactoriseur et un formateur ne pose pas de problème une fois l'ordre fixé : sur un fichier volontairement non conforme aux deux, Rector et Pint convergent en un aller-retour puis ne bougent plus. Ils ne se défont pas, ils se complètent — l'un travaille la sémantique, l'autre la mise en forme.

Le hook pre-commit applique exactement le même ordre, à une différence près : en local les outils écrivent, en CI ils se contentent de constater.

---

## Les seuils de couverture

| Mesure | Seuil |
| --- | --- |
| Classes documentées nulle part | 46 au plus, et ce plafond ne remonte jamais |
| PHP, lignes | 80 % |
| JS, lignes | 80 % |
| JS, instructions | 80 % |
| JS, fonctions | 75 % |
| JS, branches | 60 % |

Le seuil PHP est global. Côté JS, seuls `inertia.js`, `ssr.js`, `app.js` et `bootstrap.js` sont exclus de la mesure : les layouts et `components/inertia/` sont mesurés et testés, et ne doivent pas être remis dans les exclusions pour faire remonter un chiffre.

La couverture PHP a besoin de **pcov**, présent dans le stage `dev` de l'image et absent du PHP de la machine. D'où deux cibles distinctes : `make coverage-php` passe par le conteneur et exige donc la stack, `make coverage-php-ci` s'exécute sur le PHP appelant, le runner de CI portant déjà pcov.

---

## Le hook pre-commit

Installé par `make install-hooks`, il rejoue le pipeline en local, avec les outils en mode écriture.

```
[1/5] Rector      → puis re-stage
[2/5] Pint        → puis re-stage
[3/5] Larastan
[4/5] Pest
[5/5] Vitest
```

Trois comportements à connaître.

**Il ne déclenche que ce qui est concerné.** Les quatre étapes PHP ne partent que si un fichier PHP est indexé — ou `composer.json`, `phpstan.neon`, `pint.json`, `rector.php`. L'étape front ne part que si un fichier `.js`, `.ts`, `.vue`, `.css` ou un `package.json` l'est. Rien d'indexé de pertinent, rien à faire.

**Les étapes PHP exigent la stack.** La suite Pest tourne sur PostgreSQL : le hook vérifie que les conteneurs `app` et `postgres` répondent, et refuse le commit en le disant plutôt que de laisser dérouler une pile d'exceptions.

**Il re-stage ce que Rector et Pint ont corrigé, et seulement dans le périmètre du commit.** Un fichier corrigé hors du commit reste dans l'arbre de travail, et le hook le liste en sortie. Il avertit aussi en amont quand un fichier n'est indexé que partiellement : si un outil le réécrit, tout son contenu entre dans le commit.

---

## Correspondance avec les cibles `make`

Le pipeline n'invoque presque jamais un binaire directement, il passe par le `Makefile`. Une cible et sa variante de vérification ne diffèrent que par le mode.

| Cible locale | Écrit | Variante de vérification | Écrit |
| --- | --- | --- | --- |
| `make refactor` | oui | `make refactor-check` | non, sort en 2 s'il reste du travail |
| `make lint` | oui | `make lint-check` | non, sort en 1 au moindre écart |
| `make static` | non | — | — |

`phpcbf` n'a pas de mode à blanc : il réécrit systématiquement et sort en code 0. Il n'a donc aucune place dans un pipeline, et le projet n'en utilise pas.
