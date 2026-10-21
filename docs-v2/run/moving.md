# Moving a module out

By the end of this page, a module that sat with others runs in an application of its own, and the
others still call it.

Moving a module changes where its code lives and tells the others where to find it. What it does
doesn't change: the messages it answers, the events it emits and the copies of its rows stay in the
foundation, and nothing that calls it is rewritten. See [Where modules run](/run/where) for the two layouts.

## What moves

| In the application of several modules | In the application of its own |
|---|---|
| `apps/Iam/app/`, under `Apps\Iam\` | `app/`, under `App\` |
| `apps/Iam/config/` | `config/` |
| `apps/Iam/routes/` | `routes/`, loaded by `bootstrap/app.php` |
| `apps/Iam/database/` | `database/` |
| `apps/Iam/tests/` | `tests/` |
| `apps/Iam/composer.json` | the `require` of the application's `composer.json` |

The only line of code that changes is the namespace: `Apps\Iam\` becomes `App\`. The new
application requires the foundation, the package and the drivers the module uses.

## What the new application declares

```php
// config/distributable.php
'declared' => [
    'iam' => ['path' => '.', 'namespace' => 'App'],
    // each module it calls: where it answers
    'analytics' => ['host' => env('ANALYTICS_HOST')],
],

'runs' => env('RUN_MODULES', 'iam'),
```

Its `config/` and `routes/` are the application's own, see [Where a module's code lives](/run/where#where-a-module-s-code-lives).

## What the others change

Each application that keeps calling the module drops its code and declares where it answers:

```bash
# deletes apps/Iam and its declaration; its data is left as it is
php artisan modules:delete iam --force
```

```php
// config/distributable.php, in the applications that call iam
'iam' => ['host' => env('IAM_HOST')],
```

All the applications share the event stream and the same `DISTRIBUTABLE_RPC_SECRET`, see
[Where modules run](/run/where#what-an-application-needs-to-call-a-module-that-runs-elsewhere).
Run `modules:doctor` in each one: it reports a module that serves messages without a host.

## Next

[Deploy the processes](/run/deploying).
