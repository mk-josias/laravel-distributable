# Laravel Distributable

Design each Laravel module as a microservice: a context bounded to its module, contracts and
events to talk to the others, and the same code whether modules run in the same process or in
separate, distributed processes.

## Install

```bash
composer require mk-josias/laravel-distributable
php artisan modules:install
php artisan modules:make iam
```

Or start from the example application, three modules each with its own database:

```bash
composer create-project mk-josias/laravel-distributable-starter my-app
```

## What is in this repository

- **`packages/core`**
  - Published as: `mk-josias/laravel-distributable`
  - the package: [README and documentation](packages/core/README.md)
- **`packages/rpc-http`**
  - Published as: `mk-josias/laravel-distributable-rpc-http`
  - the HTTP transport of the calls between modules
- **`packages/stream-redis`**
  - Published as: `mk-josias/laravel-distributable-stream-redis`
  - the Redis Streams driver of the events
- **`packages/stream-in-process`**
  - Published as: `mk-josias/laravel-distributable-stream-in-process`
  - the in-process driver of the events, with no broker
- **`starter`**
  - Published as: `mk-josias/laravel-distributable-starter`
  - the example application

## Developing

```bash
composer install
composer check   # Pint, PHPStan, Pest
```

MIT license.
