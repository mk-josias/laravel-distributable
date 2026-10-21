# Où vont les fichiers

À la fin de cette page, vous savez quel dossier d’un module contient quoi, comment ses routes sont
préfixées, et où il déclare ses écouteurs, ses handlers et ses tâches planifiées.

Un module est organisé comme une application Laravel, et le paquet lit tout à partir du dossier et de
son espace de noms : il n’y a rien à enregistrer.

## Les dossiers

- `app/` : son code, sous `Apps\{Nom}\` (`Apps\Iam` pour `iam`). Ses commandes Artisan sont toutes les
  `Illuminate\Console\Command` sous `app/`, et `app/Providers` contient ses providers, listés dans le
  `bootstrap/providers.php` propre au module.
- `config/*.php` : ses réglages. `config/database.php` contient ses connexions, voir
  [Les réglages d’un module](/fr/build/settings).
- `routes/{surface}.php` : ses routes. `routes/api.php` d’`iam` est servie sous `/iam/api/…`.
- `lang/` : ses traductions, comme `__('iam::messages.hello')`.
- `resources/views/` : ses vues, comme `view('iam::welcome')` ; pendant que le module s’exécute,
  `view('welcome')` cherche là d’abord, puis dans le `resources/views` de l’application. Les
  composants anonymes de
  `resources/views/components` et les composants à classe de `app/View/Components` sont
  `<x-iam::alert />`.
- `database/migrations/`, `database/factories/` (sous `Apps\{Nom}\Database\Factories\`) et
  `database/seeders/` (sous `Apps\{Nom}\Database\Seeders\`).
- `tests/` : ses tests, sous `Apps\{Nom}\Tests\`.

Ce qu’il partage avec les autres modules n’est pas dans son dossier, mais dans la
[fondation](/fr/talk/foundation) : ses messages et son client, les charges utiles de ses événements, la
forme de ses copies.

## Où vivent les modules

`modules.path` (`apps`) et `modules.namespace` (`Apps`) dans `config/distributable.php` disent où
sont les modules. Le nom d’un module est son dossier en snake_case : `apps/PointOfSale` est
`point_of_sale`. Un module qui vit ailleurs déclare son propre `path` et son propre `namespace`, voir
[Où s’exécutent les modules](/fr/run/where#ce-que-declared-peut-dire-d-un-module). `foundation.path`
(`foundation`) est l’endroit où `modules:doctor` et le test d’architecture cherchent la fondation.

## Les routes d’un module

`routes/{nom}.php` est chargée sous `distributable.routes.prefix`, `{module}/{name}` par défaut
(`apps/Iam/routes/api.php` devient `/iam/api/…`), dans le groupe de middleware `{nom}` si
l’application en définit un. Un module règle son propre préfixe avec `declared.{module}.prefix` :

```php
'iam' => ['prefix' => 'identity/{name}']   // /identity/api/…
```

Une passerelle qui route par chemin doit utiliser les mêmes préfixes : le paquet ne les lui dit pas.

## Ce que déclare son provider

Son provider, qui étend `Distributable\Providers\ServiceProvider`, déclare ce que le module écoute,
sert, copie et planifie :

```php
final class IamServiceProvider extends ServiceProvider
{
    // événements Laravel que le module écoute, comme dans EventServiceProvider::$listen
    protected array $listen = [
        \Illuminate\Auth\Events\Login::class => [\Apps\Iam\Listeners\RecordLogin::class],
    ];

    // événements du flux que le module traite : nom de l'événement => classes Handler
    protected array $streamHandlers = [
        'billing.invoice.paid' => [\Apps\Iam\Handlers\UpdatePlan::class],
    ];

    // messages auxquels le module répond : message => classe Handler
    protected array $rpcHandlers = [
        \Foundation\Iam\Rpc\FindUser::class => \Apps\Iam\Rpc\FindUserHandler::class,
    ];

    // copies de lignes d'autres modules qu'il garde
    protected array $shadows = [\Foundation\Iam\Shadows\UserShadow::class];

    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('iam:prune')->daily();
    }
}
```

- `$listen` : un écouteur s’exécute dans le module, quel que soit le module qui a émis l’événement. Un
  écouteur en file continue de fonctionner comme Laravel le met en file, et un écouteur après
  validation s’exécute une fois la transaction validée, toujours dans le module.
- `$streamHandlers` : [les événements des autres modules](/fr/talk/events#le-traiter-dans-un-autre-module),
  fusionnés seulement là où le module s’exécute.
- `$rpcHandlers` : [les messages auxquels le module répond](/fr/talk/calls#y-repondre).
- `$shadows` : [les copies qu’il garde](/fr/talk/copies#declarer-la-copie).
- `schedule()` : une tâche planifiée s’exécute dans le module, closures comprises, et seul un
  processus qui exécute le module la planifie.

La découverte d’événements de Laravel ne voit pas les `app/Listeners` d’un module : elle nomme
`apps/Iam/app/Listeners/X.php` `Apps\Iam\app\Listeners\X`. Déclarez les écouteurs dans `$listen`.
Dans un test, `Event::assertListening()` ne voit qu’une closure pour eux : utilisez
`$this->assertListeningInModule(Login::class, RecordLogin::class)` de `ModuleAware`.

## Ensuite

[Les réglages d’un module](/fr/build/settings).
