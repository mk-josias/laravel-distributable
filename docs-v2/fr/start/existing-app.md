# Une application existante

À la fin de cette page, votre application Laravel a un module `iam`, déclaré et vérifié, qui annonce
un événement qu’un autre module traite.

`app/` reste votre application Laravel. Chaque module vit dans `apps/`. Pour partir d’une application
qui fonctionne déjà, [essayez le starter](/fr/start/starter).

```
config/distributable.php                   déclare les modules : 'declared' => ['iam' => [], …]

apps/Iam/
├── app/                              Apps\Iam\, organisé comme une application Laravel
│   ├── Providers/IamServiceProvider.php   les événements qu'il traite, dans $streamHandlers
│   ├── Models/User.php
│   └── Events/UserRegistered.php
├── config/
│   └── database.php                  ses connexions : `modules:make` l'écrit toujours
├── database/migrations/
└── routes/api.php                    servies sous /iam/api/…

foundation/
├── FoundationServiceProvider.php     ce que la fondation déclare pour chaque module
└── Iam/                              Foundation\Iam\, ce qu'iam partage avec les autres modules
    ├── Rpc/FindUser.php              un message auquel iam répond
    ├── Services/IamClient.php        comment un autre processus appelle iam
    ├── Shadows/UserShadow.php
    └── database/shadows/users/       la migration de la copie, nommée par le #[Shadow] d'UserShadow
```

Le paquet charge les modules, pas la fondation : faites correspondre `Foundation\` dans
`composer.json` et listez `Foundation\FoundationServiceProvider` dans `bootstrap/providers.php`.

## Déclarer les modules

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
// apps/Iam/app/Models/User.php : de l'Eloquent ordinaire, il utilise la connexion d'iam parce qu'il s'exécute dans iam
final class User extends \Illuminate\Database\Eloquent\Model {}
```

## Donner à un module ses connexions

```php
// apps/Iam/config/database.php, par-dessus config/database.php tant qu'iam s'exécute
return [
    // la connexion sur laquelle s'exécute le code du module
    'default' => 'iam',
    'connections' => [
        // lit et écrit
        'iam' => ['driver' => 'pgsql', 'database' => 'iam', 'username' => 'iam_app'],
        // crée et modifie les tables ; chaque module peut nommer la sienne `owner`
        'owner' => ['driver' => 'pgsql', 'database' => 'iam', 'username' => 'iam_owner'],
    ],
];
```

```bash
# migre chaque module sur sa propre connexion `owner`, voir Migrations
php artisan migrate --module='*' --database=owner
```

## Annoncer un événement, et le traiter dans un autre module

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
        // enregistrer l'inscription
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

Cet événement est en version 1. Quand la forme d’une charge utile change, sa classe de charge utile
déclare une nouvelle version et comment lire les anciennes, pour que les événements déjà dans le flux
restent lisibles : voir
[Change the shape of a payload](/fr/talk/events#changer-la-forme-d-une-charge-utile).

## Vérifier que chaque module pourrait s’exécuter séparément

```bash
php artisan modules:doctor
```

```
Boundary crossed: apps/Analytics/app/Models/Report.php: Apps\Iam\Models\User
[iam] runs elsewhere and serves RPC messages, but distributable.declared.iam.host is not set.
```

Il signale un module qui importe la classe d’un autre module ou nomme ses tables, un module non
déclaré, un hôte ou un secret RPC manquant, et sort avec un code non nul s’il trouve quelque chose. La
liste complète est dans [Les frontières](/fr/understand/boundaries#avant-un-deploiement).

## Ensuite

[Créer un module](/fr/build/create), puis [Faire parler les modules](/fr/talk/choosing).
