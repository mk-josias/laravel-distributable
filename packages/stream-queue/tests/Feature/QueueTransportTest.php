<?php

declare(strict_types=1);

use Distributable\Contracts\Stream\Transport;
use Distributable\Data\Envelope;
use Distributable\Stream\Queue\QueueTransport;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

beforeEach(function () {
    config()->set('queue.connections.events', ['driver' => 'database', 'connection' => 'testing', 'table' => 'jobs', 'queue' => 'default', 'retry_after' => 90]);
    config()->set('distributable.events.streams.default', ['driver' => 'queue', 'connection' => 'events']);

    Schema::create('jobs', function (Blueprint $table): void {
        $table->id();
        $table->string('queue')->index();
        $table->longText('payload');
        $table->unsignedTinyInteger('attempts');
        $table->unsignedInteger('reserved_at')->nullable();
        $table->unsignedInteger('available_at');
        $table->unsignedInteger('created_at');
    });
});

function paid(int $order): Envelope
{
    return new Envelope((string) Str::uuid7(), 'billing', 'billing.invoice.paid', ['order_id' => $order], [], now()->toImmutable());
}

it('is the transport of a stream whose driver is queue', function () {
    expect(app(Transport::class))->toBeInstanceOf(QueueTransport::class);
});

it('copies an envelope to one queue per declared service', function () {
    app(Transport::class)->publish(paid(1));

    expect(DB::table('jobs')->orderBy('queue')->pluck('queue')->all())
        ->toBe(['distributable-default-billing', 'distributable-default-orders']);
});

it('hands the consumer its own copy, then deletes it', function () {
    $transport = app(Transport::class);
    $transport->publish(paid(1));
    $received = [];

    $transport->consume(['orders'], ['billing'], function (Envelope $envelope) use (&$received, $transport): void {
        $received[] = $envelope->payload;
        $transport->stop();
    });

    expect($received)->toBe([['order_id' => 1]])
        ->and(DB::table('jobs')->pluck('queue')->all())->toBe(['distributable-default-billing']);
});

it('serves several consumers from one loop, each from its own queue', function () {
    $transport = app(Transport::class);
    $transport->publish(paid(1));
    $received = [];

    $transport->consume(['orders', 'billing'], ['billing'], function (Envelope $envelope, string $consumer) use (&$received, $transport): void {
        $received[] = $consumer;

        if (count($received) === 2) {
            $transport->stop();
        }
    });

    expect($received)->toBe(['orders', 'billing'])
        ->and(DB::table('jobs')->count())->toBe(0);
});
