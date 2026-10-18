<?php

declare(strict_types=1);

namespace Distributable\Stream\Redis;

use Carbon\CarbonImmutable;
use Closure;
use Distributable\Contracts\Stream\ArchivesStreams;
use Distributable\Contracts\Stream\RedeliversEnvelopes;
use Distributable\Contracts\Stream\ReportsAcknowledgements;
use Distributable\Contracts\Stream\SkipsEntries;
use Distributable\Contracts\Stream\Transport;
use Distributable\Contracts\Stream\TrimsStreams;
use Distributable\Data\Envelope;
use Illuminate\Contracts\Redis\Factory as Redis;
use Illuminate\Redis\Connections\Connection;
use Illuminate\Redis\Connections\PhpRedisConnection;
use Throwable;

/**
 * Redis Streams, straight on Illuminate\Redis — no third-party event package, because the wire
 * format has to be ours for transports to stay interchangeable.
 *
 * One Redis stream per configured stream, written by every service, so its entries keep the order
 * they were published in; one consumer group per CONSUMING service: each consumer keeps its own
 * cursor, so plugging a new one disturbs nobody. Delivery is at-least-once — unacknowledged
 * entries come back, which is exactly what the consumption guard is for.
 */
final class RedisStreamTransport implements ArchivesStreams, RedeliversEnvelopes, ReportsAcknowledgements, SkipsEntries, Transport, TrimsStreams
{
    private const string OWN_PENDING = '0';

    private const string NEW_ENTRIES = '>';

    private const int MAX_BACKOFF_SECONDS = 60;

    private const int ARCHIVE_BATCH = 500;

    private bool $listening = true;

    private ?Closure $acknowledged = null;

    public function __construct(
        private readonly Redis $redis,
        private readonly RedisStream $config,
    ) {}

    public function publish(Envelope $envelope): void
    {
        $this->connection()->command('xadd', [$this->config->getKey(), '*', ['envelope' => $envelope->toJson()]]);
    }

    public function onAcknowledged(callable $callback): void
    {
        $this->acknowledged = Closure::fromCallable($callback);
    }

    /** With $acknowledgedOnly, it stops below the floor trim() uses: every group has acknowledged what lies under it. */
    public function entries(?CarbonImmutable $since, ?CarbonImmutable $until, bool $acknowledgedOnly): iterable
    {
        $key = $this->config->getKey();
        $end = $until !== null ? '('.$until->getTimestampMs() : '+';

        if ($acknowledgedOnly) {
            $floor = $this->floor($key);

            if ($floor === null) {
                return;
            }

            if ($end === '+' || self::compareIds($floor, substr($end, 1)) < 0) {
                $end = '('.$floor;
            }
        }

        $from = $since !== null ? (string) $since->getTimestampMs() : '-';

        do {
            /** @var array<string, array<string, string>> $entries */
            $entries = $this->connection()->command('xrange', [$key, $from, $end, self::ARCHIVE_BATCH]) ?: [];

            foreach ($entries as $id => $fields) {
                yield Envelope::fromJson($fields['envelope'] ?? '{}');

                $from = '('.$id;
            }
        } while (count($entries) === self::ARCHIVE_BATCH);
    }

    public function trim(): int
    {
        $key = $this->config->getKey();
        $floor = $this->floor($key);

        if ($floor === null) {
            return 0;
        }

        // phpredis: xTrim(key, threshold, approximate, minid)
        return (int) $this->connection()->command('xtrim', [$key, $floor, false, true]);
    }

    public function skipOldest(string $consumer): ?Envelope
    {
        $key = $this->config->getKey();

        /** @var list<array{0: string}>|false $pending */
        $pending = $this->connection()->command('xpending', [$key, $consumer, '-', '+', 1]);
        $id = is_array($pending) && isset($pending[0][0]) ? (string) $pending[0][0] : null;

        if ($id === null) {
            return null;
        }

        /** @var array<string, array<string, string>> $entry */
        $entry = $this->connection()->command('xrange', [$key, $id, $id]) ?: [];
        $this->connection()->command('xack', [$key, $consumer, [$id]]);

        return Envelope::fromJson($entry[$id]['envelope'] ?? '{}');
    }

    /** The cursor never goes back, which would deliver again what was handled, nor past now, which would swallow what is yet to be published. */
    public function skipUntil(string $consumer, CarbonImmutable $until): int
    {
        $key = $this->config->getKey();
        $end = min($until->getTimestampMs(), CarbonImmutable::now()->getTimestampMs()).'-18446744073709551615';
        $skipped = 0;

        do {
            /** @var list<array{0: string}> $pending */
            $pending = $this->connection()->command('xpending', [$key, $consumer, '-', $end, self::ARCHIVE_BATCH]) ?: [];
            $ids = array_map(static fn (array $row): string => (string) $row[0], $pending);

            if ($ids !== []) {
                $this->connection()->command('xack', [$key, $consumer, $ids]);
                $skipped += count($ids);
            }
        } while (count($ids) === self::ARCHIVE_BATCH);

        $delivered = $this->lastDelivered($key, $consumer);

        if ($delivered === null || self::compareIds($delivered, $end) >= 0) {
            return $skipped;
        }

        $from = '('.$delivered;

        do {
            /** @var array<string, array<string, string>> $entries */
            $entries = $this->connection()->command('xrange', [$key, $from, $end, self::ARCHIVE_BATCH]) ?: [];
            $skipped += count($entries);
            $from = '('.(string) array_key_last($entries);
        } while (count($entries) === self::ARCHIVE_BATCH);

        $this->connection()->command('xgroup', ['SETID', $key, $consumer, $end]);

        return $skipped;
    }

    private function lastDelivered(string $key, string $group): ?string
    {
        try {
            /** @var list<array<string, mixed>>|false $groups */
            $groups = $this->connection()->command('xinfo', ['GROUPS', $key]);
        } catch (Throwable) {
            return null;
        }

        foreach ($groups ?: [] as $info) {
            if ((string) $info['name'] === $group) {
                return (string) $info['last-delivered-id'];
            }
        }

        return null;
    }

    /** Every entry below it is acknowledged by every group; null while no group reads the stream. */
    private function floor(string $key): ?string
    {
        $floor = null;

        try {
            /** @var list<array<string, mixed>>|false $groups */
            $groups = $this->connection()->command('xinfo', ['GROUPS', $key]);
        } catch (Throwable) {
            return null; // no such stream yet
        }

        foreach ($groups ?: [] as $group) {
            /** @var array{0?: int, 1?: string|null}|false $pending */
            $pending = $this->connection()->command('xpending', [$key, (string) $group['name']]);
            $oldest = is_array($pending) && ($pending[0] ?? 0) > 0 ? (string) $pending[1] : (string) $group['last-delivered-id'];

            $floor = $floor === null || self::compareIds($oldest, $floor) < 0 ? $oldest : $floor;
        }

        return $floor;
    }

    private static function compareIds(string $a, string $b): int
    {
        [$aMs, $aSeq] = array_map(intval(...), explode('-', $a.'-0'));
        [$bMs, $bSeq] = array_map(intval(...), explode('-', $b.'-0'));

        return [$aMs, $aSeq] <=> [$bMs, $bSeq];
    }

    /** One group is read per call: each pass visits every consumer's group, sharing the block window between them. */
    public function consume(array $consumers, array $channels, callable $handle): void
    {
        $key = $this->config->getKey();
        $host = gethostname() ?: 'unknown-host';
        $window = max(1, intdiv($this->config->getBlockMs(), max(1, count($consumers))));
        $failures = array_fill_keys($consumers, 0);
        $retryAt = array_fill_keys($consumers, 0);
        $this->listening = true;

        foreach ($consumers as $consumer) {
            $this->ensureGroup($key, $consumer);
        }

        while ($this->listening) {
            $visited = false;

            foreach ($consumers as $consumer) {
                if (! $this->listening || $retryAt[$consumer] > time()) {
                    continue;
                }

                $visited = true;
                $name = $consumer.':'.$host;
                $deliver = static fn (Envelope $envelope) => $handle($envelope, $consumer);

                $this->reclaim($key, $consumer, $name, $channels, $deliver);

                // Blocking: own pending entries first, so a failed one is retried before anything later is read.
                $entries = ($this->config->blocksOnFailure() ? $this->read($key, $consumer, $name, self::OWN_PENDING, $window) : [])
                    ?: $this->read($key, $consumer, $name, self::NEW_ENTRIES, $window);

                if ($this->handleEntries($key, $entries, $consumer, $channels, $deliver)) {
                    $failures[$consumer] = 0;

                    continue;
                }

                $failures[$consumer]++;
                $retryAt[$consumer] = time() + min(self::MAX_BACKOFF_SECONDS, 2 ** min($failures[$consumer], 6));
            }

            if (! $visited) {
                sleep(1);
            }
        }
    }

    /** @return array<string, array<string, string>> */
    private function read(string $key, string $group, string $name, string $from, int $blockMs): array
    {
        /** @var array<string, array<string, array<string, string>>>|null|false $read */
        $read = $this->connection()->command('xreadgroup', [
            $group,
            $name,
            [$key => $from],
            $this->config->getCount(),
            $blockMs,
        ]);

        // A single key is read: its entries are the reply's only value, whatever client prefix names it.
        return is_array($read) && $read !== [] ? (array) (array_values($read)[0] ?: []) : [];
    }

    public function stop(): void
    {
        $this->listening = false;
    }

    /**
     * Entries left pending by a consumer that died mid-handling: claim them back after
     * `claimAfterMs` and replay. Nothing is lost when a process is killed.
     *
     * @param  list<string>  $channels
     * @param  callable(Envelope): void  $handle
     */
    private function reclaim(string $key, string $group, string $name, array $channels, callable $handle): void
    {
        $entries = $this->autoclaim($key, $group, $name);

        if ($entries !== []) {
            $this->handleEntries($key, $entries, $group, $channels, $handle);
        }
    }

    /**
     * Sent raw: phpredis 6.1's xAutoClaim() loses the connection. The raw reply lists entries as
     * [id, [field, value, …]].
     *
     * @return array<string, array<string, string>>
     */
    private function autoclaim(string $key, string $group, string $name): array
    {
        $connection = $this->connection();
        $arguments = [$key, $group, $name, (string) $this->config->getClaimAfterMs(), '0-0', 'COUNT', (string) $this->config->getCount()];

        $reply = $connection instanceof PhpRedisConnection
            ? $connection->command('rawCommand', ['XAUTOCLAIM', $connection->command('_prefix', [$key]), ...array_slice($arguments, 1)])
            : $connection->command('xautoclaim', $arguments);

        $entries = [];

        foreach (is_array($reply) && is_array($reply[1] ?? null) ? $reply[1] : [] as $entry) {
            if (is_array($entry) && isset($entry[0]) && is_array($entry[1] ?? null)) {
                $fields = [];

                for ($i = 0; $i + 1 < count($entry[1]); $i += 2) {
                    $fields[(string) $entry[1][$i]] = (string) $entry[1][$i + 1];
                }

                $entries[(string) $entry[0]] = $fields;
            }
        }

        return $entries;
    }

    /**
     * Blocking, a failed entry stays unacknowledged and the pass stops there: handling the next one would
     * apply it ahead of the one that failed. Skipping, it is acknowledged and dropped. An entry from an
     * emitter outside $channels is acknowledged unread.
     *
     * @param  array<string, array<string, string>>  $entries
     * @param  list<string>  $channels
     * @param  callable(Envelope): void  $handle
     */
    private function handleEntries(string $key, array $entries, string $group, array $channels, callable $handle): bool
    {
        foreach ($entries as $id => $fields) {
            $handled = null;

            try {
                $envelope = Envelope::fromJson($fields['envelope'] ?? '{}');

                if (in_array($envelope->emitter, $channels, true)) {
                    $handle($envelope);
                    $handled = $envelope;
                }
            } catch (Throwable $e) {
                report($e);

                if ($this->config->blocksOnFailure()) {
                    return false;
                }
            }

            $this->connection()->command('xack', [$key, $group, [$id]]);

            if ($handled !== null && $this->acknowledged !== null) {
                ($this->acknowledged)($handled, $group);
            }
        }

        return true;
    }

    private function ensureGroup(string $key, string $group): void
    {
        try {
            // From the start of the stream: a consumer plugged in late still sees what was announced before it.
            $this->connection()->command('xgroup', ['CREATE', $key, $group, '0', true]);
        } catch (Throwable) {
            // BUSYGROUP: the group already exists, which is the normal case.
        }
    }

    private function connection(): Connection
    {
        /** @var Connection */
        return $this->redis->connection($this->config->getConnection());
    }
}
