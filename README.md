# Laravel Microservices

Laravel applications working together as microservices: signed RPC through shared contracts, ordered
events with an outbox, and read-only copies of each other's rows. Each service owns its data; the
package depends on nothing but Laravel.

[Documentation](https://mk-josias.github.io/laravel-microservices/) · [Packagist](https://packagist.org/packages/mk-josias/laravel-microservices)

## What it solves

| A service needs to | It does |
|---|---|
| ask another service something | calls an interface it shares with it: `app(BillingService::class)->invoiceFor(7)` is a signed HTTP request, with no route to write on either side |
| tell the others something happened | emits an event: written in the same transaction as the data, published in order, handled once even when it is delivered twice |
| join, filter or sort on another service's rows | keeps the columns it needs in its own database, filled by that service's events, and refuses any write that doesn't come from them |
| work with a service in another language | reads and writes the same JSON envelopes on the stream, and calls the same signed endpoints |

```php
// in orders: ask billing, through the contract billing shares
$invoice = app(BillingService::class)->invoiceFor($order->id);

// in orders: announce, for whoever listens
app(Bus::class)->emit(new OrderPlaced($order->id));
```

```bash
php artisan stream:consume   # in billing: runs the handlers listening to orders' events
```

## Packages

Each is published on its own:

| Package | Composer | |
|---|---|---|
| [core](packages/core) | `mk-josias/laravel-microservices` | contracts, RPC, events, outbox, copies; `array` and `null` stream drivers |
| [rpc-http](packages/rpc-http) | `mk-josias/laravel-microservices-rpc-http` | the `http` RPC driver: signed calls, one endpoint per service |
| [stream-redis](packages/stream-redis) | `mk-josias/laravel-microservices-stream-redis` | the `redis` events driver, on Redis Streams: one ordered stream, a cursor per consuming service |
| [stream-queue](packages/stream-queue) | `mk-josias/laravel-microservices-stream-queue` | the `queue` events driver, on Laravel queues, for a stack without Redis |

A driver is one interface: Kafka, RabbitMQ or an existing package plug in without touching the core.
See [Writing a transport](packages/core/docs/transports.md).

## Several services in one codebase

Whether a service is its own application or a module of a larger one is a deployment choice: the
code that calls it, announces to it or copies its rows is the same.
[laravel-distributable-modules](https://github.com/mk-josias/laravel-distributable-modules) builds on
this package to run services as modules, in one process or in separate ones.

## Try it

[examples/](examples) runs iam, notifications and analytics as three applications behind a Node
gateway; CI runs it on every push.

## Development

```bash
composer install
composer check   # pint, phpstan and the tests of every package
```

The Redis tests skip themselves when no Redis server is reachable.

## License

MIT
