<?php

declare(strict_types=1);

use Distributable\Contracts\Stream\Transport;
use Distributable\Data\Envelope;
use Distributable\Events\ShadowChanged;
use Distributable\Services\Stream\Dispatcher;
use Distributable\Services\Stream\TransportManager;
use Distributable\Tests\Microservices\Fixtures\App\Handlers\OnInvoicePaid;
use Distributable\Tests\Microservices\Fixtures\App\Models\Refund;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

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
    $transport->consume(['orders'], ['orders', 'billing'], function (Envelope $envelope) use ($transport, &$seen): void {
        DB::table('paid_orders')->insert(['order_id' => $envelope->payload['order_id']]);

        if (++$seen === 2) {
            $transport->stop();
        }
    });

    $key = (string) config('microservices.events.streams.default.key');

    expect(DB::table('paid_orders')->pluck('order_id')->all())->toBe([1, 2])
        ->and(Redis::connection()->command('xpending', [$key, 'orders'])[0])->toBe(0);
});

it('serves several consumers from one loop, each with its own cursor', function () {
    $transport = app(Transport::class);

    foreach ([1, 2] as $order) {
        $transport->publish(new Envelope((string) Str::uuid7(), 'billing', 'billing.invoice.paid', ['order_id' => $order], [], now()->toImmutable()));
    }

    $seen = [];
    $handled = 0;
    $transport->consume(['orders', 'shipping'], ['billing'], function (Envelope $envelope, string $consumer) use ($transport, &$seen, &$handled): void {
        $seen[$consumer][] = $envelope->payload['order_id'];

        if (++$handled === 4) {
            $transport->stop();
        }
    });

    $key = (string) config('microservices.events.streams.default.key');

    expect($seen)->toBe(['orders' => [1, 2], 'shipping' => [1, 2]])
        ->and(Redis::connection()->command('xpending', [$key, 'orders'])[0])->toBe(0)
        ->and(Redis::connection()->command('xpending', [$key, 'shipping'])[0])->toBe(0);
});

it('drops a failed entry and goes on with the next one when on_failure is skip', function () {
    config()->set('microservices.events.streams.default.on_failure', 'skip');
    $transport = app(Transport::class);

    foreach ([1, 2] as $order) {
        $transport->publish(new Envelope((string) Str::uuid7(), 'billing', 'billing.invoice.paid', ['order_id' => $order], [], now()->toImmutable()));
    }

    $seen = [];
    $transport->consume(['orders'], ['billing'], function (Envelope $envelope) use ($transport, &$seen): void {
        $seen[] = $envelope->payload['order_id'];

        if ($envelope->payload['order_id'] === 2) {
            $transport->stop();
        }

        if ($envelope->payload['order_id'] === 1) {
            throw new RuntimeException('handler down');
        }
    });

    $key = (string) config('microservices.events.streams.default.key');

    expect($seen)->toBe([1, 2])
        ->and(Redis::connection()->command('xpending', [$key, 'orders'])[0])->toBe(0);
});

it('keeps a failed entry pending when on_failure is block', function () {
    $transport = app(Transport::class);
    $transport->publish(new Envelope((string) Str::uuid7(), 'billing', 'billing.invoice.paid', ['order_id' => 1], [], now()->toImmutable()));

    $transport->consume(['orders'], ['billing'], function () use ($transport): void {
        $transport->stop();

        throw new RuntimeException('handler down');
    });

    $key = (string) config('microservices.events.streams.default.key');

    expect(Redis::connection()->command('xpending', [$key, 'orders'])[0])->toBe(1);
});

it('skips the failed entry so the next one is read', function () {
    $transport = app(Transport::class);

    foreach ([1, 2] as $order) {
        $transport->publish(new Envelope((string) Str::uuid7(), 'billing', 'billing.invoice.paid', ['order_id' => $order], [], now()->toImmutable()));
    }

    $transport->consume(['orders'], ['billing'], function () use ($transport): void {
        $transport->stop();

        throw new RuntimeException('handler down');
    });

    $skipped = $transport->skipOldest('orders');
    $key = (string) config('microservices.events.streams.default.key');

    $seen = [];
    $transport->consume(['orders'], ['billing'], function (Envelope $envelope) use ($transport, &$seen): void {
        $seen[] = $envelope->payload['order_id'];
        $transport->stop();
    });

    expect($skipped->payload['order_id'])->toBe(1)
        ->and($seen)->toBe([2])
        ->and($transport->skipOldest('orders'))->toBeNull()
        ->and(Redis::connection()->command('xpending', [$key, 'orders'])[0])->toBe(0);
});

it('skips every entry up to a date, read or not, and never moves the cursor back', function () {
    $transport = app(Transport::class);

    foreach ([1, 2, 3] as $order) {
        $transport->publish(new Envelope((string) Str::uuid7(), 'billing', 'billing.invoice.paid', ['order_id' => $order], [], now()->toImmutable()));
    }

    $transport->consume(['orders'], ['billing'], function () use ($transport): void {
        $transport->stop();

        throw new RuntimeException('handler down');
    });

    expect($transport->skipUntil('orders', now()->subDay()->toImmutable()))->toBe(0)
        ->and($transport->skipUntil('orders', now()->addMinute()->toImmutable()))->toBe(3)
        ->and($transport->skipUntil('orders', now()->addMinute()->toImmutable()))->toBe(0);

    $seen = 0;
    usleep(2000);
    $transport->publish(new Envelope((string) Str::uuid7(), 'billing', 'billing.invoice.paid', ['order_id' => 4], [], now()->toImmutable()));
    $transport->consume(['orders'], ['billing'], function () use ($transport, &$seen): void {
        $seen++;
        $transport->stop();
    });

    expect($seen)->toBe(1);
});

it('gives its entries back for an archive, and below the acknowledged floor on request', function () {
    $transport = app(Transport::class);

    foreach ([1, 2, 3] as $order) {
        $transport->publish(new Envelope((string) Str::uuid7(), 'billing', 'billing.invoice.paid', ['order_id' => $order], [], now()->toImmutable()));
    }

    expect(collect($transport->entries(null, null, true))->all())->toBe([]);

    $handled = 0;
    $transport->consume(['orders'], ['billing'], function () use ($transport, &$handled): void {
        if (++$handled === 3) {
            $transport->stop();
        }
    });

    $orders = fn (iterable $entries): array => collect($entries)->map(fn (Envelope $envelope): int => $envelope->payload['order_id'])->all();

    expect($orders($transport->entries(null, null, false)))->toBe([1, 2, 3])
        ->and($orders($transport->entries(null, null, true)))->toBe([1, 2])
        ->and($orders($transport->entries(now()->addHour()->toImmutable(), null, false)))->toBe([]);
});

it('deletes the consumption marks of an entry once it is acknowledged when guard_cleanup is acknowledge', function () {
    config()->set('microservices.events.guard', true);
    config()->set('microservices.events.guard_cleanup', 'acknowledge');
    config()->set('microservices.events.listen', ['billing.invoice.paid' => [OnInvoicePaid::class]]);
    $transport = app(Transport::class);
    $transport->onAcknowledged(app(Dispatcher::class)->forget(...));
    $transport->publish(new Envelope((string) Str::uuid7(), 'billing', 'billing.invoice.paid', ['order_id' => 1], [], now()->toImmutable()));

    $transport->consume(['orders'], ['billing'], function (Envelope $envelope, string $consumer) use ($transport): void {
        app(Dispatcher::class)->dispatch($envelope, $consumer);
        expect(DB::table('event_consumptions')->count())->toBe(1);
        $transport->stop();
    });

    expect(DB::table('paid_orders')->count())->toBe(1)
        ->and(DB::table('event_consumptions')->count())->toBe(0);
});

it('leaves the consumption marks to stream:prune-consumptions when guard_cleanup is prune', function () {
    config()->set('microservices.events.guard', true);
    config()->set('microservices.events.listen', ['billing.invoice.paid' => [OnInvoicePaid::class]]);
    $transport = app(Transport::class);
    $transport->publish(new Envelope((string) Str::uuid7(), 'billing', 'billing.invoice.paid', ['order_id' => 1], [], now()->toImmutable()));

    $transport->consume(['orders'], ['billing'], function (Envelope $envelope, string $consumer) use ($transport): void {
        app(Dispatcher::class)->dispatch($envelope, $consumer);
        $transport->stop();
    });

    expect(DB::table('event_consumptions')->count())->toBe(1);
});

it('announces a source row on the Redis stream its model names, and nothing on the default one', function () {
    $key = 'microservices-test:'.Str::random(8);
    config()->set('microservices.events.streams.shadows', ['driver' => 'redis', 'key' => $key, 'block' => 100]);

    $refund = Refund::query()->create(['amount' => 30]);

    $announced = collect(app(TransportManager::class)->stream('shadows')->entries(null, null, false))->all();
    Redis::connection()->command('del', [$key]);

    expect($announced)->toHaveCount(1)
        ->and($announced[0]->name)->toBe(ShadowChanged::NAME)
        ->and($announced[0]->payload)->toBe(['source' => 'refunds', 'key' => $refund->id, 'attributes' => ['amount' => 30]])
        ->and(collect(app(Transport::class)->entries(null, null, false))->all())->toBe([]);
});
