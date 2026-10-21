<?php

declare(strict_types=1);

namespace Distributable\Stream\Redis;

use Illuminate\Contracts\Config\Repository;

/**
 * The settings of a stream on the `redis` driver, read live, never snapshotted: what the stream
 * sets in distributable.events.streams.{name}, else the defaults of config/stream-redis.php.
 */
final readonly class RedisStream
{
    public function __construct(private Repository $config, public string $name) {}

    public function getConnection(): string
    {
        return (string) ($this->setting('connection') ?? 'default');
    }

    /** The one Redis stream every module writes this stream's events to: `distributable:events` for the default stream, `distributable:{name}` for another. */
    public function getKey(): string
    {
        $default = $this->name === 'default' ? 'distributable:events' : "distributable:{$this->name}";

        return (string) ($this->config->get("distributable.events.streams.{$this->name}.key") ?? $default);
    }

    public function getBlockMs(): int
    {
        return (int) $this->setting('block');
    }

    public function getCount(): int
    {
        return (int) $this->setting('count');
    }

    public function getClaimAfterMs(): int
    {
        return (int) $this->setting('claim_after');
    }

    /** `block`: a failed entry is retried before any later one. `skip`: later entries go on, the failed one is acknowledged and dropped. */
    public function blocksOnFailure(): bool
    {
        return $this->setting('on_failure') !== 'skip';
    }

    private function setting(string $name): mixed
    {
        return $this->config->get("distributable.events.streams.{$this->name}.{$name}")
            ?? $this->config->get("stream-redis.{$name}");
    }
}
