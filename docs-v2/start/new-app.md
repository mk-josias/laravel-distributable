# A new application

By the end of this page, you have a Laravel application with a module `billing` that answers a
route, on its own connection.

## Install the package

```bash
composer require mk-josias/laravel-distributable
# the events driver: Redis Streams (or yours); DISTRIBUTABLE_STREAM_DRIVER=redis
composer require mk-josias/laravel-distributable-stream-redis
# calls to a module running in another process
composer require mk-josias/laravel-distributable-rpc-http
# config/distributable.php and the modules directory
php artisan modules:install
```

The two drivers matter when modules exchange events, or run in separate processes. A single module
needs neither yet.

## Create the first module

```bash
php artisan modules:make billing
```

```
apps/Billing/
├── app/
│   ├── Models/
│   └── Providers/BillingServiceProvider.php
├── bootstrap/providers.php
├── config/database.php
├── database/migrations/
├── routes/api.php
└── tests/Feature/
```

It is declared in `config/distributable.php` too. Everything in the module is plain Laravel, laid
out like an application: a model in `app/Models`, a route in `routes/api.php`, a migration in
`database/migrations`. The package finds them from the folder.

## Give it its connection

`config/database.php` names the connection the module's code runs on. Edit it to point to yours:

```php
// apps/Billing/config/database.php
return [
    'default' => 'billing',
    'connections' => [
        'billing' => ['driver' => 'pgsql', 'database' => 'billing', 'username' => 'billing_app'],
    ],
];
```

## Write a route and a model

```bash
php artisan make:model Invoice -mf --module=billing
php artisan make:controller InvoiceController --module=billing
php artisan migrate --module=billing
```

`routes/api.php` is served under `/billing/api/…`. The model needs no connection: while billing's
code runs, Laravel's default connection is `billing`.

## Check it

```bash
php artisan modules:list
php artisan modules:doctor
```

## Next

- **Add a second module and make them talk:** [Call, event or copy?](/talk/choosing).
- **Learn what you just set up:** [Create a module](/build/create),
  [A module's settings](/build/settings).
