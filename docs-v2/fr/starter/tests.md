# Tests

À la fin de cette page, chaque module du starter est testé seul, et l’ensemble est testé ensemble.

```bash
php artisan test
```

`tests/TestCase.php` donne à chaque module un fichier sqlite vide et lance `migrate --module=*` avant
chaque test.

Un module est testé seul, comme il s’exécute une fois qu’il a son propre processus. Ses tests vivent dans
`apps/{Module}/tests/` et étendent `Tests\ModuleTestCase`, qui n’exécute que ce module (`RUN_MODULES`) et
tient la place d’iam :

```php
class InboxTest extends ModuleTestCase
{
    protected string $module = 'notifications';

    public function test_a_registered_user_finds_a_welcome(): void
    {
        // sa copie, et un jeton
        $headers = $this->user(1, 'ada');
        // comme iam l'annoncerait
        $this->receive('notifications', IamEvent::UserRegistered->value, ['id' => 1]);

        $this->getJson('/notifications/api/v1/notifications', $headers)->assertJsonCount(1, 'data');
    }
}
```

- **`user($id, $name, $permissions)` :** écrit la copie de l’utilisateur sur la connexion du module, fait
  répondre iam `$permissions` à `grants()`, et renvoie l’en-tête `Authorization` d’un jeton valide
- **`receive($module, $name, $payload)` :** remet au module un événement tel que son émetteur l’aurait
  annoncé (fourni par le paquet)

Comme les tests d’un module ne nomment aucune classe d’un autre module, `Boundaries` tient aussi pour eux.

- **unitaire**
  - Où : `tests/Unit`, `apps/{Module}/tests/Unit`
  - Suite : `Unit`
- **composant**
  - Où : `apps/{Module}/tests/Feature`
  - Suite : `Modules`
- **intégration**
  - Où : `tests/Integration`
  - Suite : `Integration`
- **de bout en bout**
  - Où : `tests/e2e/together-or-apart.sh`
  - Suite : aucune

- **Unitaire** : une classe seule, sans base de données, HTTP ni conteneur.
- **Composant** : un module seul, par ses routes et les événements qu’il reçoit.
- **Intégration** : tous les modules dans un processus, par leurs routes. Un fichier par sujet : les
  connexions restent séparées, les événements, l’authentification et les messages RPC traversent les
  modules. `ArchitectureTest` vérifie qu’aucun module n’utilise les classes d’un autre
  (`Distributable\Testing\Boundaries`) et que `modules:doctor` passe.
- **De bout en bout** : le même parcours utilisateur contre les montages Docker, à travers Traefik.

`php artisan test --testsuite=Unit` lance les tests rapides seuls.

`php artisan make:test PreferenceTest --module=notifications` écrit un test dans le module.

Un test atteint un module par ses routes et ses messages RPC, pas par ses tables. `inModuleOf` sert au
rare test qui n’a aucune route à prendre : il entre dans le module de la classe, exécute la fonction,
puis en sort :

```php
$user = $this->inModuleOf(User::class, fn () => User::query()->create([...]));
```

`composer check` lance Pint, PHPStan et les tests.
