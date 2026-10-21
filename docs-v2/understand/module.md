# What a module is

By the end of this page, you can read the `iam` module of the starter and say what it owns, how
`analytics` reaches it, what the two share, and where each of them runs.

A module is an autonomous area of your application, with a boundary of its own: a business domain
(payments, messaging), or a supporting domain the others rely on (identity, notifications). It is built
like a microservice: it owns its code, its data and its contracts, and it can be deployed on its own. The package
adds what makes that boundary real: while a module's code runs, it runs in a context of its own.

## What it owns

`iam` owns the users, the tokens and the permissions. Everything that serves them is in its
folder, laid out like a Laravel application:

```
apps/Iam/
├── app/
│   ├── Actions/RegisterUser.php        use cases
│   ├── Models/User.php                 its models
│   ├── Http/Controllers/Api/AuthController.php
│   ├── Rpc/FindUserHandler.php         the messages it answers
│   ├── Events/UserRegistered.php       the events it announces
│   ├── Console/SyncPermissions.php     its commands
│   └── Providers/IamServiceProvider.php
├── config/database.php                 its connections
├── database/migrations/                its tables
├── routes/api.php                      served under /iam/api/…
└── tests/Feature/
```

Its routes, jobs, commands, listeners, migrations and tests belong to it. The package finds them
from the folder, so there is nothing to register. [Where things go](/build/layout).

## Its own context

`iam` declares its connections in `apps/Iam/config/database.php`, and `default` names the one its
code runs on:

```php
return [
    'default' => 'iam',
    'connections' => [
        'iam' => ['driver' => 'sqlite', 'database' => env('IAM_DATABASE', database_path('iam.sqlite'))],
    ],
];
```

`analytics` and `notifications` have their own file with their own `default`. A model of `iam`
names no connection, and still reads `iam`'s:

```php
// in iam: iam's connection. In analytics, the same line reads analytics' connection.
User::query()->find($id);
```

The connection is the first thing a module sets. Its configuration (`config/cache.php`, for
example) and its services (cache, mailer, disks) follow the same rule: a module's own values apply
for as long as its code runs, and the application's come back after.
[A module's settings](/build/settings), [a module's services](/build/services).

## How another module reaches it

`analytics` never imports `Apps\Iam\…`. It has three ways in.

A call, to read one record now. The message is shared; `iam` answers it. `RpcPrincipals` is the
class that asks, for a module that keeps no copy of the users:

```php
// foundation/Iam/Auth/RpcPrincipals.php, run by a module that keeps no copy of the users
$user = $this->iam->findUser((int) $identity->id);
```

```php
// apps/Iam/app/Providers/IamServiceProvider.php
protected array $rpcHandlers = [
    FindUser::class => FindUserHandler::class,
];
```

An event, to learn that something happened. `iam` announces it; `analytics` handles it:

```php
// apps/Analytics/app/Providers/AnalyticsServiceProvider.php
protected array $streamHandlers = [
    IamEvent::UserRegistered->value => [RecordSignup::class],
];
```

A copy, to list and join on `iam`'s users next to its own rows:

```php
// the same file
protected array $shadows = [UserShadow::class];
```

[Which one to use](/talk/choosing).

## What they share

`analytics` can name the message `FindUser` and the client `IamClient` because they are not in
`apps/Iam`: they are in the foundation, the code every module may load.

```
foundation/Iam/
├── Rpc/FindUser.php                    the message, with the rules that validate it
├── Services/IamClient.php              how any module calls iam
├── Data/UserData.php                   what the answer holds
├── Events/IamEvent.php                 the names of the events iam announces
├── Events/UserRegisteredPayload.php    the payload of one of them
└── Shadows/UserShadow.php              the shape of the copy others keep of iam's users
```

`iam` owns these files, and every other module reads them. Nothing else is shared.
[What modules share](/talk/foundation).

## Where it runs

The starter declares its three modules in `config/distributable.php`, and `iam` has a `host`:

```php
'declared' => [
    'iam' => ['host' => env('IAM_HOST')],
    'analytics' => [],
    'notifications' => [],
],
'runs' => env('RUN_MODULES', '*'),
```

`RUN_MODULES` says which of them a process boots. With the default, one process runs the three,
and `IamClient::findUser()` is a method call. In `docker-compose.mesh.yml`, `iam` has a container with
`RUN_MODULES: iam`, `analytics` another with `RUN_MODULES: analytics` and `IAM_HOST` pointing to
`iam`: the same `findUser()` goes over the network.

```
RUN_MODULES=*                          RUN_MODULES=iam         RUN_MODULES=analytics
one process                            one process             one process
iam, analytics, notifications          iam                     analytics
findUser() is a method call            ◄──── findUser() goes over the network ─────
events always go through the stream
```

The code of the modules is the same in both. [Where modules run](/run/where).

## Next

[Why the package exists](/understand/why), or [try the starter](/start/starter).
