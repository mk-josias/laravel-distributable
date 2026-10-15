# Laravel Distributable Modules

Design each Laravel module as a microservice: a context bounded to its module, contracts and
events to talk to the others, and the same code whether modules run in the same process or in
separate, distributed processes.

## Install

```bash
composer require mk-josias/laravel-distributable-modules
php artisan modules:install
php artisan modules:make iam --database
```

Or start from the example application, three modules each with its own database:

```bash
composer create-project mk-josias/laravel-distributable-modules-starter my-app
```

## What is in this repository

| Folder | Published as | |
|---|---|---|
| `packages/core` | `mk-josias/laravel-distributable-modules` | the package: [README and documentation](packages/core/README.md) |
| `starter` | `mk-josias/laravel-distributable-modules-starter` | the example application |

It builds on [laravel-microservices](https://github.com/mk-josias/laravel-microservices), which
provides the events, calls and copies between services.

## Developing

`composer.json` reaches laravel-microservices through a path repository at
`../laravel-microservices`: clone it next to this repository, then run `composer check`
(Pint, PHPStan, Pest).

MIT license.
