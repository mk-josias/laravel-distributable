# Flux d’événements Redis

Le pilote d’événements `redis` de [laravel-distributable](https://github.com/mk-josias/laravel-distributable/tree/main/packages/core), sur Redis Streams.

```bash
composer require mk-josias/laravel-distributable-stream-redis
```

Son provider est découvert et enregistre le pilote. Le flux `default` fourni l’utilise dès que
`DISTRIBUTABLE_STREAM_DRIVER=redis`. Ses options sont dans le fichier propre au paquet, que
`php artisan vendor:publish --tag=stream-redis-config` publie dans `config/stream-redis.php` :

```php
// config/stream-redis.php
return [
    // une connexion de database.redis, null pour `default`
    'connection' => env('DISTRIBUTABLE_STREAM_CONNECTION'),
    // combien de temps attend une lecture, en ms
    'block' => 5_000,
    // entrées par lecture
    'count' => 50,
    // après combien de temps les entrées en attente d'un consommateur mort sont reprises, en ms
    'claim_after' => 60_000,
    // block : une entrée en échec est retentée avant les suivantes ;
    // skip : les suivantes continuent, celle en échec est abandonnée
    'on_failure' => 'block',
];
```

Toutes les entrées d’un flux sont dans un seul stream Redis : `distributable:events` pour le flux
`default`, `distributable:{nom}` pour un autre. Un flux peut régler n’importe laquelle de ces options
pour lui-même, dans `distributable.events.streams.{nom}` (`key` compris), et les autres gardent les
valeurs ci-dessus :

```php
// config/distributable.php
'streams' => [
    'default' => ['driver' => 'redis'],
    'shadows' => ['driver' => 'redis', 'on_failure' => 'skip'],
],
```

- **Ordre :** chaque module écrit dans un seul stream Redis, donc ses entrées gardent l’ordre dans
  lequel elles ont été publiées
- **Un curseur par consommateur :** un groupe de consommateurs par module consommateur : un nouveau
  consommateur ne dérange aucun des autres
- **Au moins une fois :** une entrée est acquittée après la réussite de ses handlers ; une entrée non
  acquittée revient
- **Après un échec :** `block` : l’entrée en échec est retentée avant toute suivante ; `skip` : les
  suivantes continuent, celle en échec est abandonnée

Il implémente `ArchivesStreams` (`stream:export`, et `--acknowledged`), `ReportsAcknowledgements`
(`guard_cleanup => acknowledge`) et `TrimsStreams` (`stream:trim`). Rien n’est coupé à l’écriture :
lancez `stream:trim` de façon planifiée.

Demande Redis 5 ou plus, avec PhpRedis ou Predis.
