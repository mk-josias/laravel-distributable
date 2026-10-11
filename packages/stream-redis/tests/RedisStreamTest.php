<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Microservices\Contracts\Stream\Transport;
use Microservices\Data\Envelope;
use Microservices\Tests\Fixtures\App\Handlers\OnInvoicePaid;

beforeEach(function () {
    try {
        Redis::connection()->ping();
    } catch (Throwable) {
        $this->markTestSkipped('No Redis server reachable.');
    }

    config()->set('microservices.events.streams.default.driver', 'redis');
    config()->set('microservices.events.streams.default.key', 'microservices-test:'.Str::random(8));
    config()->set('microservices.events.streams.default.block', 100);
    config()->set('microservices.events.guard', false);
});

afterEach(function () {
    Redis::connection()->command('del', [(string) config('microservices.events.streams.default.key')]);
});

it('hands this service the entries of the declared emitters, in order, and acknowledges each', function () {
    config()->set('microservices.events.listen', ['billing.invoice.paid' => [OnInvoicePaid::class]]);
    $transport = app(Transport::class);

    foreach ([1, 2] as $order) {
        $transport->publish(new Envelope((string) Str::uuid7(), 'billing', 'billing.invoice.paid', ['order_id' => $order], [], now()->toImmutable()));
    }
    $transport->publish(new Envelope((string) Str::uuid7(), 'stranger', 'billing.invoice.paid', ['order_id' => 3], [], now()->toImmutable()));

    $seen = 0;
    $transport->consume('orders', ['orders', 'billing'], function (Envelope $envelope) use ($transport, &$seen): void {
        DB::table('paid_orders')->insert(['order_id' => $envelope->payload['order_id']]);

        if (++$seen === 2) {
            $transport->stop();
        }
    });

    $key = (string) config('microservices.events.streams.default.key');

    expect(DB::table('paid_orders')->pluck('order_id')->all())->toBe([1, 2])
        ->and(Redis::connection()->command('xpending', [$key, 'orders'])[0])->toBe(0);
});
