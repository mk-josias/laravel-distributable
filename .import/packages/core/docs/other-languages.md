# Services in other languages

A service written in another language (a Node gateway, a Go worker) can take part: it emits and
consumes events on the stream, and calls the Laravel services over RPC. The package doesn't ship a
client for it; this is what that client has to speak.

Declare it in `microservices.services` like any other service, with its `host`. A consumer
acknowledges without reading any entry whose `emitter` isn't a declared service.

Everything that leaves a process is JSON: the envelope on the stream and in the queues, the body
and the answer of an RPC call, and the cached answers. Nothing is serialized in a PHP format.

| The service wants to | It does |
|---|---|
| announce something | [writes an envelope to the stream](#events-on-redis) |
| react to another service's events | [reads the stream](#events-on-redis) with its own consumer group |
| ask a service something | [calls its RPC route](#rpc-over-http), signed |
| be asked by a service | [answers that same route](#answering-a-service) |
| own data the others keep a copy of | [announces each change](#being-the-source-of-a-copy) |
| read or forget a cached answer | [reads or deletes its key](#cached-answers) |

## Events on Redis

| | |
|---|---|
| Stream | the stream's `key`, `microservices:events` for the `default` stream, behind the prefix of the Redis connection: with the starter's `REDIS_PREFIX=database:`, the key Redis holds is `database:microservices:events`. Set `REDIS_PREFIX=` to have the bare key |
| Entry | `XADD {key} * envelope {json}`: one field, `envelope`, holding [the envelope](events.md#the-envelope) as JSON |
| `emitter` | the service's name in `microservices.services` |
| `id` | a UUID, unique per event: the consumption guard keys on it |
| Consuming | one consumer group per consuming service, named after it, created at `0`; acknowledge with `XACK` once handled |

## RPC over HTTP

```
POST {host}/{service}/rpc/{method}
Content-Type: application/json
X-Rpc-Timestamp: 1790000000            unix seconds, within rpc-http.signature_ttl (30 s) of the server's clock
X-Rpc-Nonce:     <a fresh UUID>        accepted once
X-Rpc-Context:   {"trace_id":"…"}      the propagated Context keys, as JSON; {} when none
X-Rpc-Signature: hex(hmac_sha256(secret, timestamp + "\n" + nonce + "\n" + path + "\n" + body + "\n" + context))

{"contract": "Foundation\\Billing\\Contracts\\BillingService", "arguments": {"orderId": 42}}
```

`path` is `/{service}/rpc/{method}`, with its leading slash, or what `rpc-http.path` sets
(`MICROSERVICES_RPC_PATH`) when the services changed it. `body` and `context` are signed as sent,
byte for byte. `arguments` are named after the method's parameters. `secret` is
`microservices.rpc.secret`. The answer is the method's return value as JSON:

| Status | Means |
|---|---|
| 200 | the return value, as JSON |
| 404 | the method returned `null` |
| 400 | the service serves no such contract or method: `{"message": "…"}` |
| 403 | the signature is missing, stale, wrong or already used |

## Cached answers

An `RpcService` keeps an answer under its key, behind the prefix of the Redis connection
(`REDIS_PREFIX`) and the one of the cache (`CACHE_PREFIX`), as JSON, in the database of Laravel's
`cache` Redis connection (`REDIS_CACHE_DB`, 1 by default, where the stream is in 0):

```
GET database:cache:iam:user:42   →   {"id":42,"name":"Ada"}
DEL database:cache:iam:user:42       when the service owns the user and it changes
```

with `REDIS_PREFIX=database:` and `CACHE_PREFIX=cache:`, the starter's defaults. Laravel's own are
`{app name}-database-` and `{app name}-cache-`: every service then needs the same `APP_NAME`, or
the same two prefixes set.

The key after the prefixes is the one the owner's `RpcService` passes to `readThrough()`.

## Answering a service

A Laravel service calls it exactly as it calls another Laravel service: through a contract and its
`RpcService`, with the service's `host` in `microservices.services`. The service receives the request
above on `POST /{name}/rpc/{method}`, checks the signature, and answers the return value as JSON, or
a 404 for `null`. Any other status is an error for the caller.

To call it over something else than this HTTP format (gRPC, an existing REST API), give its host a
transport of your own: see [RPC](rpc.md#your-own-transport).

## Being the source of a copy

A Laravel service can keep a [copy](shadows.md) of rows the service owns, with the service as the
copy's `owner()`. The service then announces each change with a `microservices.shadow.changed`
event:

```json
{
  "id": "0191f3c2-8a41-7c2e-9b55-3f1c7d0a4e88",
  "emitter": "identity",
  "name": "microservices.shadow.changed",
  "payload": { "source": "users", "key": 42, "attributes": { "name": "Ada" } },
  "headers": {},
  "emitted_at": "2026-09-30T13:22:41.512000Z",
  "recipients": [],
  "stream": "default",
  "version": 1
}
```

`source` is the `sourceTable()` of the copy, `key` the row's id (an integer or a string),
`attributes` the columns of the copy. A deleted row is announced with a `deleted_at` among the
attributes. Each service that keeps a copy writes it in its own database when it consumes the event.
