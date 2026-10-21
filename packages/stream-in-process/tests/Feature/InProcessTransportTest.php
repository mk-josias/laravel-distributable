<?php

declare(strict_types=1);

use Apps\Iam\Events\UserRegistered;
use Distributable\Contracts\Stream\Bus;
use Distributable\Tests\Doubles\Recorder;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    Config::set('distributable.events.streams.default.driver', 'in-process');
    $this->app->singleton(Recorder::class);
});

it('hands an emitted event to the handlers of the modules this process runs, in their module, without a consumer', function () {
    $this->app->make(Bus::class)->emit(new UserRegistered(5, 'lamp'));

    expect($this->app->make(Recorder::class)->records)->toBe([['iam.user.registered', ['id' => 5, 'name' => 'lamp']]])
        ->and($this->app->make(Recorder::class)->connections)->toBe(['iam']);
});

it('hands it over once the emitter\'s transaction has committed', function () {
    $this->inModule('iam', function (): void {
        DB::transaction(function (): void {
            $this->app->make(Bus::class)->emit(new UserRegistered(5, 'lamp'));

            expect($this->app->make(Recorder::class)->records)->toBe([]);
        });
    });

    expect($this->app->make(Recorder::class)->records)->toHaveCount(1);
});
