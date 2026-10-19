# Changelog

Notable changes to `mk-josias/laravel-distributable` and its starter. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and versions follow
[Semantic Versioning](https://semver.org/): before 1.0, a minor version may break.

## [Unreleased]

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
  declares it by implementing `Contracts\Rpc\Serves`.
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
- **Breaking** (starter): `IamService` and `IamRpcService` give way to the messages `FindUser`,
  `FindUserByToken` and `Grants`, answered by handlers in `apps/Iam/app/Rpc/`, and to
  `Foundation\Iam\Services\IamClient`.
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

[Unreleased]: https://github.com/mk-josias/laravel-distributable/compare/v0.3.0...HEAD
[0.3.0]: https://github.com/mk-josias/laravel-distributable/compare/v0.2.0...v0.3.0
