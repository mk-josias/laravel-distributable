<?php

declare(strict_types=1);

namespace Foundation\Iam\Services;

use Distributable\Services\Rpc\RpcService;
use Foundation\Iam\Contracts\IamService;

final class IamRpcService extends RpcService implements IamService
{
    public function findUserByToken(string $token): ?array
    {
        $raw = $this->call('findUserByToken', ['token' => $token]);

        return is_array($raw) ? ['id' => (int) $raw['id'], 'name' => (string) $raw['name']] : null;
    }
}
