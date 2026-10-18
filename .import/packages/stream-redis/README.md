# Redis Event Stream

The `redis` events driver of [laravel-microservices](https://github.com/mk-josias/laravel-microservices/tree/main/packages/core), on Redis Streams.

```bash
composer require mk-josias/laravel-microservices-stream-redis
```

Its provider is discovered and registers the driver. The shipped `default` stream uses it once
`MICROSERVICES_STREAM_DRIVER=redis`:

```php
// config/microservices.php
'default' => [
    'driver' => env('MICROSERVICES_STREAM_DRIVER', 'array'),
    // a database.redis connection, null for `default`
    'connection' => env('MICROSERVICES_STREAM_CONNECTION'),
    // the one Redis stream every service writes to
    'key' => 'microservices:events',
    // how long a read waits, in ms
    'block' => 5_000,
    // entries per read
    'count' => 50,
    // after how long a dead consumer's pending entries are taken back, in ms
    'claim_after' => 60_000,
    // block: a failed entry is retried before later ones; skip: later ones go on, the failed one is dropped
    'on_failure' => 'block',
],
```

| What it guarantees | |
|---|---|
| Order | every service writes to one Redis stream, so its entries keep the order they were published in |
| A cursor per consumer | one consumer group per consuming service: a new consumer disturbs none of the others |
| At least once | an entry is acknowledged after its handlers succeed; an unacknowledged one comes back |
| After a failure | `block`: the failed entry is retried before any later one; `skip`: later ones go on, the failed one is dropped |

It implements `ArchivesStreams` (`stream:export`, and `--acknowledged`), `ReportsAcknowledgements`
(`guard_cleanup => acknowledge`) and `TrimsStreams` (`stream:trim`). Nothing is trimmed on write:
run `stream:trim` on a schedule.

Needs Redis 5 or later, with PhpRedis or Predis.
