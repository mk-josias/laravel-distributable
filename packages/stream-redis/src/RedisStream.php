<?php

declare(strict_types=1);

namespace Distributable\Stream\Redis;

use Illuminate\Contracts\Config\Repository;

/** The distributable.events.streams.{name}.* settings of a stream on the `redis` driver; read live, never snapshotted. */
final readonly class RedisStream
{
    public function __construct(private Repository $config, public string $name) {}

    public function getConnection(): string
    {
        return (string) ($this->config->get("distributable.events.streams.{$this->name}.connection") ?? 'default');
    }

    /** The one Redis stream every module writes this stream's events to. */
    public function getKey(): string
    {
        return (string) $this->config->get("distributable.events.streams.{$this->name}.key", "distributable:{$this->name}");
    }

    public function getBlockMs(): int
    {
        return (int) $this->config->get("distributable.events.streams.{$this->name}.block", 5_000);
    }

    public function getCount(): int
    {
        return (int) $this->config->get("distributable.events.streams.{$this->name}.count", 50);
    }

    public function getClaimAfterMs(): int
    {
        return (int) $this->config->get("distributable.events.streams.{$this->name}.claim_after", 60_000);
    }

    /** `block` (default): a failed entry is retried before any later one. `skip`: later entries go on, the failed one is acknowledged and dropped. */
    public function blocksOnFailure(): bool
    {
        return $this->config->get("distributable.events.streams.{$this->name}.on_failure", 'block') !== 'skip';
    }
}
