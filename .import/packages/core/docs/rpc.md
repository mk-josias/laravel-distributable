# Calls between services (RPC)

RPC is for asking another service for an answer right away: reading the data it owns, or having it
perform an action whose result the caller needs. An action done over RPC is not part of the
caller's transaction: if the caller fails afterwards, the action stays done. When the caller doesn't
need the result, announce an event instead.

## The pieces

| Piece | Lives | Role |
|---|---|---|
| the contract | in the foundation, the package every service requires | the methods the service answers |
| the `RpcService` | next to the contract, in the namespace declared for the service | what callers are given: it carries each call |
| the implementation | in the answering service | the code that runs, on its own data |

```php
// config/microservices.php, in orders
'services' => ['billing' => ['host' => 'https://billing.internal', 'namespace' => 'Foundation\Billing']],

// app/Providers/ServicesProvider.php, in orders: the contracts it calls
protected array $rpc = [\Foundation\Billing\Contracts\BillingService::class => \Foundation\Billing\Services\BillingRpcService::class];

// app/Providers/ServicesProvider.php, in billing: the contracts it answers
protected array $services = [\Foundation\Billing\Contracts\BillingService::class => \App\Services\BillingService::class];
```

Both providers extend `Microservices\Providers\RpcServiceProvider`. The `RpcService` finds the
service it calls from its namespace: `Foundation\Billing\Services\BillingRpcService` is under
`Foundation\Billing`, the namespace of `billing`.

## The path of a call

```
orders ─► BillingService (the contract) ─► BillingRpcService::call('invoiceFor', ['orderId' => 7])
                                                       │
                             Rpc\TransportManager: the transport of billing's host (http, or yours)
                                                       ▼
                             POST {host}/billing/rpc/invoiceFor {contract, arguments}, signed
                                                       ▼
billing ─► the `rpc` group checks the signature ─► LocalServices runs the implementation in $services
```

`Microservices\Services\Rpc\LocalServices` runs the method and returns the answer as JSON decodes
it. Only a method declared on a contract of `$services` can be called.

`Contracts\Rpc\Transport` is what an `RpcService` is given, bound to `Services\Rpc\TransportManager`. A
package that runs several services in one process binds its own, to call `LocalServices` directly
when the service runs here: laravel-distributable-modules does, so a call between two modules of one process is
a plain method call.

## The called side

The `http` driver comes with its own package, [`mk-josias/laravel-microservices-rpc-http`](https://github.com/mk-josias/laravel-microservices/tree/main/packages/rpc-http).
It serves `POST {service}/rpc/{method}` for each service this process runs, in the `rpc`
middleware group. `rpc-http.path` and `rpc-http.middleware_group` change both,
for instance to `internal/{service}/{method}`: the caller reads the same path, so every service
must set the same one. It rejects a request that is unsigned, too old, modified or replayed: each call is
signed with an HMAC of the timestamp, a nonce, the path, the body and the propagated context, and
each nonce is accepted once, using the cache.

| Status | Means |
|---|---|
| 200 | the return value, as JSON |
| 404 | the method returned `null`; the caller reads `null` |
| 400 | the service answers no such contract or method |
| 403 | the signature is missing, stale, wrong or already used |

Keep the RPC path off the public entry point: a gateway forwards client requests to the services'
routes, never to their RPC routes.

The caller and the called service must share `microservices.rpc.secret`. It defaults to `APP_KEY`;
when the services have their own keys, set the same `MICROSERVICES_RPC_SECRET` on both sides,
otherwise every call is rejected with a 403.

## Caching answers

Reference data, read often and changed rarely (a user, a product), is what to cache: a long
lifetime, and the owner forgets the answer when it writes it. Leave uncached what must be exact on
every call, such as a token that can be revoked. Data to join, filter or sort on locally belongs in
a [copy](shadows.md), not in this cache.

The owner writes these methods, in the `RpcService` it ships in the foundation; a caller only calls
the contract's method.

```php
// foundation/Iam/Services/IamRpcService.php, written by iam
public function findUser(int $id): ?array
{
    return $this->readThrough("iam:user:{$id}", self::DEFAULT_TTL,
        fn (): mixed => $this->call('findUser', ['id' => $id]),
        fn (array $raw): array => ['id' => (int) $raw['id'], 'name' => (string) $raw['name']],
    );
}

// iam calls it when the user changes
public function forgetUser(int $id): void
{
    $this->forget("iam:user:{$id}");
}

// not cached: a revoked token must stop working at once
public function findUserByToken(string $token): ?array
{
    return $this->call('findUserByToken', ['token' => $token]);
}
```

`RpcService` caches answers with a read-through:

| Method | What it does |
|---|---|
| `readThrough($key, $ttl, $fetch, $map, $tags)` | reads the cache; on a miss, calls `$fetch` and keeps its raw answer. `$map` turns the raw answer into what you return, on every read, so a mapping changed by a deploy applies to answers cached before it. An answer `$map` can no longer read is logged, dropped and returned as `null`. |
| `readThroughUntil($key, $fetch, $map, $ttlOf)` | the same, with a lifetime read from the mapped answer, for example a token kept until it expires. A lifetime of zero or less is returned as `null`. |
| `forget($key, $tags)` | drops an answer. The owner calls it when it writes, which is why `DEFAULT_TTL` can be a week. |

The answers live in the store `microservices.rpc.cache` names. The caller keeps an answer and the
owner forgets it, so every service must use the same store, with the same `CACHE_PREFIX` and
`REDIS_PREFIX`. An answer is kept as JSON: on Redis, the key `{REDIS_PREFIX}{CACHE_PREFIX}iam:user:42`
holds `{"id":42,"name":"Ada"}`, so a service in another language reads or deletes it. A tagged
answer is filed by Laravel under its tags, and its key is Laravel's tagged one.

## Your own transport

```php
// config/microservices.php
'services' => ['billing' => ['host' => ['url' => 'grpc://billing.internal', 'transport' => 'grpc']]],
'rpc'      => ['transports' => ['http' => ['driver' => 'http'], 'grpc' => ['driver' => 'grpc', 'port' => 50051]]],

app(\Microservices\Services\Rpc\TransportManager::class)
    ->extend('grpc', fn ($app, array $config, string $name) => new GrpcTransport($config));
```

A transport implements `invoke(string $service, string $contract, string $method, array $arguments)`.
Its called side hands the four values to `LocalServices::call()`, as the HTTP endpoint does.
