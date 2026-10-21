# Where things go

By the end of this page, you know which folder of a module holds what, how its routes are
prefixed, and where it declares its listeners, handlers and scheduled tasks.

A module is laid out like a Laravel application, and the package reads everything from the folder
and its namespace: there is nothing to register.

## The folders

- `app/`: its code, under `Apps\{Name}\` (`Apps\Iam` for `iam`). Its Artisan commands are every
  `Illuminate\Console\Command` under `app/`, and `app/Providers` holds its providers, listed in the
  module's own `bootstrap/providers.php`.
- `config/*.php`: its settings. `config/database.php` holds its connections, see
  [A module's settings](/build/settings).
- `routes/{surface}.php`: its routes. `routes/api.php` of `iam` is served under `/iam/api/…`.
- `lang/`: its translations, as `__('iam::messages.hello')`.
- `resources/views/`: its views, as `view('iam::welcome')`; while the module runs, `view('welcome')`
  looks there first, then in the application's `resources/views`. Anonymous components of
  `resources/views/components` and class components of `app/View/Components` are
  `<x-iam::alert />`.
- `database/migrations/`, `database/factories/` (under `Apps\{Name}\Database\Factories\`) and
  `database/seeders/` (under `Apps\{Name}\Database\Seeders\`).
- `tests/`: its tests, under `Apps\{Name}\Tests\`.

What it shares with the other modules is not in its folder, but in the
[foundation](/talk/foundation): its messages and client, its events' payloads, the shape of its
copies.

## Where the modules live

`modules.path` (`apps`) and `modules.namespace` (`Apps`) in `config/distributable.php` set where
modules are. A module's name is its folder in snake_case: `apps/PointOfSale` is `point_of_sale`. A
module that lives elsewhere declares its own `path` and `namespace`, see
[Where modules run](/run/where#what-declared-can-say-about-a-module). `foundation.path`
(`foundation`) is where `modules:doctor` and the architecture test look for the foundation.

## The routes of a module

`routes/{name}.php` is loaded under `distributable.routes.prefix`, `{module}/{name}` by default
(`apps/Iam/routes/api.php` becomes `/iam/api/…`), in the `{name}` middleware group if the
application defines one. A module sets its own prefix with `declared.{module}.prefix`:

```php
'iam' => ['prefix' => 'identity/{name}']   // /identity/api/…
```

A gateway that routes by path must use the same prefixes: the package does not tell it.

## What its provider declares

Its provider, which extends `Distributable\Providers\ServiceProvider`, declares what the module
listens to, answers, copies and schedules:

```php
final class IamServiceProvider extends ServiceProvider
{
    // Laravel events the module listens to, as in EventServiceProvider::$listen
    protected array $listen = [
        \Illuminate\Auth\Events\Login::class => [\Apps\Iam\Listeners\RecordLogin::class],
    ];

    // stream events the module handles: event name => Handler classes
    protected array $streamHandlers = [
        'payments.payment.captured' => [\Apps\Iam\Handlers\UpdatePlan::class],
    ];

    // messages the module answers: message => Handler class
    protected array $rpcHandlers = [
        \Foundation\Iam\Rpc\FindUser::class => \Apps\Iam\Rpc\FindUserHandler::class,
    ];

    // copies of other modules' rows that it keeps
    protected array $shadows = [\Foundation\Iam\Shadows\UserShadow::class];

    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('iam:prune')->daily();
    }
}
```

- `$listen`: a listener runs in the module, whichever module dispatched the event. A queued one
  keeps working as Laravel queues it, and an after-commit one runs once the transaction commits,
  still in the module.
- `$streamHandlers`: [events from other modules](/talk/events#handle-it-in-another-module),
  merged only where the module runs.
- `$rpcHandlers`: [messages the module answers](/talk/calls#answer-it).
- `$shadows`: [copies it keeps](/talk/copies#declare-the-copy).
- `schedule()`: a scheduled task runs in the module, closures included, and only a process that
  runs the module schedules it.

Laravel's own event discovery doesn't see a module's `app/Listeners`: it names
`apps/Iam/app/Listeners/X.php` `Apps\Iam\app\Listeners\X`. Declare the listeners in `$listen`. In a
test, `Event::assertListening()` only sees a closure for them: use
`$this->assertListeningInModule(Login::class, RecordLogin::class)` from `ModuleAware`.

## Next

[A module's settings](/build/settings).
