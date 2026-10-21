# Redis Event Stream

The `redis` events driver of [laravel-distributable](https://github.com/mk-josias/laravel-distributable/tree/main/packages/core), on Redis Streams.

```bash
composer require mk-josias/laravel-distributable-stream-redis
```

Its provider is discovered and registers the driver. The shipped `default` stream uses it once
`DISTRIBUTABLE_STREAM_DRIVER=redis`. Its options are in the package's own file, which
`php artisan vendor:publish --tag=stream-redis-config` publishes to `config/stream-redis.php`:

```php
// config/stream-redis.php
return [
    // a database.redis connection, null for `default`
    'connection' => env('DISTRIBUTABLE_STREAM_CONNECTION'),
    // how long a read waits, in ms
    'block' => 5_000,
    // entries per read
    'count' => 50,
    // after how long a dead consumer's pending entries are taken back, in ms
    'claim_after' => 60_000,
    // block: a failed entry is retried before later ones;
    // skip: later ones go on, the failed one is dropped
    'on_failure' => 'block',
];
```

All the entries of a stream are in one Redis stream: `distributable:events` for the `default`
stream, `distributable:{name}` for another. A stream can set any of these options for itself, in
`distributable.events.streams.{name}` (`key` included), and the others keep the values above:

```php
// config/distributable.php
'streams' => [
    'default' => ['driver' => 'redis'],
    'shadows' => ['driver' => 'redis', 'on_failure' => 'skip'],
],
```

- **Order:** every module writes to one Redis stream, so its entries keep the order they were
  published in
- **A cursor per consumer:** one consumer group per consuming module: a new consumer disturbs none
  of the others
- **At least once:** an entry is acknowledged after its handlers succeed; an unacknowledged one
  comes back
- **After a failure:** `block`: the failed entry is retried before any later one; `skip`: later ones
  go on, the failed one is dropped

It implements `ArchivesStreams` (`stream:export`, and `--acknowledged`), `ReportsAcknowledgements`
(`guard_cleanup => acknowledge`) and `TrimsStreams` (`stream:trim`). Nothing is trimmed on write:
run `stream:trim` on a schedule.

Needs Redis 5 or later, with PhpRedis or Predis.
