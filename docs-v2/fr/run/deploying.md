# Déployer

À la fin de cette page, chaque module s’exécute dans le processus que vous avez choisi, à partir d’une
image qui ne contient que son code.

Le même code s’exécute dans chaque processus : les modules qu’un processus exécute sont un réglage.
Cette page traite du placement des modules dans des processus et des conteneurs. Le paquet ne livre
aucun Dockerfile ; le [starter](https://github.com/mk-josias/laravel-distributable-starter) livre ceux
utilisés ici.

## Placer un module

Pour déplacer `iam` dans son propre processus, lancez deux copies du même code avec des réglages
différents :

```dotenv
# le processus iam
RUN_MODULES=iam

# le processus analytics
RUN_MODULES=analytics
IAM_HOST=http://iam.internal:8000
```

`config/distributable.php` déclare chaque module dans chaque processus, donc chaque processus sait
encore que les autres existent. Un processus qui exécute un module a besoin de :

- la connexion de chaque module qu’il exécute. `migrate --module=*` fait une passe par module qu’il
  exécute, donc deux processus ne migrent jamais la même ;
- le flux d’événements, partagé par tous les processus (Redis dans le starter) ;
- `declared.{module}.host` pour chaque module qu’il appelle sans l’exécuter. `modules:doctor` signale un
  module qui sert des messages sans lui ;
- le même `APP_KEY`, ou le même `DISTRIBUTABLE_RPC_SECRET`, que les autres processus : il signe les
  appels, et le processus appelé rejette tout appel qu’il ne peut pas vérifier.

## Une image par module

Une image pour un module ne contient que son code. À la construction, dans cet ordre (les commandes
sont décrites dans [Les bibliothèques d’un module](#les-bibliotheques-d-un-module)) :

```bash
UNUSED="$(RUN_MODULES=analytics php artisan modules:unused-packages)"
RUN_MODULES=analytics php artisan modules:purge --force
[ -z "$UNUSED" ] || composer update $UNUSED --no-dev --no-scripts
composer dump-autoload --optimize --no-dev
```

Au démarrage, `php artisan optimize` lance `modules:cache`, et une image démarrée avec un module purgé
dans `RUN_MODULES` échoue au boot en nommant ce module.

## Ce que fait un conteneur

L’image du starter exécute un ou plusieurs rôles, chacun activé par une variable. Elle ne migre que
lorsque le rôle `http` est actif.

- **http** (Octane), `WITH_HTTP`, actif par défaut : autant que vous en avez besoin.
- **rpc** (`rpc:serve`, sur `DISTRIBUTABLE_RPC_PORT`), `WITH_RPC`, inactif par défaut : autant que vous
  en avez besoin. Il répond aux appels entre modules, à l’écart des workers de l’API.
- **worker** (`queue:work`), `WITH_WORKER`, inactif par défaut : autant que vous en avez besoin.
- **scheduler** (`schedule:run` chaque minute), `WITH_SCHEDULER`, inactif par défaut : un par ensemble
  de modules, parce que deux exécuteraient chaque tâche deux fois.
- **consumers** (`stream:consume`), `WITH_CONSUMERS`, inactif par défaut : un par ensemble de modules.
  Il sert chaque module de `RUN_MODULES`, chacun avec sa propre position, et deux casseraient l’ordre
  dans lequel un module lit.

Une tâche planifiée s’exécute dans le module qui l’a déclarée, donc le conteneur qui porte le
scheduler est celui qui exécute ce module.

## Déployer un module seul

Chaque fichier lance une image construite pour ce module seulement : son code et la fondation, rien
des autres modules (voir [Une image par module](#une-image-par-module)).

Rien ne lie les modules à un seul fichier compose. Le service de chaque module porte sa propre image,
son `RUN_MODULES`, ses rôles et son port ; ce qu’il partage avec les autres n’est que de
l’infrastructure joignable : le serveur de base de données, le flux, le secret, et l’adresse des
modules qu’il appelle.

- **un réseau Docker :** déclaré `external: true`, avec le même `name` dans chaque fichier
- **le serveur de base de données et Redis :** leurs noms de service (`postgres`, `redis`) se résolvent
  sur ce réseau
- **l’adresse d’un module qu’un autre appelle :** un alias réseau sur le module (`iam.svc`), écrit dans
  l’`IAM_HOST` de l’appelant
- **le secret de signature :** le même `APP_KEY` dans chaque fichier

Trois fichiers, démarrés dans cet ordre, exécutent `iam` et `analytics` séparément :

```yaml
# infra.yml
name: dm-infra
services:
  postgres:
    image: distributable-postgres:local
    environment:
      POSTGRES_DB: distributable
      POSTGRES_USER: distributable
      POSTGRES_PASSWORD: distributable
      DB_APP_PASSWORD: distributable_app
      MODULE_DATABASES: "iam analytics notifications"
    volumes:
      - pgdata:/var/lib/postgresql/data
    healthcheck:
      test: ["CMD", "pg_isready", "-U", "distributable"]
      interval: 5s
      timeout: 5s
      retries: 10
    networks: [dm]

  redis:
    image: valkey/valkey:8-alpine
    healthcheck:
      test: ["CMD", "redis-cli", "ping"]
      interval: 5s
      timeout: 5s
      retries: 10
    networks: [dm]

networks:
  dm:
    name: dm

volumes:
  pgdata:
```

`postgres` est l’image `docker/postgres` du starter : elle crée les bases listées dans
`MODULE_DATABASES`.

```yaml
# analytics.yml
name: dm-analytics
x-env: &env
  APP_ENV: production
  APP_DEBUG: "false"
  APP_KEY: "base64:QUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUE="
  LOG_CHANNEL: stderr
  DB_CONNECTION: pgsql
  DB_HOST: postgres
  DB_USERNAME: distributable
  DB_PASSWORD: distributable
  DB_APP_USERNAME: distributable_app
  DB_APP_PASSWORD: distributable_app
  REDIS_HOST: redis
  CACHE_STORE: redis
  SESSION_DRIVER: array
  QUEUE_CONNECTION: redis
  DISTRIBUTABLE_STREAM_DRIVER: redis
  AUTH_GATEWAY_SECRET: gateway-secret
  IAM_HOST: "http://iam.svc:8000"
  OCTANE_WORKERS: "2"
  BROADCAST_CONNECTION: reverb
  REVERB_APP_ID: distributable
  REVERB_APP_KEY: distributable-key
  REVERB_APP_SECRET: distributable-secret
  REVERB_HOST: localhost
  REVERB_PORT: "8080"
  REVERB_SCHEME: http

services:
  analytics:
    image: distributable-skeleton:analytics
    environment:
      <<: *env
      RUN_MODULES: analytics
      WITH_CONSUMERS: "true"
      WITH_SCHEDULER: "true"
    ports:
      - "8002:8000"
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost:8000/up"]
      interval: 10s
      timeout: 5s
      retries: 10
      start_period: 40s
    networks: [dm]

networks:
  dm:
    external: true
    name: dm
```

`iam.yml` est le même fichier avec `iam` pour le nom, l’image, `RUN_MODULES` et le port, sans
`WITH_SCHEDULER`, avec `SYNC_PERMISSIONS: "true"`, et l’alias `iam.svc` sur son entrée de réseau :

```yaml
    networks:
      dm:
        aliases: [iam.svc]
```

Les images sont celles que construit le `docker-compose.mesh.yml` du starter, une par module, avec
`--build-arg RUN_MODULES=<module>`. Les valeurs ci-dessus sont pour le développement : en production,
mettez de vrais secrets.

## Vérifier un déploiement

- **chaque module, où il s’exécute, sa connexion par défaut et son hôte distant :**
  `php artisan modules:list`
- **quels modules un processus en cours sert :** `MODULES_STATUS_ROUTE=/` : la route répond avec
  `"modules": ["analytics"]` et `"status": "ok"`
- **que chaque module peut s’exécuter avec cette configuration :** `php artisan modules:doctor`, code
  de sortie non nul en cas de problème

Gardez le chemin RPC hors du point d’entrée public : une passerelle transmet les requêtes des clients
aux routes d’API d’un module, jamais à ses routes RPC (voir [Appels](/fr/talk/calls)).

## Les bibliothèques d’un module

Le paquet ne lit aucun `composer.json` de lui-même : par défaut, toute dépendance est dans celui de la
racine. Un module peut déclarer les bibliothèques que lui seul utilise dans `{module}/composer.json`,
fusionné dans le fichier racine par
[wikimedia/composer-merge-plugin](https://github.com/wikimedia/composer-merge-plugin). Il reste un seul
`composer.lock` et un seul `vendor/`.

```json
// apps/Iam/composer.json
{ "require": { "spatie/laravel-permission": "^8.3" } }

// composer.json
"extra": { "merge-plugin": { "include": ["apps/*/composer.json"] } }
```

Une image construite pour certains modules peut alors laisser de côté ce que seuls les autres
requièrent. `modules:unused-packages` affiche ces paquets. Lancez-le avant `modules:purge`, qui
supprime les fichiers qu’il lit, et nommez les paquets à Composer pour qu’il les retire sans changer
aucune autre version :

```bash
# spatie/laravel-permission
UNUSED="$(RUN_MODULES=analytics php artisan modules:unused-packages)"
RUN_MODULES=analytics php artisan modules:purge --force
[ -z "$UNUSED" ] || {
  composer update $UNUSED --no-dev --no-scripts
  rm -f bootstrap/cache/packages.php bootstrap/cache/services.php
}
composer dump-autoload --optimize --no-dev
```

Un paquet que le `composer.json` racine ou un module que ce processus exécute requiert aussi n’est
jamais listé. Démarrer une image avec, dans `RUN_MODULES`, un module dont le dossier a été purgé
échoue au boot, avec le nom du module.

## Ensuite

[Exploiter le flux d’événements](/fr/run/streams).
