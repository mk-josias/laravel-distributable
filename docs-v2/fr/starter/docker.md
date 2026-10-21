# Docker

À la fin de cette page, le starter s’exécute dans un seul conteneur ou dans un conteneur par module.

Le starter livre les deux montages, sur Postgres (une connexion propre à chaque module) et Redis (le
flux d’événements), servis par Octane avec Swoole :

```bash
# tous les modules dans un conteneur
docker compose -f docker-compose.mono.yml up -d
# un conteneur par module
docker compose -f docker-compose.mesh.yml up -d
```

Dans les deux, Traefik est le seul chemin vers l’API, sur `:8000` (`GATEWAY_PORT`) ; les ports des modules
restent privés, sauf le 8080 de Reverb (la poussée en direct). Postgres et le Redis des modules sont sur
un réseau que Traefik ne rejoint pas ; il lit les utilisateurs dans `gateway-cache`, un Redis à lui.
Envoyez le jeton qu’iam a émis : Traefik le transforme en l’`X-Identity` que lisent les modules. Le
conteneur qui exécute iam écrit sa paire de clés JWT dans le volume `jwt-keys` à son premier démarrage
(`WITH_JWT_KEYS`), et Traefik y lit la clé publique.

Dans le second montage, chaque image est construite avec `--build-arg RUN_MODULES=<module>`, donc elle ne
contient que son module : le Dockerfile lance `modules:purge` avant `composer dump-autoload`. Un
conteneur ne migre que la connexion du module qu’il exécute, donc deux conteneurs ne migrent jamais la
même.

- **`docker/Dockerfile` :** installe les dépendances, purge les modules que l’image n’exécute pas et les
  paquets que seuls eux requéraient, puis construit l’environnement PHP et Swoole.
- **`docker/entrypoint.sh` :** écrit les clés JWT d’iam (`WITH_JWT_KEYS`), lance `optimize`, migre (avec
  le rôle `http` seulement), puis démarre supervisord.
- **`gateway/` :** Traefik devant les modules, et son plugin d’authentification : voir
  [`gateway/README.md`](https://github.com/mk-josias/laravel-distributable-starter/blob/main/gateway/README.md).
- **`docker/supervisord.conf` :** les rôles qu’un conteneur peut prendre, chacun activé par une variable.
- **`docker/postgres/` :** une image Postgres qui crée les bases de `MODULE_DATABASES`, possédées par
  `distributable`, écrites par `distributable_app`.

- **`http` (Octane)**
  - Variable : `WITH_HTTP`
  - Par défaut : `true`
  - À lancer au plus : autant que vous en avez besoin
- **`rpc` (`rpc:serve`, port `DISTRIBUTABLE_RPC_PORT`)**
  - Variable : `WITH_RPC`
  - Par défaut : `false`
  - À lancer au plus : autant que vous en avez besoin : les appels entre modules, tenus à l’écart des
    workers de l’API
- **`worker` (`queue:work`)**
  - Variable : `WITH_WORKER`
  - Par défaut : `false`
  - À lancer au plus : autant que vous en avez besoin
- **`reverb` (WebSocket, port 8080)**
  - Variable : `WITH_REVERB`
  - Par défaut : `false`
  - À lancer au plus : un par conteneur qui pousse : il pousse vers le sien, sur localhost
- **`scheduler` (supercronic)**
  - Variable : `WITH_SCHEDULER`
  - Par défaut : `false`
  - À lancer au plus : un par ensemble de modules : deux exécuteraient chaque tâche deux fois
- **`consumers` (`stream:consume`)**
  - Variable : `WITH_CONSUMERS`
  - Par défaut : `false`
  - À lancer au plus : un par ensemble de modules : il sert chaque module de `RUN_MODULES`, chacun avec
    son propre curseur, et deux casseraient l’ordre dans lequel un module lit

Les valeurs des fichiers compose sont pour le développement. En production, mettez de vrais secrets.
