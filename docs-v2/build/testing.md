# Testing a module

By the end of this page, a test of your module runs in that module's context, hands it an event as
its emitter would have announced it, and fails when a boundary is crossed.

## Run the module's tests

A module's tests extend your `Tests\TestCase`. PHPUnit runs them once `phpunit.xml` lists them:

```xml
<testsuite name="Modules">
    <directory>apps/*/tests</directory>
</testsuite>
```

Keep in a module the tests that only need that module, and in `tests/` the ones that cross modules.
`php artisan make:test PaymentTest --module=payments` writes one in the module.

A test in a module's folder runs in that module's context. A test of `tests/` enters a module
through `ModuleAware`:

```php
use Distributable\Testing\ModuleAware;

// iam's connection
$user = $this->inModule('iam', fn () => User::query()->create(['name' => 'ada']));
```

`$this->artisan('iam:sync')` runs the command in its module's context, as the real console does.

## Migrate the databases

`migrate` runs in the modules `--module` names, so the test case names them:

```php
// tests/TestCase.php, in setUp()
$this->artisan('migrate --module=*');
```

`RefreshDatabase` runs `migrate:fresh` before the test enters its module: give it the module through
`migrateFreshUsing()`.

```php
use RefreshDatabase {
    migrateFreshUsing as private laravelMigrateFreshUsing;
}

protected function migrateFreshUsing(): array
{
    return [...$this->laravelMigrateFreshUsing(), '--module' => ['payments']];
}
```

## Hand a module an event

```php
// analytics' handlers receive the event, with no stream and no iam
$this->receive('analytics', 'iam.user.registered', ['id' => 42]);
```

`receive()`, from `ModuleAware`, hands the event to the module's handlers as if its emitter had
announced it.

## Check a listener

`Event::assertListening()` only sees a closure for a listener a module declares in `$listen`. Use
the assertion that knows modules:

```php
$this->assertListeningInModule(Login::class, RecordLogin::class);
```

## Fail the tests when a boundary is crossed

```php
// tests/Architecture/BoundariesTest.php
it('keeps the modules apart', function () {
    expect(app(\Distributable\Testing\Boundaries::class)->violations())->toBe([]);
});
```

See [The boundaries](/understand/boundaries#in-your-tests) for what it reports.

## The starter's way

The starter adds a `ModuleTestCase` that runs one module alone and stands in for `iam`, plus
integration and end-to-end levels: see [the starter's tests](/starter/tests).

## Next

[Make modules talk](/talk/choosing).
