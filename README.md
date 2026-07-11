# WowPlanet

Application web de suivi de progression **World of Warcraft** : profils de personnages,
collections (montures, mascottes, transmog…), Mythic+, raids, score de compte.

**Stack** : Laravel 12 + Vue 3 (SPA) · FrankenPHP · SQLite · données via l'**API Blizzard**.

> Tout tourne dans Docker. Aucun runtime local (PHP, Node, Composer) n'est requis :
> toutes les commandes passent par `docker compose` ou `make`.

---

## 1. Prérequis machine

Pour lancer le projet en dev, il te faut sur ton PC :

| Outil | Rôle |
| --- | --- |
| **Docker** + **Docker Compose** | Exécute app, worker et Vite. Seul runtime nécessaire. |
| **Traefik** (reverse-proxy local) | Sert le projet en HTTPS sur `wowplanet.dev.local`. Proxy partagé, **non inclus dans ce repo** — il doit déjà tourner sur le réseau Docker `dev-network`. |

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

# 3. Installer les dépendances PHP + JS (via Docker, aucun outil local requis)
make install

# 4. Générer la clé applicative
docker compose exec app php artisan key:generate

# 5. Créer la base SQLite puis migrer
touch database/database.sqlite
docker compose exec app php artisan migrate

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

| Commande | Description |
| --- | --- |
| `make test` | Tests PHP (Pest). |
| `make test-js` | Tests JS (Vitest). |
| `make lint` | Corrige le style (Laravel Pint). |
| `make static` | Analyse statique (Larastan). |
| `make quality` | lint + static + refactor + tests. |
| `make coverage` | Couverture PHP + JS (min 80 %). |
| `make clean` | Vide les caches Laravel. |

`make help` liste toutes les cibles disponibles.

---

## 6. Architecture (résumé)

- **Backend** — Clean Architecture (`Domain` → `Application` → `Infrastructure` / `Http`),
  Laravel 12, servi par FrankenPHP.
- **Frontend** — SPA Vue 3 (Vue Router + Pinia). Le backend sert le template Blade, les données
  transitent par les routes `api.php`.

Détails d'architecture et conventions : voir [`CLAUDE.md`](CLAUDE.md) et le dossier
[`documentation/`](documentation/).
