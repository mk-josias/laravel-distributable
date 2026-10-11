# Laravel Microservices

Laravel applications working together as microservices: signed RPC through shared contracts, ordered
events with an outbox, and read-only copies of each other's rows.

This repository holds the packages, each published on its own:

| Package | Composer | |
|---|---|---|
| [core](packages/core) | `mk-josias/laravel-microservices` | contracts, RPC, events, outbox, copies; `array` and `null` stream drivers |
| [rpc-http](packages/rpc-http) | `mk-josias/laravel-microservices-rpc-http` | the `http` RPC driver: signed calls, one endpoint per service |
| [stream-redis](packages/stream-redis) | `mk-josias/laravel-microservices-stream-redis` | the `redis` events driver, on Redis Streams |
| [stream-queue](packages/stream-queue) | `mk-josias/laravel-microservices-stream-queue` | the `queue` events driver, on Laravel queues |

[examples/](examples) runs iam, notifications and analytics as three applications behind a Node gateway, in CI.

```bash
composer install
composer check   # pint, phpstan and the tests of every package
```
