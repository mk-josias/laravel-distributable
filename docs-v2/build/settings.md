# A module's settings

By the end of this page, you can give a module its own configuration and its own connections, and
you know when they apply.

A module's code stays ordinary Laravel: `User::query()`, `DB::transaction()`, `Cache::get()`, with
no connection or setting named anywhere. Whichever way its code starts (a request, a job, a
command, an event, a call from another module), it runs with its own settings, and the previous
module's come back afterwards.

![analytics calls iam through its client: the same DB::transaction() lands in iam's connection, then analytics' settings come back; outside any module any query throws](/images/module-context.png)

## Give a module its own settings

The application's `config/` holds the defaults every module shares, and a module's `config/` holds
what is its own. While a module's code runs, it sees the application's config with its own files on
top:

- each value a module's file sets replaces the application's: a string, a number, a list
  (`['a', 'b']`) or `[]`. Any other array is gone through key by key;
- a key the application doesn't set exists only while that module runs;
- what the module's code changes with `config()->set()` stays its own: it is back the next time the
  module runs, and the application never sees it;
- outside the module, the application's values come back. Two modules in one process each keep
  theirs.

```php
// apps/Iam/config/cache.php: while iam's code runs, config('cache.prefix') is 'iam'
return ['prefix' => 'iam'];
```

A file named in `shared` (`config/distributable.php`) is the exception: the process reads it whole,
so each module's values are added to the application's for every module. By default it holds
`distributable`, where a module declares its streams. When two modules of one process set a key of
such a file differently, the module registered last wins, and `modules:doctor` reports it.

A service Laravel builds once from the config, such as the cache, keeps the values it was built
with: [A module's services](/build/services) gives each module its own. Once `config:cache` has run,
the cache holds every module's files and they are not read again.

## The connection

The first setting most modules make is the connection their code runs on. A module declares its
connections in its own `config/database.php`, and `default` names the one its code runs on:

```php
// apps/Iam/config/database.php
'default' => 'iam',
'connections' => [
    // reads and writes rows
    'iam' => [
        'driver' => 'pgsql', 'database' => 'iam',
        'username' => env('IAM_USERNAME'), 'password' => env('IAM_PASSWORD'),
    ],
    // creates and alters tables
    'owner' => [
        'driver' => 'pgsql', 'database' => 'iam',
        'username' => env('IAM_OWNER'), 'password' => env('IAM_OWNER_PASSWORD'),
    ],
],
```

A connection a module declares exists only while its code runs. Two modules can give one the same
name and still each reach their own database: every module of the starter names `owner` the role
that owns its tables, and migrates with `--database=owner` (see
[Migrations](/build/migrations#running-them)). The connections can also sit in the root
`config/database.php`, next to the others: every module sees them then, and runs on the one its
`default` names.

They can point to separate databases on one server, separate schemas of one database, separate
servers, or sqlite files in tests: the code is the same. Several modules can share one database;
give their tables distinct names then (`protected $table = 'iam_users'`). Without `default`, the
module has no connection set: its models must name theirs.

Models are plain Eloquent models, `User extends Authenticatable` included. `DB::`,
`DB::transaction()` and `Schema::` use the module's connection, with nothing to name:

```php
// apps/Iam/app/Actions/RegisterUser.php
final class RegisterUser
{
    public function execute(string $name): User
    {
        // iam's connection
        return DB::transaction(fn () => User::query()->create(['name' => $name]));
    }
}
```

### A second connection

A module that needs a warehouse next to its own connection declares it, and names it where it uses
it:

```php
// apps/Analytics/config/database.php
'default' => 'analytics',
'connections' => [
    'analytics' => ['driver' => 'pgsql', 'database' => 'analytics'],
    'warehouse' => ['driver' => 'pgsql', 'database' => 'warehouse'],
],
```

```php
// apps/Analytics/app/Models/Report.php
protected $connection = 'warehouse';
```

A migration that sets `$connection = 'warehouse'` runs there.

## When a module's settings apply

You don't switch anything yourself. A module's settings apply to:

- a request on the module's routes, or on any route whose controller is a module's class;
- a job, a command, an event handler of the module;
- a Laravel listener the module declares in `$listen`, a task it schedules in `schedule()`;
- a call another module sends it, from this process or another;
- a queued closure, `defer()`, `Concurrency::run()` or `Octane::concurrently()` started in the
  module.

When analytics calls `IamClient::findUser()` and iam runs in the same process, iam's handler runs
with iam's settings, and analytics' come back when the method returns.

A closure sent to another process (Octane tasks, `Concurrency`, queued closures) is rebuilt from its
line in the file: keep one closure per line, or the worker may rebuild the wrong one.

### Work that runs later

For a closure of yours that runs later or elsewhere, `ModuleContext::bind()` keeps the current
module with it:

```php
$task = app(\Distributable\Context\ModuleContext::class)->bind(fn () => Report::rebuild());
```

`ModuleContext::current()` returns the module running now, or `null` outside any module.

## Outside a module

Outside a module there is no default connection: a route closure in `routes/web.php`, another
package's route, or Tinker without `--module` throws `ModuleException` ("No module runs here") on
its first query. Go through the module's client instead, which still works when the module runs
elsewhere:

```php
// never Apps\Iam\Models\User::find($id)
app(\Foundation\Iam\Services\IamClient::class)->findUser($id);
```

There are no foreign keys between modules. Reference another module's rows by id, or keep a
[copy](/talk/copies) of them.

## Reaching another module

A module can't run its code with another module's settings: `within()`, called from another
module's code or from the foundation, throws a `ModuleException`. The way into another module is
its messages, see [Calling another module](/talk/calls).

## Failed jobs and batches

A failed job is stored on the connection it ran on: the one of its module, which it carries even
when that module has since been renamed or removed. A batch is stored on the connection of the
module of its first job, or of the module running when the job has none. Neither is ever stored on
the connection of another module: with no connection to go to, the package throws. There is no
application-wide database: `queue.failed.database` and `queue.batching.database` are read only from
a module's own `config/queue.php`, to send its failed jobs or its batches to another connection that
module declares. Without them, a module uses its own connection.

```php
// apps/Analytics/config/queue.php
return ['failed' => ['database' => 'warehouse']];
```
`queue:failed`, `queue:retry` and `Bus::findBatch()` read the connections of the modules this
process runs. This needs Laravel's database storage (the `database-uuids` failer and database
batches); the tables come from the application's `database/migrations`, which run in every
module's database.

## Next

[A module's services](/build/services): the cache, the mailer and the disks follow the module too.
