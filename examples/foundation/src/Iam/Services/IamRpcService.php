<?php

declare(strict_types=1);

namespace Foundation\Iam\Services;

use Foundation\Iam\Contracts\IamService;
use Microservices\Services\Rpc\RpcService;

final class IamRpcService extends RpcService implements IamService
{
    public function findUserByToken(string $token): ?array
    {
        $raw = $this->call('findUserByToken', ['token' => $token]);

        return is_array($raw) ? ['id' => (int) $raw['id'], 'name' => (string) $raw['name']] : null;
    }
}
