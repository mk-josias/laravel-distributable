# An existing application

By the end of this page, your Laravel application has a module `iam`, declared and checked, that
announces an event another module handles.

`app/` is still your Laravel application. Each module lives in `apps/`. To start from a working
application instead, [try the starter](/start/starter).

```
config/distributable.php                   declares the modules: 'declared' => ['iam' => [], …]

apps/Iam/
├── app/                              Apps\Iam\, laid out like a Laravel app
│   ├── Providers/IamServiceProvider.php   the events it handles, in $streamHandlers
│   ├── Models/User.php
│   └── Events/UserRegistered.php
├── config/
│   └── database.php                  its connections: `modules:make` always writes it
├── database/migrations/
└── routes/api.php                    served under /iam/api/…

foundation/
├── FoundationServiceProvider.php     what the foundation declares for every module
└── Iam/                              Foundation\Iam\, what iam shares with the other modules
    ├── Rpc/FindUser.php              a message iam answers
    ├── Services/IamClient.php        how another process calls iam
    ├── Shadows/UserShadow.php
    └── database/shadows/users/       the copy's migration, named by UserShadow's #[Shadow]
```

The package loads the modules, not the foundation: map `Foundation\` in `composer.json` and list
`Foundation\FoundationServiceProvider` in `bootstrap/providers.php`.

## Declare the modules

```php
// config/distributable.php
'declared' => [
    'iam' => [],
    'analytics' => [],
],
```

```php
// apps/Iam/app/Providers/IamServiceProvider.php
final class IamServiceProvider extends \Distributable\Providers\ServiceProvider {}
```

```php
// apps/Iam/app/Models/User.php: plain Eloquent, it uses iam's connection because it runs in iam
final class User extends \Illuminate\Database\Eloquent\Model {}
```

## Give a module its connections

```php
// apps/Iam/config/database.php, over config/database.php while iam runs
return [
    // the connection the module's code runs on
    'default' => 'iam',
    'connections' => [
        // reads and writes
        'iam' => ['driver' => 'pgsql', 'database' => 'iam', 'username' => 'iam_app'],
        // creates and alters tables; each module can name its own `owner`
        'owner' => ['driver' => 'pgsql', 'database' => 'iam', 'username' => 'iam_owner'],
    ],
];
```

```bash
# migrates each module's database on its own `owner` connection, see Migrations
php artisan migrate --module='*' --database=owner
```

## Announce an event, and handle it in another module

```php
#[\Distributable\Attributes\Emits('iam.user.registered')]
final class UserRegistered extends \Distributable\Events\Event
{
    public function __construct(private readonly int $id) {}

    public function payload(): array
    {
        return ['id' => $this->id];
    }
}
```

```php
app(\Distributable\Contracts\Stream\Bus::class)->emit(new UserRegistered($user->id));
```

```php
// apps/Analytics/app/Handlers/RecordSignup.php
final class RecordSignup implements \Distributable\Contracts\Stream\Handler
{
    public function handle(string $name, array $payload): void
    {
        // record the signup
    }
}
```

```php
// apps/Analytics/app/Providers/AnalyticsServiceProvider.php
protected array $streamHandlers = [
    'iam.user.registered' => [
        RecordSignup::class,
    ],
];
```

```bash
php artisan stream:consume
```

This event is version 1. When the shape of a payload changes, its payload class declares a new
version and how to read the old ones, so events already in the stream stay readable: see
[Change the shape of a payload](/talk/events#change-the-shape-of-a-payload).

## Check that every module could run apart

```bash
php artisan modules:doctor
```

```
Boundary crossed: apps/Analytics/app/Models/Report.php: Apps\Iam\Models\User
[iam] runs elsewhere and serves RPC messages, but distributable.declared.iam.host is not set.
```

It reports a module that imports another module's class or names its tables, an undeclared module,
a missing host or RPC secret, and exits with a non-zero code if it finds anything. The full list is
in [The boundaries](/understand/boundaries#before-a-deployment).

## Next

[Create a module](/build/create), then [Make modules talk](/talk/choosing).
