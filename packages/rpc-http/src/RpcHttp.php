<?php

declare(strict_types=1);

namespace RpcHttp;

use Illuminate\Contracts\Config\Repository;

/** The microservices.rpc.* settings of calls over HTTP; read live, never snapshotted. */
final readonly class RpcHttp
{
    public function __construct(private Repository $config) {}

    /** Where a service answers a call, the same for caller and called: {service} and {method} are filled in. */
    public function getPath(): string
    {
        return trim((string) $this->config->get('microservices.rpc.path', '{service}/rpc/{method}'), '/');
    }

    /** The middleware group of the called side's routes, which checks the signature. */
    public function getMiddlewareGroup(): string
    {
        return (string) $this->config->get('microservices.rpc.middleware_group', 'rpc');
    }

    public function getSecret(): string
    {
        return (string) $this->config->get('microservices.rpc.secret', '');
    }

    public function getSignatureTtl(): int
    {
        return (int) $this->config->get('microservices.rpc.signature_ttl', 30);
    }
}
