# Read-only copies (shadows)

When a service needs another service's rows locally, for example to join, filter or sort on them,
it can keep a copy in its own database, kept up to date by events. Only the source service writes
the data.

```php
// billing: the source; the $shadowed fields are copied
final class Customer extends Model implements \Distributable\Contracts\Shadows\Shadowed
{
    use \Distributable\Traits\ShadowSource;

    protected array $shadowed = ['name'];
}

// foundation: the copy as billing describes it, for every service that keeps one
class CustomerShadow extends \Distributable\Models\ShadowModel
{
    public static function owner(): string { return 'billing'; }

    public static function sourceTable(): string { return 'customers'; }
}

// orders, config/microservices.php: the copies it keeps, in its own database
'shadows' => [\Foundation\Billing\Shadows\CustomerShadow::class],
```

A keeper lists the foundation's class as it is, or a subclass of its own that adds relations,
casts or a `beforeSync()`. Two forms of one source need two tables: the second sets `$table` and
brings its own migration. A copy is a model like any other: it reads the database of the service
running, so it is read inside that service.

The keeper creates the copy's table with a migration extending
`Distributable\Migrations\ShadowMigration`. `shadow()` names the copy by its class, so the source
table is written once, in the copy's `sourceTable()`; `table()` is the name the keeper's copy model
reads: the source's own, or its `$table`. The copy has the source's key as its own, the shadowed
columns and a `deleted_at`:

```php
return new class extends \Distributable\Migrations\ShadowMigration
{
    protected function shadow(): string { return \Foundation\Billing\Shadows\CustomerShadow::class; }

    public function up(): void
    {
        Schema::create($this->table(), function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->string('name');
            $table->softDeletes();
        });
    }
};
```

```
billing: Customer saved / deleted ─► microservices.shadow.changed ─► orders consumer ─► CustomerShadow::sync()
```

A copy rejects any write that doesn't come from `sync()`, and a deleted source row becomes a soft
delete in the copy. Override `beforeSync()` to derive what the copy needs and the source never
announced, and `shouldBeShadowed()` to keep only some rows: a row it returns `false` for is never
written, and leaves the copy if it was there.

```php
// analytics keeps only the administrators
protected static function shouldBeShadowed(array $attributes): bool
{
    return (bool) $attributes['is_admin'];
}
```

An announcement that arrives late, after a newer one, is ignored when the source row and the copy
both carry a date: the latest of `updated_at` and `deleted_at`, to the second. A source without
those columns announces no date, and the copy then keeps whichever announcement arrived last. Such
a copy can be overwritten by an older announcement; `shadows:announce` repairs it.

To fill a copy created after the source already had data:

```bash
# on the keeper: ask the owners to send their rows again
php artisan shadows:want [--keepers=orders] [--sources=customers]
# on the owner: send every row again
php artisan shadows:announce customers [--keepers=orders]
```

Both commands address the event through its `recipients`, so only those services update their
copy.

## On its own stream

Every source row is announced by a `microservices.shadow.changed` event, on the default stream.
A source model can name another stream of `microservices.events.streams` by overriding
`shadowStream()`; its rows, and only its rows, travel on it:

```php
// billing: Customer's rows go on the `shadows` stream, the other events stay on the default one
public function shadowStream(): ?string
{
    return 'shadows';
}
```

```php
// config/microservices.php, in billing and in every service keeping the copy
'events' => ['streams' => [
    'default' => ['driver' => 'redis'],
    'shadows' => ['driver' => 'redis'],
]],
```

```bash
# on the keeper: one consumer per stream
php artisan stream:consume --stream=shadows
```

A keeper has to read that stream, or its copy stays empty. With `events.reliable_publish` on, a row
announced on `shadows` is written to `event_publications` with that stream, then published there.
`shadows:want` and `shadows:announce` need nothing more: the want goes out on the default stream,
the answer on the source's own.

An order is kept only within one stream. A handler that reads a copy an earlier event was meant
to fill (a welcome mail reading the user's copy) needs that copy's source and its own event on
the same stream: on two streams, the event can overtake the copy.

A service in another language can be the source: see
[Services in other languages](other-languages.md#being-the-source-of-a-copy).
