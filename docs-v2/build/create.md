# Create a module

By the end of this page, you can create a module, generate code in it, run a command in it and
remove it.

```bash
php artisan modules:make payments
```

This creates the module in `apps/Payments` and declares it in `config/distributable.php`:

```
apps/Payments/
├── app/
│   ├── Models/
│   └── Providers/PaymentsServiceProvider.php
├── bootstrap/providers.php
├── config/database.php
├── database/migrations/
├── routes/api.php
└── tests/Feature/
```

Everything in it is plain Laravel, laid out like an application. Put a model in `app/Models`, a
route in `routes/api.php`, a migration in `database/migrations`: the package finds them from the
folder, so there is nothing to register. What each folder holds is in
[Where things go](/build/layout).

## Generate code in a module

Laravel's own `make:*` commands take `--module`. They write in the module, under its namespace,
exactly what they write in `app/` without the option:

```bash
# apps/Payments/app/Models/Payment.php, its migration and its factory
php artisan make:model Payment -mf --module=payments
php artisan make:controller PaymentController --module=payments
# a new column on a table of the module
php artisan make:migration add_refunded_at_to_payments --module=payments
```

- `make:model`, `controller`, `request`, `resource`, `job`, `event`, `listener`, `policy`, `rule`,
  `command`, `mail`, `notification`, `enum`, `class`… and the generators other packages add
  (`make:data`) write in `{module}/app/…`.
- `make:migration`, and `-m` on `make:model`, write in `{module}/database/migrations`.
- `make:factory` and `make:seeder` write in `{module}/database/factories` and `database/seeders`.
- `make:config` writes in `{module}/config`.
- `make:view`, `make:component`, and `--markdown` on `make:mail` and `make:notification`, write in
  `{module}/resources/views`. The class that renders the view names it with the module:
  `view('payments::components.alert')`.
- `make:test`, and `--test` on the other generators, write in `{module}/tests/Feature` or
  `tests/Unit`.
- `make:provider` has no `--module`: write the provider in `{module}/app/Providers` and list it in
  `{module}/bootstrap/providers.php`.

A model's factory is found without any code: `Payment::factory()` resolves
`Apps\Payments\Database\Factories\PaymentFactory`, and that factory resolves its model. Laravel's
`#[UseFactory]` on a model still wins, and so does a resolver of your own; yours can fall back on
`Distributable\Context\ModuleFactories::factoryName()` and `modelName()`.

## Run a command in a module

`--module` is on every Artisan command, and names the module it runs in. Repeat it, or write `*`
for every module the process runs, and the command runs once in each:

```bash
# Payment::count() reads payments' connection
php artisan tinker --module=payments
php artisan model:show Payment --module=payments
php artisan db:seed --module=payments
php artisan migrate --module=payments
php artisan migrate --module='*'
```

- `migrate` and its variants migrate the connection of each module named only.
- `db:seed` runs the application's `Database\Seeders\DatabaseSeeder`, then
  `Apps\Payments\Database\Seeders\DatabaseSeeder`, as `migrate` runs the application's migrations,
  then the module's. `--class=PaymentSeeder` names the module's seeder, or the application's when
  the module has none of that name.
- `model:prune` and `model:show` find the models in the module's `app/Models`, so `model:show
  Payment` needs no namespace.
- Any other command, yours or a package's, runs in the module's context.

Without the option, in a process that runs several modules, a command that isn't a module's own
runs in no module: `model:show` on a module's model then throws `ModuleException`, since no connection is set for it.
`migrate --seed` and `migrate:fresh --seed` seed each module the same way; a module without a
`DatabaseSeeder` of its own gets the application's.

## Let your IDE find the classes

The package autoloads the modules itself: the application needs no `composer.json` entry for them.
Your IDE reads `composer.json` without booting Laravel. To let it resolve the classes, map the
foundation in the root file, and each module in its own `composer.json`, merged by
`wikimedia/composer-merge-plugin`:

```json
// composer.json
"autoload": { "psr-4": { "Foundation\\": "foundation/" } },
"extra": { "merge-plugin": { "include": ["apps/*/composer.json"] } }
```

```json
// apps/Payments/composer.json
{
    "autoload": { "psr-4": {
        "Apps\\Payments\\": "app/",
        "Apps\\Payments\\Database\\Factories\\": "database/factories/",
        "Apps\\Payments\\Database\\Seeders\\": "database/seeders/"
    } },
    "autoload-dev": { "psr-4": { "Apps\\Payments\\Tests\\": "tests/" } }
}
```

The paths are relative to the module's folder: they follow it when it moves, and leave with it when
it is deleted.

## Remove a module

```bash
# deletes its folders and its declaration; its database is left as it is
php artisan modules:delete payments
```

## Next

[Where things go in a module](/build/layout).
