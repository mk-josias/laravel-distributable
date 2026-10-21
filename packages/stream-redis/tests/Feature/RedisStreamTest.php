<?php

declare(strict_types=1);

use Distributable\Contracts\Stream\Transport;
use Distributable\Data\Envelope;
use Distributable\Events\ShadowChanged;
use Distributable\Services\Stream\Dispatcher;
use Distributable\Services\Stream\TransportManager;
use Distributable\Tests\Fixtures\Payments\Handlers\OnReceiptSent;
use Distributable\Tests\Fixtures\Payments\Models\Refund;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

beforeEach(function () {
    try {
        Redis::connection()->ping();
    } catch (Throwable) {
        $this->markTestSkipped('No Redis server reachable.');
    }

    config()->set('distributable.events.streams.default.driver', 'redis');
    config()->set('distributable.events.streams.default.key', 'distributable-test:'.Str::random(8));
    config()->set('distributable.events.streams.default.block', 100);
    config()->set('distributable.events.guard', false);
});

afterEach(function () {
    Redis::connection()->command('del', [(string) config('distributable.events.streams.default.key')]);
});

it('hands this service the entries of the declared emitters, in order, and acknowledges each', function () {
    config()->set('distributable.events.listen', ['messaging.receipt.sent' => [OnReceiptSent::class]]);
    $transport = app(Transport::class);

    foreach ([1, 2] as $payment) {
        $transport->publish(new Envelope((string) Str::uuid7(), 'messaging', 'messaging.receipt.sent', ['payment_id' => $payment], [], now()->toImmutable(), 'default'));
    }
    $transport->publish(new Envelope((string) Str::uuid7(), 'stranger', 'messaging.receipt.sent', ['payment_id' => 3], [], now()->toImmutable(), 'default'));

    $seen = 0;
    $transport->consume(['payments'], ['payments', 'messaging'], function (Envelope $envelope) use ($transport, &$seen): void {
        DB::table('sent_receipts')->insert(['payment_id' => $envelope->payload['payment_id']]);

        if (++$seen === 2) {
            $transport->stop();
        }
    });

    $key = (string) config('distributable.events.streams.default.key');

    expect(DB::table('sent_receipts')->pluck('payment_id')->all())->toBe([1, 2])
        ->and(Redis::connection()->command('xpending', [$key, 'payments'])[0])->toBe(0);
});

it('serves several consumers from one loop, each with its own cursor', function () {
    $transport = app(Transport::class);

    foreach ([1, 2] as $payment) {
        $transport->publish(new Envelope((string) Str::uuid7(), 'messaging', 'messaging.receipt.sent', ['payment_id' => $payment], [], now()->toImmutable(), 'default'));
    }

    $seen = [];
    $handled = 0;
    $transport->consume(['payments', 'notifications'], ['messaging'], function (Envelope $envelope, string $consumer) use ($transport, &$seen, &$handled): void {
        $seen[$consumer][] = $envelope->payload['payment_id'];

        if (++$handled === 4) {
            $transport->stop();
        }
    });

    $key = (string) config('distributable.events.streams.default.key');

    expect($seen)->toBe(['payments' => [1, 2], 'notifications' => [1, 2]])
        ->and(Redis::connection()->command('xpending', [$key, 'payments'])[0])->toBe(0)
        ->and(Redis::connection()->command('xpending', [$key, 'notifications'])[0])->toBe(0);
});

it('drops a failed entry and goes on with the next one when on_failure is skip', function () {
    config()->set('distributable.events.streams.default.on_failure', 'skip');
    $transport = app(Transport::class);

    foreach ([1, 2] as $payment) {
        $transport->publish(new Envelope((string) Str::uuid7(), 'messaging', 'messaging.receipt.sent', ['payment_id' => $payment], [], now()->toImmutable(), 'default'));
    }

    $seen = [];
    $transport->consume(['payments'], ['messaging'], function (Envelope $envelope) use ($transport, &$seen): void {
        $seen[] = $envelope->payload['payment_id'];

        if ($envelope->payload['payment_id'] === 2) {
            $transport->stop();
        }

        if ($envelope->payload['payment_id'] === 1) {
            throw new RuntimeException('handler down');
        }
    });

    $key = (string) config('distributable.events.streams.default.key');

    expect($seen)->toBe([1, 2])
        ->and(Redis::connection()->command('xpending', [$key, 'payments'])[0])->toBe(0);
});

it('keeps a failed entry pending when on_failure is block', function () {
    $transport = app(Transport::class);
    $transport->publish(new Envelope((string) Str::uuid7(), 'messaging', 'messaging.receipt.sent', ['payment_id' => 1], [], now()->toImmutable(), 'default'));

    $transport->consume(['payments'], ['messaging'], function () use ($transport): void {
        $transport->stop();

        throw new RuntimeException('handler down');
    });

    $key = (string) config('distributable.events.streams.default.key');

    expect(Redis::connection()->command('xpending', [$key, 'payments'])[0])->toBe(1);
});

it('skips the failed entry so the next one is read', function () {
    $transport = app(Transport::class);

    foreach ([1, 2] as $payment) {
        $transport->publish(new Envelope((string) Str::uuid7(), 'messaging', 'messaging.receipt.sent', ['payment_id' => $payment], [], now()->toImmutable(), 'default'));
    }

    $transport->consume(['payments'], ['messaging'], function () use ($transport): void {
        $transport->stop();

        throw new RuntimeException('handler down');
    });

    $skipped = $transport->skipOldest('payments');
    $key = (string) config('distributable.events.streams.default.key');

    $seen = [];
    $transport->consume(['payments'], ['messaging'], function (Envelope $envelope) use ($transport, &$seen): void {
        $seen[] = $envelope->payload['payment_id'];
        $transport->stop();
    });

    expect($skipped->payload['payment_id'])->toBe(1)
        ->and($seen)->toBe([2])
        ->and($transport->skipOldest('payments'))->toBeNull()
        ->and(Redis::connection()->command('xpending', [$key, 'payments'])[0])->toBe(0);
});

it('skips every entry up to a date, read or not, and never moves the cursor back', function () {
    $transport = app(Transport::class);

    foreach ([1, 2, 3] as $payment) {
        $transport->publish(new Envelope((string) Str::uuid7(), 'messaging', 'messaging.receipt.sent', ['payment_id' => $payment], [], now()->toImmutable(), 'default'));
    }

    $transport->consume(['payments'], ['messaging'], function () use ($transport): void {
        $transport->stop();

        throw new RuntimeException('handler down');
    });

    expect($transport->skipUntil('payments', now()->subDay()->toImmutable()))->toBe(0)
        ->and($transport->skipUntil('payments', now()->addMinute()->toImmutable()))->toBe(3)
        ->and($transport->skipUntil('payments', now()->addMinute()->toImmutable()))->toBe(0);

    $seen = 0;
    usleep(2000);
    $transport->publish(new Envelope((string) Str::uuid7(), 'messaging', 'messaging.receipt.sent', ['payment_id' => 4], [], now()->toImmutable(), 'default'));
    $transport->consume(['payments'], ['messaging'], function () use ($transport, &$seen): void {
        $seen++;
        $transport->stop();
    });

    expect($seen)->toBe(1);
});

it('gives its entries back for an archive, and below the acknowledged floor on request', function () {
    $transport = app(Transport::class);

    foreach ([1, 2, 3] as $payment) {
        $transport->publish(new Envelope((string) Str::uuid7(), 'messaging', 'messaging.receipt.sent', ['payment_id' => $payment], [], now()->toImmutable(), 'default'));
    }

    expect(collect($transport->entries(null, null, true))->all())->toBe([]);

    $handled = 0;
    $transport->consume(['payments'], ['messaging'], function () use ($transport, &$handled): void {
        if (++$handled === 3) {
            $transport->stop();
        }
    });

    $ids = fn (iterable $entries): array => collect($entries)->map(fn (Envelope $envelope): int => $envelope->payload['payment_id'])->all();

    expect($ids($transport->entries(null, null, false)))->toBe([1, 2, 3])
        ->and($ids($transport->entries(null, null, true)))->toBe([1, 2])
        ->and($ids($transport->entries(now()->addHour()->toImmutable(), null, false)))->toBe([]);
});

it('deletes the consumption marks of an entry once it is acknowledged when guard_cleanup is acknowledge', function () {
    config()->set('distributable.events.guard', true);
    config()->set('distributable.events.guard_cleanup', 'acknowledge');
    config()->set('distributable.events.listen', ['messaging.receipt.sent' => [OnReceiptSent::class]]);
    $transport = app(Transport::class);
    $transport->onAcknowledged(app(Dispatcher::class)->forget(...));
    $transport->publish(new Envelope((string) Str::uuid7(), 'messaging', 'messaging.receipt.sent', ['payment_id' => 1], [], now()->toImmutable(), 'default'));

    $transport->consume(['payments'], ['messaging'], function (Envelope $envelope, string $consumer) use ($transport): void {
        app(Dispatcher::class)->dispatch($envelope, $consumer);
        expect(DB::table('event_consumptions')->count())->toBe(1);
        $transport->stop();
    });

    expect(DB::table('sent_receipts')->count())->toBe(1)
        ->and(DB::table('event_consumptions')->count())->toBe(0);
});

it('leaves the consumption marks to stream:prune-consumptions when guard_cleanup is prune', function () {
    config()->set('distributable.events.guard', true);
    config()->set('distributable.events.listen', ['messaging.receipt.sent' => [OnReceiptSent::class]]);
    $transport = app(Transport::class);
    $transport->publish(new Envelope((string) Str::uuid7(), 'messaging', 'messaging.receipt.sent', ['payment_id' => 1], [], now()->toImmutable(), 'default'));

    $transport->consume(['payments'], ['messaging'], function (Envelope $envelope, string $consumer) use ($transport): void {
        app(Dispatcher::class)->dispatch($envelope, $consumer);
        $transport->stop();
    });

    expect(DB::table('event_consumptions')->count())->toBe(1);
});

it('announces a source row on the Redis stream its model names, and nothing on the default one', function () {
    $key = 'distributable-test:'.Str::random(8);
    config()->set('distributable.events.streams.shadows', ['driver' => 'redis', 'key' => $key, 'block' => 100]);

    $refund = Refund::query()->create(['amount' => 30]);

    $announced = collect(app(TransportManager::class)->stream('shadows')->entries(null, null, false))->all();
    Redis::connection()->command('del', [$key]);

    expect($announced)->toHaveCount(1)
        ->and($announced[0]->name)->toBe(ShadowChanged::NAME)
        ->and($announced[0]->payload)->toBe(['source' => 'refunds', 'key' => $refund->id, 'attributes' => ['amount' => 30]])
        ->and(collect(app(Transport::class)->entries(null, null, false))->all())->toBe([]);
});
