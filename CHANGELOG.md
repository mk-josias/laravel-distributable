# Changelog

Notable changes to `mk-josias/laravel-distributable` and its starter. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and versions follow
[Semantic Versioning](https://semver.org/): before 1.0, a minor version may break.

## [Unreleased]

### Added

- `--module` takes several modules, repeated, or `*` for every module the process runs, on every
  command: the command runs once in each.
- What the application teaches a service in code, a driver given to `extend()` or a validation
  rule, reaches each module's own instance of that service, whenever the application learned it.
- The `in-process` events driver, in its own package
  (`composer require mk-josias/laravel-distributable-stream-in-process`), hands each event to the
  handlers of the modules the process runs, once the emitter's transaction commits, with no broker
  and no `stream:consume`.
- A stream handler that implements `ShouldQueue` runs on the queue, with the options a queued
  Laravel listener takes (`$tries`, `viaQueue()`, `shouldQueue()`…), still in its module and under
  the guard, whatever the driver.

### Changed

- A process that runs one module runs everything in it: in an application that is its own module
  (`'path' => '.'`), or a container started with `RUN_MODULES=iam`, `migrate`, `tinker` and
  `routes/web.php` need no `--module`.
- A closure route runs in the module whose folder holds the file it is written in, as a controller
  runs in the module of its class, `route:cache` included.
- **Breaking:** a module's config file applies only while the module runs. Each value it sets
  replaces the application's, a list or `[]` included, and a key the application doesn't set is no
  longer added for every module. A list a module used to extend item by item now holds only what
  the module's file lists: write the whole list there. The files named in the new `shared` setting
  (`distributable` by default) are still added to the application's.
- **Breaking:** `per_module` is renamed `module_services`, and lists Laravel's services by default
  (`db`, the cache, the queue, the mailer, the disks, the log, Redis, broadcasting, auth and the
  validator): each module gets its own without listing them. Rename the key in your
  `config/distributable.php`, and keep the package's names next to yours, or remove the key.
- **Breaking:** `migrate` and its variants are Laravel's own commands, run in the modules `--module`
  names. Without it they run in no module and throw, as any query there. Replace `php artisan
  migrate` with `php artisan migrate --module='*'`. A test using `RefreshDatabase` passes the module
  through `migrateFreshUsing()`.
- **Breaking:** the `owner` key of a module's `config/database.php` and `Module::ownerConnection()`
  are gone. Name the owner connection `owner` in each module, migrate with `--database=owner`, and
  write `DB::connection('owner')` where you called `ownerConnection()`. A connection a module
  declares exists only while it runs, so every module can use the same name.
- The starter migrates with `migrate --module='*' --database=owner`, and each of its modules declares
  an `owner` connection.

- A failed job is stored on the connection it ran on, and a batch on the connection of its first
  job's module (or of the running module). Both used to fall back to the first module's database
  when the job's class belonged to no module; they now throw instead of storing on another module.
  `queue.failed.database` and `queue.batching.database` are read only from a module's own
  `config/queue.php`, and name a connection that module declares; there is no application database,
  so the starter's `config/queue.php` drops them. The `no_module` connection can now be built, and
  refuses its first query.
- A job of a batch finds its batch in the database of the module that stored it, instead of asking
  the database of each module in turn. Looking a batch up from a command still asks them all.
- The options of the `redis` events driver (`connection`, `block`, `count`, `claim_after`,
  `on_failure`) are in the driver's own `config/stream-redis.php`, published with
  `--tag=stream-redis-config`, as `rpc-http` does. What you set in `events.streams.{name}` still
  wins for that stream, so nothing has to change; `config/distributable.php` keeps only the
  `driver` of the `default` stream.
- **Breaking:** an event says what it is with an attribute, as a message does. `#[Emits(name,
  module, stream)]` replaces `#[Emitter]` and the `name()` and `stream()` methods; `module` is the
  module of the event's class when it is left out. Move the value `name()` returned into
  `#[Emits]`, and delete the method. `Event::version()` goes too.
- **Breaking:** the version of an event is the one of its payload. A payload class carries
  `#[Payload(event, version)]`, and `Versioned` keeps only `upcast()`: move the value `version()`
  returned into `#[Payload]` and delete the method. `FoundationServiceProvider::$payloads` is a list
  of payload classes, no longer a map of event name => class.
- **Breaking:** the two lists of handlers in a module's provider are named alike. Rename `$rpc` to
  `$rpcHandlers` (message => handler) and `$handlers` to `$streamHandlers` (event name => handlers).
- `#[Emits]` and `#[Payload]` take the case of a backed enum as the name, `#[Emits(IamEvent::UserRegistered)]`,
  or a string.
- **Breaking:** a source model names its stream with `#[ShadowStream('shadows')]` instead of
  overriding `shadowStream()`.
- **Breaking:** `Envelope::for()` is gone, and an `Envelope` is built with its `stream`: no class of
  the package assumes the default stream is called `default` any more. Use
  `Services\Stream\EnvelopeFactory::for()`.
- **Breaking:** `db:seed` in a module (`--module`, or `migrate --seed`) runs the application's
  `DatabaseSeeder`, then the module's, as `migrate` runs the application's migrations, then the
  module's. A module without a `DatabaseSeeder` of its own now gets the application's instead of
  being skipped: keep in the application's seeder only what every module needs.
- **Breaking:** `vendor:publish --module=iam` publishes into the module everything a package puts
  under `app/`, `database/`, `config/` or `resources/views`, a config file included; only the
  migrations used to go there.

### Fixed

- The middleware group of a module's routes file runs in the module: a closure route's `auth`,
  session or throttle middleware no longer query outside every module.
- In a module, `view('welcome')` finds the module's `resources/views/welcome.blade.php`, then the
  application's; a view named without a namespace used to be looked for in the application only.

## [0.4.0] - 2026-10-19

### Added

- A call between modules is a message: a `readonly` class of the foundation that implements
  `Contracts\Rpc\Message`, carries `#[Rpc(module, version, name)]` and the `rules()` that validate
  its payload. The module answers it with a class that implements `Contracts\Rpc\Handler`,
  declared in its provider's `$rpc` (message => handler). A handler that does not implement
  `Handler`, or whose `handle()` takes another message, is refused at boot. A new version of a
  message is a new class, and a module serves both while callers move over.
- `modules:doctor` checks the messages of the foundation: one that names an undeclared module, or
  a remote module with no host.
- `rpc:serve` starts the process of a transport that answers calls in one of its own: a transport
  declares it by implementing `Contracts\Rpc\ServesRpc`.
- rpc-http: with `DISTRIBUTABLE_RPC_PORT` set, the RPC answers on that port only, and `rpc:serve`
  starts an Octane server for it. Without it, the RPC answers wherever the application is served.
- Starter: iam writes the users the gateway needs into its own Redis, `gateway-cache`
  (`gateway:user:{id}`, one hour at most), and Traefik reads only that one.
- Starter: `WITH_RPC` runs `rpc:serve`, and the compose files run iam's RPC in a container of its
  own, `iam-rpc`, so a busy API never starves the calls between modules.

### Changed

- **Breaking:** `RpcService` becomes `RpcClient`, and `call('findUser', ['id' => $id])` becomes
  `send(new FindUser($id))`.
- **Breaking:** the RPC route is `POST {module}/rpc/{version}/{message}` with `{"payload": {…}}`,
  where it was `{service}/rpc/{method}`. `DISTRIBUTABLE_RPC_PATH` takes the new placeholders, and a
  caller that is not PHP, such as a gateway, sends the new path and body. A payload the message's
  rules refuse is a 422, read back by the caller as a `ValidationException`.
- **Breaking:** a custom RPC driver implements `Contracts\Rpc\Transport::invoke($module, $name,
  $version, $payload)`.
- **Breaking:** the HTTP transport owns its own settings. `distributable.rpc.secret` becomes
  `rpc-http.secret` (the variable `DISTRIBUTABLE_RPC_SECRET` and the `APP_KEY` fallback do not
  change), and `distributable.rpc.transport` no longer defaults to `http` in the core: `rpc-http`
  declares its transport and takes it as the default. A custom transport can implement
  `Contracts\Rpc\ReportsRpcProblems` to tell `modules:doctor` what stops its calls.
- **Breaking** (starter): `IamService` and `IamRpcService` give way to the messages `FindUser`,
  `FindUserByToken` and `Grants`, answered by handlers in `apps/Iam/app/Rpc/`, and to
  `Foundation\Iam\Services\IamClient`.
- **Breaking** (starter): the gateway asks iam over RPC (`FindUserByToken`) and signs the X-Identity
  itself. `GET /iam/api/v1/identity` is gone; the plugin's `iam` setting becomes `rpc` and
  `rpcSecret`, and Traefik holds the RPC secret (`DISTRIBUTABLE_RPC_SECRET`).
- **Breaking:** a class says what it is with an attribute, not a method or a property. A copy
  carries `#[Shadow(module:, table:, migrations:)]`, where it defined `owner()`, `sourceTable()` and
  `migrations()`; a source model carries `#[ShadowFields('name', 'email')]`, where it set
  `$shadowed`; an event of the foundation carries `#[Emitter(module: 'iam')]`. `Event::emitter()`
  is gone, and `Bus::emit()` takes the emitter as an optional second argument.
- **Breaking:** the package no longer loads or registers the foundation. Map `Foundation\` in
  `composer.json`, and list the foundation's provider in `bootstrap/providers.php` (a foundation
  package lists it in `extra.laravel.providers`). `distributable.foundation.namespace` and
  `declared.{module}.foundation` are gone, and `foundation.path` only says where
  `modules:doctor` and `Boundaries` look. The class of an event in the foundation no longer says its
  emitter: give it `#[Emitter]`.
- `modules:install`, `modules:make` and `modules:delete` write nothing in the foundation.
- **Breaking:** `modules:make` loses `--database`: it always writes `config/database.php`, with the
  module's `default` and `owner` connections, like the rest of an application's structure. It also
  creates `app/Models`, `database/migrations` and `tests/Feature`.
- **Breaking:** the commands say module, not service. `stream:consume`, `stream:skip` and
  `stream:republish` lose `--service` and take the `--module` every command has; `stream:export` and
  `stream:import` filter by `--emitter` where they took `--service`. `ServiceException` is gone
  into `ModuleException`, `Testing\InteractsWithServices` into `ModuleAware`, and
  `ShadowSource::sourceService()` becomes `sourceModule()`.
- **Breaking** (starter): `IamClient::findUser()` and `findUserByToken()` return a `UserData`, where
  they returned an `['id' => …, 'name' => …]` array.
- **Breaking** (starter): every module's API has one shape: a controller in `Http/Controllers/Api/`,
  a request whose `toDto()` feeds an action or a `Repositories/` read. Analytics serves
  `GET /analytics/api/v1/metrics` with `filter[from]` and `filter[to]`, where it served `datasets`;
  `foundation/Common` is ranged by nature.
- **Breaking** (starter): three Docker networks (`public`, `internal`, `gateway`) keep Traefik away
  from Postgres and from the modules' Redis, and the gateway's `redisDatabase` and `cachePrefix`
  settings are gone.

### Removed

- **Breaking:** `LocalServices`, `RpcServices`, `ServiceProvider::$services` and
  `FoundationServiceProvider::$rpc`: the methods a module offered become messages and handlers.
- **Breaking** (starter): `GET /analytics/api/v1/signups` and `users/{id}`.

## [0.3.0] - 2026-10-18

### Added

- `modules.routes.prefix` (`{module}/{name}`) and a module's own `prefix` in `modules.declared`.
- `ShadowModel::migrations()`, abstract: every copy names the files or folders of its migrations.
- `modules:doctor` reports a path a kept copy names that holds no migration.
- `model:prune` and `model:show` find the models of the module `--module` names.
- Starter: Sanctum tokens, one per device; `POST /iam/api/v1/login`, `refresh`, `logout`, `logout-all`.
- Starter: the analytics datasets are partitioned by month on MySQL and MariaDB too.
- Starter: `stream:republish` runs every minute when events are published reliably.

### Changed

- **Breaking:** the packages are `mk-josias/laravel-distributable`, `-rpc-http`, `-stream-redis` and
  `-starter`; the drivers' namespaces become `Distributable\RpcHttp\` and `Distributable\Stream\Redis\`.
- **Breaking:** one provider and one config: `MicroservicesServiceProvider` merges into
  `DistributableServiceProvider`, and the `rpc` and `events` sections of `config/microservices.php`
  move into `config/distributable.php`. `MICROSERVICES_*` variables become `DISTRIBUTABLE_*`, the
  Redis stream `distributable:events`, the copies' events `distributable.shadow.*`, the RPC routes
  `distributable.rpc.{module}`.
- **Breaking:** a module's host is its `host` in `distributable.declared`, its copies its provider's
  `$shadows`; `microservices.name`, `services` and `shadows` are gone.
- **Breaking:** `owner` is a key of a module's `config/database.php`, next to `default`, naming a
  connection; the `owner` sub-key of a connection and the `{connection}:owner` name are gone.
- **Breaking:** the migrations of a copy are those its `migrations()` names, no longer files
  matching `*_{source}_shadow*.php`.
- Starter: only the gateway checks the token (`AUTH_TOKEN_VALIDATION_STRATEGY`: `signature` or
  `issuer`); the modules read the signed `X-Identity`, checked by `Foundation\Iam\Auth\TrustedIdentity`.
- Starter: `POST /iam/api/v1/register` replaces `/users` and returns no token; `AUTH_TOKEN_TTL`
  replaces `AUTH_JWT_TTL`.
- Starter: `Authenticate` moves to `Foundation\Common\Http\Middleware`.
- `modules:install`, `modules:make` and `modules:delete` no longer edit `composer.json`, and
  `modules:doctor` no longer checks it: the package autoloads the modules itself; the entries an
  IDE needs are documented in Modules.

### Removed

- **Breaking:** the `queue` events driver (`laravel-microservices-stream-queue`): on a `database`
  queue, every module's events landed in one module's database.
- **Breaking:** `Contracts\Colocation`, `SingleService`, `ModuleColocation` and `Config\Services`:
  `ModuleRegistry` (`ownerOf()`, `connectionOf()`, `localNames()`) and `ModuleContext` answer
  which module runs and what it owns. `RpcServiceProvider` merges into `FoundationServiceProvider`,
  and `foundation:make` goes: `modules:install` and `modules:make` create the foundation.
- Starter: the `api_token` column of `iam_users`, and `POST /iam/api/v1/tokens`.
- **Breaking:** `Migrations\ShadowMigration`, from laravel-microservices: the migration of a copy
  is a plain migration; a subclass with its own `$table` names its own `migrations()`.
- `modules:doctor` no longer reports a `database` cache, queue or session driver: the application
  chooses its stores.

[Unreleased]: https://github.com/mk-josias/laravel-distributable/compare/v0.4.0...HEAD
[0.4.0]: https://github.com/mk-josias/laravel-distributable/compare/v0.3.0...v0.4.0
[0.3.0]: https://github.com/mk-josias/laravel-distributable/compare/v0.2.0...v0.3.0
