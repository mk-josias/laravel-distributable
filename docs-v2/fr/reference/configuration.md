# Configuration

Chaque réglage de `config/distributable.php` : où il se règle, sa valeur par défaut, et ce qu’il fait.

## Où sont les réglages

Chaque réglage est dans `config/distributable.php`, que `php artisan vendor:publish
--tag=distributable-config` publie (`modules:install` aussi). Un réglage qui change selon
l’environnement lit une variable d’environnement, nommée dans sa section : réglez la variable dans
`.env`, ou la valeur dans le fichier. Un module peut ajouter ses propres flux dans son propre
`config/distributable.php`. Le transport HTTP a son propre fichier, voir
[RPC en HTTP](/fr/reference/rpc-http), et le flux Redis ses options dans
[Flux d’événements Redis](/fr/reference/stream-redis). L’endroit où vont les fichiers d’un module
est dans [Où vont les fichiers](/fr/build/layout).

## Modules

La valeur par défaut, puis la variable d’environnement s’il y en a une, puis ce que fait le
réglage :

- **`declared`** — Par défaut `[]`. Chaque module de l’application, par son nom (`apps/Iam` est
  `iam`). Ajoutez une entrée pour dire où un module répond quand il s’exécute dans un autre
  processus (`host`), pour garder son code ailleurs (`path` et `namespace`), ou pour changer le
  `prefix` de ses routes. Voir [Où vit le code d’un module](/fr/run/where#ou-vit-le-code-d-un-module).
- **`runs`** — Par défaut `*`. Variable d’environnement : `RUN_MODULES`. Les modules que ce
  processus démarre : `*` pour tous, ou une liste séparée par des virgules.
- **`modules.path`** — Par défaut `apps`. Le dossier des modules : `apps/Iam` pour `iam`.
- **`modules.namespace`** — Par défaut `Apps`. L’espace de noms racine de leurs classes :
  `Apps\Iam`.
- **`foundation.path`** — Par défaut `foundation`. Le dossier de ce que les modules partagent.
  Faites-le correspondre dans `composer.json` comme votre propre code ; `modules:doctor` et
  `Boundaries` le vérifient.
- **`routes.prefix`** — Par défaut `{module}/{name}`. L’URL du fichier de routes d’un module : le
  `routes/api.php` d’`iam` est servi sous `/iam/api/…`. Une passerelle qui route par chemin doit le
  suivre.
- **`status_route`** — Par défaut `null`. Variable d’environnement : `MODULES_STATUS_ROUTE`. Un
  chemin qui répond quels modules ce processus exécute, comme `/`. `null` n’enregistre rien.

## Les appels entre modules

### Choisir le transport des appels

- **Dans `config/distributable.php` :** `rpc.transport`, `null` par défaut.
- **Dans `.env` :** `DISTRIBUTABLE_RPC_TRANSPORT`, non réglée.

Un appel vers un module qui s’exécute dans un autre processus part sur ce transport, sauf si le
`host` de ce module en nomme un autre. Avec `null`, c’est `http` dès que
`mk-josias/laravel-distributable-rpc-http` est installé. Les transports sont listés dans
`rpc.transports`, chacun avec un `driver` et ses options, comme les connexions de file ; ajoutez le
vôtre comme le montre [Écrire un pilote](/fr/extend/driver).

### Garder les réponses qu’un client a reçues

- **Dans `config/distributable.php` :** `rpc.cache`, `null` par défaut.
- **Dans `.env` :** `DISTRIBUTABLE_RPC_CACHE_STORE`, non réglée.

Le stockage de cache où un client garde les réponses qu’il a reçues. Prenez-en un que chaque
processus atteint, comme Redis : le module à qui appartiennent les données y oublie une réponse
quand il écrit. `null` est le stockage par défaut. Voir
[Garder une réponse](/fr/talk/calls#garder-une-reponse).

## Événements : l’envoi

### Garder un événement jusqu’à son envoi

- **Dans `config/distributable.php` :** `events.reliable_publish`, `false` par défaut.
- **Dans `.env` :** `DISTRIBUTABLE_EVENTS_RELIABLE_PUBLISH`, non réglée.

Par défaut, un événement est envoyé juste après le commit de la transaction qui l’a émis. Si le
processus meurt à ce moment-là, ou si le flux est indisponible, l’événement est perdu. Réglez-le à
`true` et l’événement est aussi écrit dans la base du module, dans la même transaction : il y reste
jusqu’à son envoi, et `stream:republish` envoie ceux qui ont échoué.

```dotenv
DISTRIBUTABLE_EVENTS_RELIABLE_PUBLISH=true
```

### Choisir le flux d’un événement

- **Dans `config/distributable.php` :** `events.stream`, `default` par défaut.
- **Dans `.env` :** `DISTRIBUTABLE_STREAM`, non réglée.

Un événement nomme son flux avec `#[Emits(..., stream: 'audit')]` ; sans cela, c’est ce flux qui
sert. Les flux sont listés dans `events.streams`, chacun avec un pilote et ses options (celles de
`redis` sont dans [Flux d’événements Redis](/fr/reference/stream-redis)). Le flux `default` utilise
le pilote que nomme `DISTRIBUTABLE_STREAM_DRIVER` : `array` tant que vous ne le réglez pas à
`in-process` ou `redis`, chacun une fois son paquet installé.

## Événements : la réception

### Dire quel handler s’exécute pour quel événement

- **Dans `config/distributable.php` :** `events.listen`, `[]` par défaut.

```php
'events' => ['listen' => ['iam.user.registered' => [SendWelcome::class]]],
```

Un module déclare plutôt ses handlers dans le `$streamHandlers` de son service provider. Voir
[Le traiter dans un autre module](/fr/talk/events#le-traiter-dans-un-autre-module).

### Traiter un événement une seule fois

- **Dans `config/distributable.php` :** `events.guard`, `null` par défaut.
- **Dans `.env` :** `DISTRIBUTABLE_STREAM_GUARD`, non réglée.

Un flux peut livrer deux fois le même événement, après un plantage ou une nouvelle tentative. La
garde note en base qu’un handler a déjà traité un événement, et la deuxième livraison ne fait rien.
Laissez-la à `null` : elle s’active d’elle-même quand le flux peut livrer deux fois. `true` ou
`false` la force.

### Nettoyer les notes de la garde

- **Dans `config/distributable.php` :** `events.guard_cleanup`, `prune` par défaut.

Les notes de la garde s’accumulent dans `event_consumptions`. Avec `prune`, elles restent jusqu’à ce
que vous lanciez `stream:prune-consumptions`. Avec `acknowledge`, une note est supprimée dès que le
flux confirme que l’événement est traité : rien ne s’accumule (Redis seulement).

## Événements : garder et archiver

### Nommer les tables

- **Dans `config/distributable.php` :** `events.tables`, par défaut `publications` →
  `event_publications`, `consumptions` → `event_consumptions`.

Les deux tables écrites dans la base de chaque module : les événements en attente d’envoi, et les
notes de la garde. Ne changez les noms que s’ils entrent en conflit avec les vôtres.

### Choisir où va une archive

- **Dans `config/distributable.php` :** `events.archive`, `file` par défaut.
- **Dans `.env` :** `DISTRIBUTABLE_STREAM_ARCHIVE`, non réglée.

L’endroit où `stream:export` et `stream:import` mettent ce qu’ils copient quand `--store` n’est pas
donné. `file` écrit des lignes JSON ; ajoutez un autre stockage comme le montre
[Exploiter le flux d’événements](/fr/run/streams#archiver-le-flux).

## Transmettre le contexte

- **Dans `config/distributable.php` :** `events.propagate`, `[]` par défaut.

```php
'events' => ['propagate' => ['trace_id', 'locale']],
```

Ces clés du `Context` de Laravel sont copiées dans les en-têtes d’un événement quand il est émis, et
restaurées autour de chaque handler. Les appels RPC les transportent aussi.

## Les réglages et les services d’un module

Le dossier `config/` d’un module recouvre celui de l’application pendant que son code s’exécute
([Les réglages d’un module](/fr/build/settings)).

- **`shared`** — Par défaut `['distributable']`. Les fichiers de config que le processus lit en
  entier : les valeurs de chaque module s’ajoutent à celles de l’application, pour tous les modules.
- **`module_services`** — Par défaut : les services de Laravel construits à partir de la config. Les
  services construits une fois par module au lieu d’une fois par processus ; votre liste remplace
  celle du paquet, donc gardez ses noms à côté des vôtres
  ([Les services d’un module](/fr/build/services)) :

```php
'module_services' => [
    'db', 'cache', 'cache.store', 'queue', 'queue.connection', 'mail.manager', 'mailer',
    'filesystem', 'filesystem.disk', 'filesystem.cloud', 'log', 'redis',
    BroadcastManager::class, Broadcaster::class,
    'auth', 'auth.driver', 'validator', 'validation.presence',
],
```
