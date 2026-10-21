# A module's services

By the end of this page, your modules each have their own cache, mailer and disks, and you know how
to give the same to a service of your own.

## The problem

A module's settings follow the module, but Laravel builds some services once, from the settings it
finds the first time, then reuses them. The cache, the mailer, the filesystem and most package
clients work like this. Without more, `Cache::get()` in `analytics` would keep using the prefix
`iam` set, if `iam` was the first to run.

`module_services` in `config/distributable.php` lists the services built once for each module, from
that module's own settings. The package's list already holds Laravel's:

```php
'module_services' => [
    'db', 'cache', 'cache.store', 'queue', 'queue.connection', 'mail.manager', 'mailer',
    'filesystem', 'filesystem.disk', 'filesystem.cloud', 'log', 'redis',
    BroadcastManager::class, Broadcaster::class,
    'auth', 'auth.driver', 'validator', 'validation.presence',
],
```

The first time a module uses a listed service, the package builds it for that module. Every time
that module runs again, the same instance is back; another module gets its own.

## What the application taught them

A driver given to `extend()`, or a validation rule, is taught to the instance the application
holds. Each module's own instance learns it too, whenever the application learned it, and builds
the driver with the module's settings:

```php
// app/Providers/AppServiceProvider.php
Storage::extend('dropbox', fn ($app, array $config) => new Filesystem(new DropboxAdapter($config)));
Validator::extend('even', fn (string $attribute, mixed $value): bool => $value % 2 === 0);
```

```php
// in iam: the dropbox disk iam's config/filesystems.php sets, and the rule
Storage::disk('dropbox')->put('avatar.png', $png);
Validator::make(['n' => 2], ['n' => 'even'])->passes();
```

A driver a module's code teaches while it runs stays that module's.

## The names to add

A service the list doesn't hold is added under the name it is registered with, the one its provider
gives to `singleton()`:

- a package's service: the name its provider gives it. Look for `singleton(` in the package's
  service provider; `app()->bound('name')` tells you in Tinker that a name exists;
- a singleton of yours that receives one of these in its constructor, such as `ReportBuilder`: its
  class name.

Your `config/distributable.php` replaces the package's list: keep its names next to yours.

## An example: a disk per module

```php
// apps/Iam/config/filesystems.php: the folder iam's files go in
return ['disks' => ['local' => ['root' => storage_path('app/iam')]]];
```

```php
// in iam: storage/app/iam/avatar.png
Storage::disk('local')->put('avatar.png', $png);
```

The folder is the one you put in the module's `config/filesystems.php`; the package decides
nothing. Without `'filesystem'` in `module_services`, the first module to run would set the folder
for all.

## A service of your own

A singleton that receives the cache or the mailer in its constructor keeps the instance it received
when it was built. Built in `iam`, it would keep reading `iam`'s cache when `analytics` uses it. List
its class: it is then built for each module, with that module's services.

```php
final class ReportBuilder
{
    public function __construct(private readonly \Illuminate\Contracts\Cache\Repository $cache) {}
}
```

```php
// config/distributable.php
'module_services' => [
    'db', 'cache', 'cache.store', 'queue', 'queue.connection', 'mail.manager', 'mailer',
    'filesystem', 'filesystem.disk', 'filesystem.cloud', 'log', 'redis',
    BroadcastManager::class, Broadcaster::class,
    'auth', 'auth.driver', 'validator', 'validation.presence',
    ReportBuilder::class,
],
```

## Bindings

A binding made in the application's provider or in a module's provider is shared by the whole
process: it holds for every module that runs in it. `module_services` changes the instance built
from the binding, never the class it binds, and an instance already built doesn't follow a binding
changed afterwards.

To make two modules behave differently, bind one class that reads the module's settings when it is
built, and list it in `module_services`.

## Next

[Migrations](/build/migrations).
