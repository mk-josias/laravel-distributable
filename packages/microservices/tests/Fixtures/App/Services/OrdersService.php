<?php

declare(strict_types=1);

namespace Distributable\Tests\Microservices\Fixtures\App\Services;

use Distributable\Tests\Microservices\Fixtures\App\Models\Order;
use Illuminate\Support\Facades\Context;

final class OrdersService implements \Distributable\Tests\Microservices\Fixtures\App\Contracts\OrdersService
{
    public function find(int $id): ?array
    {
        $order = Order::query()->find($id);

        return $order === null ? null : ['id' => $order->id, 'total' => $order->total];
    }

    public function traceId(): ?string
    {
        $trace = Context::get('trace_id');

        return is_string($trace) ? $trace : null;
    }
}
