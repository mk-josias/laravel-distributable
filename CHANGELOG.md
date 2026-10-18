# Changelog

Notable changes to `mk-josias/laravel-distributable` and its starter. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and versions follow
[Semantic Versioning](https://semver.org/): before 1.0, a minor version may break.

## [Unreleased]

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

[Unreleased]: https://github.com/mk-josias/laravel-distributable-modules/compare/v0.2.0...HEAD
