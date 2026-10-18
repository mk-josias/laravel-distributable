# Events

## How an event travels

Events are asynchronous: a service announces that something happened, and the services that care
handle it later, in their own consumer process.

```
emitting service                          stream                    consuming service
emit(Event) ─► Envelope ─► [row ─► commit] ─► transport ─► stream:consume
                           (table)            (redis …)     └► Dispatcher ─► handlers
```

The consuming service declares its handlers:

```php
// config/microservices.php
'events' => ['listen' => ['orders.order.placed' => [OpenInvoice::class]]],
```

A handler implements `Distributable\Contracts\Stream\Handler` and receives the event name and the
raw payload.

## The envelope

```json
{
  "id":         "0191f3c2-8a41-7c2e-9b55-3f1c7d0a4e88",
  "emitter":    "orders",
  "name":       "orders.order.placed",
  "payload":    { "id": 42 },
  "headers":    { "trace_id": "4bf92f35" },
  "emitted_at": "2026-09-30T13:22:41.512000Z",
  "recipients": [],
  "stream":     "default",
  "version":    1
}
```

`emitter` is the service the event class belongs to: `microservices.name` in an application on its
own. Override `Event::emitter()` to set it yourself.

`recipients` is optional. When it's empty, every service can handle the event. When you fill it by
overriding `Event::recipients()`, only the listed services handle it. The other consumers
acknowledge it and skip it, and the `queue` transport doesn't deliver it to them at all.

## Versioning a payload

A stream keeps events for a long time, and a consumer may be deployed after the emitter. `version`
is the version of the payload's shape. It stays at 1 as long as you only add optional fields.
Renaming a field, removing one or changing what one means raises it.

The emitter declares its versions on a payload class, which consumers can read too:

```php
final class OrderPlacedPayload implements \Distributable\Contracts\Stream\Versioned
{
    public static function version(): int { return 2; }

    public static function upcast(int $from, array $payload): array
    {
        return match ($from) {
            // 2 renamed amount
            1 => ['id' => $payload['id'], 'total' => $payload['amount']],
            default => $payload,
        };
    }
}

// in a RpcServiceProvider
protected array $payloads = ['orders.order.placed' => OrderPlacedPayload::class];
```

Before a handler runs, the payload is lifted one version at a time up to the version the process
reads. A handler therefore only ever sees the current shape, including for events written before
the change.

| The envelope's version | What happens |
|---|---|
| the version the process reads | the handler gets the payload as it is |
| older | `upcast()` runs once per missing version, then the handler |
| newer: the emitter was deployed before this consumer | `ServiceException`, no handler runs. On the `redis` transport, `on_failure: block` makes the stream wait for the consumer's deployment; with `skip` the later entries go on and this one is dropped |
| above 1 for an event with no entry in `$payloads` | the same `ServiceException` |

## Context propagation

```php
'events' => ['propagate' => ['trace_id', 'locale']],
```

These keys of Laravel's `Context` are copied into the envelope headers when the event is emitted,
and restored around each handler. RPC calls carry them too.

## Transports

| Driver | Comes with | |
|---|---|---|
| `redis` | [`mk-josias/laravel-microservices-stream-redis`](https://github.com/mk-josias/laravel-microservices/tree/main/packages/stream-redis) | Redis Streams, for the `default` stream once `MICROSERVICES_STREAM_DRIVER=redis`. One Redis stream per configured stream (`microservices:events`), written by every service, and one consumer group per consuming service. Entries are handled in the order they were published. With `'on_failure' => 'block'` (the default), a failing entry blocks the ones after it and is retried first. With `'skip'`, the ones after it go on, and the failed entry is dropped. |
| `queue` | [`mk-josias/laravel-microservices-stream-queue`](https://github.com/mk-josias/laravel-microservices/tree/main/packages/stream-queue) | Any Laravel queue connection (`database`, `sqs`, …), if you don't use Redis. Each envelope is copied to one queue per declared service (`microservices:events-{service}`). A failed envelope is retried after the ones behind it, so order isn't kept after a failure. |
| `array` | this package | In memory, for tests. Consuming reads everything and returns. |
| `null` | this package | Drops everything. |

A stream is an entry in `microservices.events.streams` with a driver and its options, like a queue
connection. An event chooses its stream with `stream()`; `null` means `microservices.events.stream`.
Order is only kept within a stream.

You can add your own driver; [Writing a transport](transports.md) lists what it has to guarantee:

```php
app(\Distributable\Services\Stream\TransportManager::class)
    ->extend('kafka', fn ($app, array $options, string $stream) => new KafkaTransport($options));
```

A transport implements `publish()`, a blocking `consume()` loop and `stop()`. If the consume
callback returns normally, the message is acknowledged; if it throws, it isn't. The other commands
ask the transport for more, through these interfaces:

| Interface | Used by | Without it |
|---|---|---|
| `Contracts\Stream\TrimsStreams` | `stream:trim` | the command trims nothing |
| `Contracts\Stream\ArchivesStreams` | `stream:export`, which reads the entries the stream keeps | the command says the transport cannot give its entries back |
| `Contracts\Stream\ReportsAcknowledgements` | `guard_cleanup => acknowledge`, which deletes a consumption mark once its entry is acknowledged | the marks stay until `stream:prune-consumptions` |
| `Contracts\Stream\RedeliversEnvelopes` | the consumption guard, turned on when delivery is at-least-once | the guard stays off unless events are published reliably |

Redis keeps every entry until every consumer group has acknowledged it. Nothing is trimmed when
writing, so a stopped consumer or a service added later doesn't miss anything. Run the trim command
on a schedule:

```bash
php artisan stream:trim [--stream=default]
```

## Reliable publishing

An event is always sent once the transaction it was emitted in has committed, and never if that
transaction rolls back. With `MICROSERVICES_EVENTS_RELIABLE_PUBLISH=true` (`events.reliable_publish`), `emit()` also
writes a row to `event_publications` in the emitting service's database, inside that transaction:
the data and the event are committed or rolled back together, so a committed event is not lost if
the process dies before sending it. Once the transaction commits, the row is sent to the stream,
with no process of its own, and deleted once it is sent. If that fails, the row stays with its
attempts and last error, and the request is not affected. The table is created by `migrate` only
with this setting: turn it on, then run `migrate`.

Without it, which is the default, no row is kept: an event is lost if the process dies, or the
stream is down, between the commit and the sending. No table is created.

Sending is not ordered across requests: two commits at the same time can reach the stream in either
order. A row still stored goes out with the next `stream:republish`:

```bash
# send the events still stored, because they could not go out after their commit
php artisan stream:republish [--service=*]
```

## Archiving the stream

`stream:export` and `stream:import` work on the stream itself, not on the table: the transport
keeps what was published, so they need no setting.

```bash
# copy what the stream keeps to an archive
php artisan stream:export storage/events.jsonl [--store=file] [--service=*] [--stream=default] \
    [--since=2026-08-01] [--until=2026-09-01] [--where=name=orders.order.placed] [--where=payload.status=paid] \
    [--acknowledged] [--batch=1000]
# publish the entries of an archive back on a stream, for example one that was emptied
php artisan stream:import storage/events.jsonl [--store=file] [--service=*] [--stream=default] \
    [--since=2026-08-01] [--until=2026-09-01] [--where=payload.status=paid]
```

Every service writes to the same stream, so an export takes all of them; `--service` keeps the
entries one service emitted. Nothing is deleted: `stream:trim` drops what every consumer has read.
`--acknowledged` stops below what every consumer group has acknowledged. The transport has to
implement `ArchivesStreams` (`redis` does). `import` publishes the entries again: the consumption
guard turns what a consumer already applied into a no-op, unless `guard_cleanup` is `acknowledge`,
which deletes the marks, so an imported event is applied again.

The archive is a store: `file` (JSON lines, one envelope per line) ships, and
`microservices.events.archive` names the default. Another one implements
`Contracts\Stream\ArchiveStore` (`write($target, $rows)`, `read($source)`) and is registered by name:

```php
app(\Distributable\Services\Stream\ArchiveStores::class)
    ->extend('s3', fn ($app) => new S3ArchiveStore($app->make('filesystem')->disk('s3')));
```

## Consuming

```bash
php artisan stream:consume [--service=billing] [--stream=default]
```

The command reads one stream for every declared emitter on it, as each service that runs in the
process: `--service` names one, otherwise it is the service running, otherwise every local one. Each
consuming service keeps its own cursor (a Redis group, a queue `{key}-{service}`), so several
services in one process are served by one command. An entry whose emitter isn't a declared service
is acknowledged unread. Run one process per stream, like `queue:work`. On `SIGTERM` it finishes the
current message and stops.

With several consuming services, the `redis` transport reads one group at a time and shares the
stream's `block` window between them: an entry can wait up to `(N-1)/N × block` while the other
groups are read. With `on_failure` at `block`, a failing handler delays only its own service.

An entry that can never succeed holds its service as long as `on_failure` is `block`. `stream:skip`
drops it without running its handler, and the stream goes on; that event is lost for that service.

```bash
# the oldest failing entry of the service running
php artisan stream:skip [--service=billing] [--stream=default]

# everything up to a date, entries not read yet included
php artisan stream:skip --service=billing --until="2026-10-16 12:00"
```

`--until` only moves the cursor forward, never past the present moment, and says how many entries it dropped. The `redis` transport
does it; a transport that can't answers that it cannot skip entries.

In a test, `Distributable\Testing\InteractsWithServices::receive('orders.order.placed', [...])`
hands the event to the handlers, without a stream or a consumer.

## Idempotent handlers

When delivery is at-least-once, each handler run is guarded: an `(event_id, handler)` row is
inserted into `event_consumptions` in the same transaction as the handler's writes. If the event is
delivered again, the row is already there and the handler doesn't run. If the handler throws, the
row is rolled back and the event is retried. The guard is enabled automatically with reliable publishing or
with a transport that implements `Contracts\Stream\RedeliversEnvelopes` (`redis` and `queue` do).
A handler that is already idempotent can implement `Contracts\Stream\Idempotent` to skip it.
`MICROSERVICES_STREAM_GUARD=false` turns the guard off for every handler, and `migrate` then
doesn't create `event_consumptions`.

The guard is what lets events be sent again when needed (`stream:republish`, `stream:import`): an
event a consumer already applied does nothing the second time. Whether sending again is right
depends on the nature of the events. When order matters and only the last event counts, an older
event sent again after a newer one can overwrite it, and the guard cannot tell: it only recognises
an event it has already seen.

The transaction stays open for the whole handler, so a guarded handler calls other services before
it writes: its rows are not locked while it waits on the network.
