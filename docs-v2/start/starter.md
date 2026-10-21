# Try the starter

By the end of this page, you have an application of three modules running on your machine, you
have registered a user, and you have seen an event travel from one module to the others.

The starter is a Laravel application set up with laravel-distributable and ready for production:
three modules (identity, notifications, analytics), authentication through a gateway, permissions,
Docker images for one container or one per module, and tests of each module alone. Start from it,
then replace its modules with yours.

## What it shows

- **Three modules, each with its own context.** `iam` owns the users, `analytics` records signups
  and computes datasets, `notifications` keeps a personal inbox. [Build a module](/build/create).
- **The three ways modules reach each other.** every module asks `iam` for a user's permissions
  through a message; `iam` announces `iam.user.registered`, which `analytics` and `notifications` handle; both
  keep a read-only copy of the users. [Make modules talk](/talk/choosing).
- **Authentication at the gateway.** Only Traefik checks the client's token; the modules read the
  identity it signs. [Authentication](/starter/authentication).
- **Permissions kept by `iam`,** asked through a message and cached.
  [Permissions](/starter/permissions).
- **Docker in two shapes:** every module in one container, or one container per module, with the
  same code. [Docker](/starter/docker).
- **Tests per module,** each running its module alone. [Tests](/starter/tests).

## Install and run it

```bash
composer create-project mk-josias/laravel-distributable-starter my-app
cd my-app
php artisan serve
```

Requires PHP 8.4+, and Redis for the event stream. `create-project` asks two questions, through
`php artisan skeleton:setup`:

- **Which example modules do you keep?** All by default. It can remove `analytics` or
  `notifications`: its folders, its declaration, its tests, its `auth.permissions` entry and its
  Docker service. `iam` always stays: authentication, permissions and the copies of the users rely
  on it.
- **How does the gateway validate a token?** `signature` by default. It sets
  `AUTH_TOKEN_VALIDATION_STRATEGY` in `.env` and a random `AUTH_GATEWAY_SECRET`. See
  [Authentication](/starter/authentication).

Without a terminal (`--no-interaction`), it keeps everything and `signature`. It runs once, then
deletes itself and its line in `composer.json`, so it can never remove a module that has become
yours.

`composer create-project` runs every step but `serve`. To redo them by hand:

```bash
cp .env.example .env
php artisan key:generate
# the RSA pair iam signs tokens with, in storage/jwt-*.key
php artisan auth:jwt-keys
# migrates each module's database, on the `owner` connection each module declares
php artisan migrate --module='*' --database=owner
# the permissions listed in auth.permissions
php artisan iam:sync-permissions --prune
```

The modules use sqlite files by default (`database/iam.sqlite`, `database/analytics.sqlite`,
`database/notifications.sqlite`); `migrate` creates them.

## Register a user, and watch the event

Register a user in `iam`:

```bash
curl -X POST http://127.0.0.1:8000/iam/api/v1/register \
    -H 'Accept: application/json' -H 'Content-Type: application/json' \
    -d '{"name":"<name>","email":"<email>","password":"<password, 8 characters or more>"}'
```

```json
{"success":true,"data":{"id":1,"name":"<name>"}}
```

`iam` announces the registration with an event. Start the consumer; it serves `analytics` and
`notifications`, each with its own cursor:

```bash
php artisan stream:consume
```

`analytics` now has a signup and `notifications` a welcome message, and each keeps its own copy of
the user.

Log in to get a token. It lasts an hour (`AUTH_TOKEN_TTL`); one session per device (`X-Device-Id`,
or else the user agent), which a new login there replaces:

```bash
curl -X POST http://127.0.0.1:8000/iam/api/v1/login \
    -H 'Accept: application/json' -H 'Content-Type: application/json' \
    -d '{"email":"<email>","password":"<password>"}'
```

```json
{"success":true,"data":{"token":"<token>","expires_at":1790003600}}
```

`register` accepts 6 requests a minute from one address. `login` answers a wrong password and an
unknown email with the same 401, and 429 after 5 failures for one email from one address, for a
minute; a successful login clears them.

A module reads only the `X-Identity` a gateway sends, never the token. Without a gateway in front,
sign it yourself with the gateway's secret, then send it:

```bash
# the X-Identity of user 1, valid for a minute
php artisan tinker --execute='echo Foundation\Iam\Auth\TrustedIdentity::sign(
    ["sub" => 1], time() + 60, config("auth.gateway.secret"));'
curl http://127.0.0.1:8000/analytics/api/v1/metrics \
    -H 'Accept: application/json' -H 'X-Identity: <X-Identity>'
curl http://127.0.0.1:8000/notifications/api/v1/notifications \
    -H 'Accept: application/json' -H 'X-Identity: <X-Identity>'
```

The `X-Identity` lasts a minute. The [Docker](/starter/docker) setups put Traefik in front, which
does it on every request: there, send the token itself.

`php artisan analytics:compute` builds the hourly datasets (the scheduler runs it every hour), and
`GET /analytics/api/v1/metrics?group=day` reads them. `analytics_datasets` is partitioned by month
on PostgreSQL, MySQL and MariaDB: `php artisan analytics:prune --keep=12` drops the expired months
whole. On SQLite it is a plain table, and pruning deletes the rows.

## Read it

- **How the modules reach each other:** [Call, event or copy?](/talk/choosing).
- **Where each file is:** [Where the starter's files are](/starter/map).
- **Run it as containers:** [Docker](/starter/docker), then [Deploying](/run/deploying).
- **Add a module or a library:** [Adding code](/starter/adding-code).

## Next

[Build a module of your own](/build/create).
