# Tester un module

À la fin de cette page, le test d’un module s’exécute dans le contexte de ce module, lui remet un
événement comme son émetteur l’aurait annoncé, et échoue quand une frontière est franchie.

## Lancer les tests du module

Les tests d’un module étendent votre `Tests\TestCase`. PHPUnit les lance dès que `phpunit.xml` les
liste :

```xml
<testsuite name="Modules">
    <directory>apps/*/tests</directory>
</testsuite>
```

Gardez dans un module les tests qui n’ont besoin que de ce module, et dans `tests/` ceux qui
traversent des modules. `php artisan make:test PaymentTest --module=payments` en écrit un dans le
module.

Un test du dossier d’un module s’exécute dans le contexte de ce module. Un test de `tests/` entre dans
un module par `ModuleAware` :

```php
use Distributable\Testing\ModuleAware;

// la connexion d'iam
$user = $this->inModule('iam', fn () => User::query()->create(['name' => 'ada']));
```

`$this->artisan('iam:sync')` lance la commande dans le contexte de son module, comme le fait la
vraie console.

## Migrer les bases

`migrate` s’exécute dans les modules que nomme `--module`, donc le test case les nomme :

```php
// tests/TestCase.php, dans setUp()
$this->artisan('migrate --module=*');
```

`RefreshDatabase` lance `migrate:fresh` avant que le test entre dans son module : donnez-lui le
module par `migrateFreshUsing()`.

```php
use RefreshDatabase {
    migrateFreshUsing as private laravelMigrateFreshUsing;
}

protected function migrateFreshUsing(): array
{
    return [...$this->laravelMigrateFreshUsing(), '--module' => ['payments']];
}
```

## Remettre un événement à un module

```php
// les handlers d'analytics reçoivent l'événement, sans flux et sans iam
$this->receive('analytics', 'iam.user.registered', ['id' => 42]);
```

`receive()`, de `ModuleAware`, remet l’événement aux handlers du module comme si son émetteur l’avait
annoncé.

## Vérifier un écouteur

`Event::assertListening()` ne voit qu’une closure pour un écouteur qu’un module déclare dans
`$listen`. Utilisez l’assertion qui connaît les modules :

```php
$this->assertListeningInModule(Login::class, RecordLogin::class);
```

## Faire échouer les tests quand une frontière est franchie

```php
// tests/Architecture/BoundariesTest.php
it('keeps the modules apart', function () {
    expect(app(\Distributable\Testing\Boundaries::class)->violations())->toBe([]);
});
```

Voir [Les frontières](/fr/understand/boundaries#dans-vos-tests) pour ce qu’il signale.

## La façon du starter

Le starter ajoute un `ModuleTestCase` qui lance un module seul et tient la place d’`iam`, plus des
niveaux d’intégration et de bout en bout : voir [les tests du starter](/fr/starter/tests).

## Ensuite

[Faire parler les modules](/fr/talk/choosing).
