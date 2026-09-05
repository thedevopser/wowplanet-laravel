# WowPlanet

Application web de suivi de progression **World of Warcraft** : profils de personnages,
collections (montures, mascottes, transmog…), Mythic+, raids, score de compte.

**Stack** : Laravel 12 + Vue 3 via Inertia · FrankenPHP · SQLite · données via l'**API Blizzard**.

> L'application tourne dans Docker, l'outillage tourne en local : tests, lint, analyse
> statique et build d'assets s'exécutent avec le PHP et le Node de la machine.

---

## 1. Prérequis machine

Pour lancer le projet en dev, il te faut sur ton PC :

| Outil | Rôle |
| --- | --- |
| **Docker** + **Docker Compose** | Exécute app, worker et Vite. |
| **PHP 8.4** + **Composer** | Outillage PHP : Pest, Pint, Larastan, Rector, Artisan. |
| **Node 26** + **npm** | Outillage JS : Vitest, build des assets. |
| **Traefik** (reverse-proxy local) | Sert le projet en HTTPS sur `wowplanet.dev.local`. Proxy partagé, **non inclus dans ce repo** — il doit déjà tourner sur le réseau Docker `dev-network`. |

> La couverture PHP est la seule commande d'outillage qui reste dans le conteneur : elle a
> besoin de **pcov**, installé dans l'image de dev et absent du PHP de la machine.

Le `compose.yml` s'attache à un réseau Docker **externe** nommé `dev-network` et publie ses
routes via des labels Traefik. Deux prérequis en découlent :

```bash
# Créer le réseau partagé (une seule fois, s'il n'existe pas déjà)
docker network create dev-network

# Résoudre le domaine local → ajouter cette ligne à /etc/hosts
127.0.0.1  wowplanet.dev.local
```

> **Pas besoin de Redis ni de MySQL.** La base est un simple fichier **SQLite** et les drivers
> queue / cache / session sont en `database`. Les variables `REDIS_*` du `.env` sont présentes
> mais **inutilisées en dev** — tu peux les laisser telles quelles.

---

## 2. Installation (première fois, après clone)

```bash
# 1. Copier le fichier d'environnement
cp .env.example .env

# 2. Démarrer le conteneur applicatif (sur dev-network)
make up

# 3. Installer les dépendances PHP + JS (Composer et npm de la machine)
make install

# 4. Générer la clé applicative
php artisan key:generate

# 5. Créer la base SQLite puis migrer
touch database/database.sqlite
php artisan migrate

# 6. (optionnel) Installer le hook pre-commit qualité (lint + static + tests)
make install-hooks
```

---

## 3. Ce que tu dois renseigner dans `.env`

| Variable | Requis | Détail |
| --- | --- | --- |
| `APP_KEY` | oui | Généré automatiquement par `php artisan key:generate` (étape 4). |
| `BLIZZARD_CLIENT_ID` | oui | Client OAuth de l'[API Blizzard](https://develop.battle.net/access/clients). |
| `BLIZZARD_CLIENT_SECRET` | oui | Secret du client OAuth Blizzard. |
| `BLIZZARD_REGION` | oui | Région de l'API (`eu` par défaut). |
| `BLIZZARD_REDIRECT_URI` | oui | URL de callback OAuth. En dev : `https://wowplanet.dev.local/auth/blizzard/callback` (déjà dans `.env.example`). |
| `ADMIN_BNET_ID` | oui (pour l'admin) | Ton BattleTag/ID Bnet, requis pour accéder au panel d'administration. |
| `DISCORD_WEBHOOK_URL_CHANGELOG` | non | Webhook Discord pour le changelog. |
| `DISCORD_WEBHOOK_URL_DISCUSSION` | non | Webhook Discord pour les discussions. |

> Les credentials Blizzard s'obtiennent en créant un client sur le
> [Blizzard Developer Portal](https://develop.battle.net/). Ils sont nécessaires pour l'import
> des données et l'authentification Battle.net.

---

## 4. Lancer en mode dev

```bash
make dev       # Serveur Vite (HMR) via Traefik
make worker    # Worker de queue "imports" (imports Blizzard, calculs cross-character)
```

Accès : **https://wowplanet.dev.local**

Arrêt :

```bash
make dev-stop      # Stoppe Vite
make worker-stop   # Stoppe le worker
make down          # Stoppe l'application
```

> **Worker** : FrankenPHP garde le code en mémoire. Après une modification d'un job ou d'une
> commande Artisan, redémarre le worker pour prendre en compte les changements :
> `docker compose restart worker`.

---

## 5. Commandes utiles (dev quotidien)

| Commande | Description | Exécution |
| --- | --- | --- |
| `make test` | Tests PHP (Pest). | local |
| `make test-js` | Tests JS (Vitest). | local |
| `make lint` | Corrige le style (Laravel Pint). | local |
| `make static` | Analyse statique (Larastan). | local |
| `make quality` | lint + static + refactor + tests. | local |
| `make coverage` | Couverture PHP + JS (min 80 %). | PHP dans le conteneur, JS en local |
| `make clean` | Vide les caches Laravel. | conteneur |

Les cibles locales n'ont pas besoin que la stack soit démarrée. Seules `make coverage` et
`make clean` s'adressent au conteneur applicatif.

`make help` liste toutes les cibles disponibles.

---

## 6. Architecture (résumé)

- **Backend** — Clean Architecture (`Domain` → `Application` → `Infrastructure` / `Http`),
  Laravel 12, servi par FrankenPHP.
- **Frontend** — Vue 3 avec Inertia et Pinia, rendu serveur optionnel via le sidecar
  `inertia-ssr`. Les controllers rendent les pages de `resources/js/pages/`, et les routes
  `api.php` servent les appels XHR.

Détails d'architecture et conventions : voir le dossier [`documentation/`](documentation/),
également servi sur `/docs` en environnement local.
