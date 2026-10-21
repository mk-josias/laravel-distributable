# The boundaries

By the end of this page, you know the seven rules the package holds modules to, and the three
checks that tell you when one is broken.

Each module is a microservice from its first line of code, and the package holds it to that, even
while it shares a process with the others. Each rule says what it guarantees, because a rule whose
reason no one sees anymore ends up being dropped.

## The seven rules

1. **Each module is designed as a microservice.** Its routes, jobs, commands, listeners,
   configuration and data belong to it, so it can leave on its own when the day comes. Its
   [provider](/build/layout) loads them from its folder, and it has its own connections
   ([settings](/build/settings)).
2. **A module knows of the others only what they show.** What a module agrees to show (the messages
   it answers, the shape of the data it exchanges, the names of its events) lives in the
   [foundation](/talk/foundation). Nothing a module uses disappears when a neighbour moves to
   another process. The foundation uses no module, and every module may use it.
3. **Ask with a message, announce with an event.** No module writes to another's data. A module
   asks another with a [message](/talk/calls), announces with an [event](/talk/events), and reads
   rows it needs from a [copy](/talk/copies).
4. **A module chooses neither its connections nor its settings.** Its code always runs with its own
   connections, settings and services ([settings](/build/settings),
   [services](/build/services)). Outside any module there is no default connection, so it has
   nothing to divert to reach a neighbour's.
5. **The same code, whatever the topology.** Whether two modules run together or apart, neither the
   caller nor the callee changes. A call to a module the process runs is a direct method call; any
   other goes out on the module's transport. Events travel on the stream in both cases.
6. **Deployment is configuration.** Which modules run in which process is a setting, not a decision
   written into the code: `RUN_MODULES`, and `host` for a module that runs elsewhere.
   [Where modules run](/run/where).
7. **Boundaries are checked before deployment.** What separate applications prevented, a check must
   detect: a forbidden import, or a neighbour's table named in code, fails your tests.

## Checking the boundaries

Three checks report the same violations, at different moments.

### In your tests

`Distributable\Testing\Boundaries` reads the PHP files of every module, of the foundation and of the
application's `app/`, `routes/` and `config/`. It needs no configuration:

```php
// tests/Architecture/BoundariesTest.php
it('keeps the modules apart', function () {
    expect(app(\Distributable\Testing\Boundaries::class)->violations())->toBe([]);
});
```

`violations()` returns one `{file}: {name}` per reference that crosses a boundary:

- a module may never name another module's namespace;
- a module may never name another module's connection, or a table that module's migrations create.
  A name the module gives one of its own connections too, such as `owner`, is its own, as is the
  name of a copy it keeps: both are allowed;
- the foundation and the application may never name a module's namespace, outside the modules' own
  folders.

It reads qualified class names and plain string literals, so a table or connection name assembled at
runtime isn't seen.

### Before a deployment

```bash
php artisan modules:doctor
```

Run it when you deploy, or in CI with the production environment. It lists every problem and exits
with a non-zero code if there is at least one:

- a folder of `apps/` that `declared` doesn't list:
  `[apps/Notifications] is not declared in distributable.declared.`
- a local module without its `bootstrap/providers.php`, or whose provider class doesn't exist;
- two copies kept in one table:
  `[analytics] keeps two copies in table [users]: … Give each its own $table.`
- an RPC message that names an undeclared module;
- a module running elsewhere that serves messages, with no host:
  `[iam] runs elsewhere and serves RPC messages, but distributable.declared.iam.host is not set.`
- RPC messages declared while the secret is empty:
  `rpc-http.secret is empty: set DISTRIBUTABLE_RPC_SECRET or APP_KEY.`
- everything `Boundaries` reports:
  `Boundary crossed: apps/Analytics/app/Models/Report.php: Apps\Iam\Models\User`
- two local modules setting one config key to different values:
  `Modules [analytics, iam] set config [iam.flag] to different values: in one process, the module registered last wins.`

### At runtime

A process loads only the classes of the modules in its `RUN_MODULES`. Using a class of another
module throws a `ModuleException`, even if its file is on disk:

```
[Apps\Iam\Repositories\UserRepository] belongs to module [iam], which this process does not run
(RUN_MODULES): go through its foundation messages or an event.
```

This catches at runtime a call that `Boundaries` would have caught in your tests. A process still
knows the other modules exist, because `config/distributable.php` declares them: it listens to
their events and calls them through the clients of the foundation.

## Nothing invented

None of these rules is new: they are proven microservices patterns, applied to modules and in place
by default, with no ceremony in the business code.

- **Bounded context:** one module is one domain, with its code, its settings and its routes.
- **Database per service, and beyond:** a module reaches only its own connections, settings and
  services.
- **Shared kernel:** the foundation holds what modules share, and nothing more.
- **Transactional outbox** (opt-in): an event is kept in the same transaction as the data until it
  is sent, so it isn't lost when sending fails.
- **Idempotent consumer:** a handled event is marked, and a second delivery does nothing.
- **Event-carried state transfer:** a copy of another module's rows, kept up to date by its events.
- **Event versioning:** each event carries a version, and older ones are converted when read.
- **Ports and adapters:** a call is the same message locally or over the network, and the stream and
  the transport can be swapped.

## Next

[Try the starter](/start/starter), or [start a new application](/start/new-app).
