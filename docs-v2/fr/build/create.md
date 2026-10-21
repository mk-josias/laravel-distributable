# Créer un module

À la fin de cette page, vous savez créer un module, générer du code dedans, lancer une commande dedans
et le supprimer.

```bash
php artisan modules:make payments
```

Cela crée le module dans `apps/Payments` et le déclare dans `config/distributable.php` :

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

Tout dedans est du Laravel ordinaire, organisé comme une application. Mettez un modèle dans
`app/Models`, une route dans `routes/api.php`, une migration dans `database/migrations` : le paquet
les trouve à partir du dossier, donc il n’y a rien à enregistrer. Ce que contient chaque dossier est
dans [Où vont les fichiers](/fr/build/layout).

## Générer du code dans un module

Les commandes `make:*` de Laravel acceptent `--module`. Elles écrivent dans le module, sous son
espace de noms, exactement ce qu’elles écrivent dans `app/` sans l’option :

```bash
# apps/Payments/app/Models/Payment.php, sa migration et sa factory
php artisan make:model Payment -mf --module=payments
php artisan make:controller PaymentController --module=payments
# une nouvelle colonne sur une table du module
php artisan make:migration add_refunded_at_to_payments --module=payments
```

- `make:model`, `controller`, `request`, `resource`, `job`, `event`, `listener`, `policy`, `rule`,
  `command`, `mail`, `notification`, `enum`, `class`… et les générateurs qu’ajoutent d’autres paquets
  (`make:data`) écrivent dans `{module}/app/…`.
- `make:migration`, et `-m` sur `make:model`, écrivent dans `{module}/database/migrations`.
- `make:factory` et `make:seeder` écrivent dans `{module}/database/factories` et
  `database/seeders`.
- `make:config` écrit dans `{module}/config`.
- `make:view`, `make:component`, et `--markdown` sur `make:mail` et `make:notification`, écrivent
  dans `{module}/resources/views`. La classe qui rend la vue la nomme avec le module :
  `view('payments::components.alert')`.
- `make:test`, et `--test` sur les autres générateurs, écrivent dans `{module}/tests/Feature` ou
  `tests/Unit`.
- `make:provider` n’a pas de `--module` : écrivez le provider dans `{module}/app/Providers` et
  listez-le dans `{module}/bootstrap/providers.php`.

La factory d’un modèle est trouvée sans aucun code : `Payment::factory()` résout
`Apps\Payments\Database\Factories\PaymentFactory`, et cette factory résout son modèle. Le
`#[UseFactory]` de Laravel sur un modèle l’emporte toujours, tout comme un résolveur à vous ; le
vôtre peut se rabattre sur `Distributable\Context\ModuleFactories::factoryName()` et `modelName()`.

## Lancer une commande dans un module

`--module` est sur chaque commande Artisan, et nomme le module dans lequel elle s’exécute.
Répétez-la, ou écrivez `*` pour chaque module que le processus exécute, et la commande s’exécute une
fois dans chacun :

```bash
# Payment::count() lit la connexion de payments
php artisan tinker --module=payments
php artisan model:show Payment --module=payments
php artisan db:seed --module=payments
php artisan migrate --module=payments
php artisan migrate --module='*'
```

- `migrate` et ses variantes migrent seulement la connexion de chaque module nommé.
- `db:seed` lance le `Database\Seeders\DatabaseSeeder` de l’application, puis
  `Apps\Payments\Database\Seeders\DatabaseSeeder`, comme `migrate` joue les migrations de
  l’application, puis celles du module. `--class=PaymentSeeder` nomme le seeder du module, ou celui
  de l’application quand le module n’en a aucun de ce nom.
- `model:prune` et `model:show` trouvent les modèles dans le `app/Models` du module, donc
  `model:show Payment` n’a pas besoin d’espace de noms.
- Toute autre commande, la vôtre ou celle d’un paquet, s’exécute dans le contexte du module.

Sans l’option, une commande qui n’est pas celle d’un module s’exécute dans aucun module :
`model:show` sur un modèle de module lève alors `ModuleException`, puisqu’aucune connexion n’est
réglée pour lui. `migrate --seed` et `migrate:fresh --seed` alimentent chaque module de la même
façon ; un module sans `DatabaseSeeder` à lui reçoit celui de l’application.

## Laisser votre IDE trouver les classes

Le paquet charge lui-même les modules : l’application n’a besoin d’aucune entrée `composer.json`
pour eux. Votre IDE lit `composer.json` sans démarrer Laravel. Pour qu’il résolve les classes,
faites correspondre la fondation dans le fichier racine, et chaque module dans son propre
`composer.json`, fusionné par `wikimedia/composer-merge-plugin` :

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

Les chemins sont relatifs au dossier du module : ils le suivent quand il déménage, et partent avec
lui quand il est supprimé.

## Supprimer un module

```bash
# supprime ses dossiers et sa déclaration ; ses données restent telles quelles
php artisan modules:delete payments
```

## Ensuite

[Où vont les fichiers d’un module](/fr/build/layout).
