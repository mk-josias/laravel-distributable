<?php

declare(strict_types=1);

namespace Microservices\Tests\Fixtures\App;

use Microservices\Contracts\Stream\Transport;
use Microservices\Data\Envelope;
use RuntimeException;

/** A broker that refuses every publication until it is brought back. */
final class FlakyTransport implements Transport
{
    public bool $down = true;

    /** @var list<Envelope> */
    public array $published = [];

    public function publish(Envelope $envelope): void
    {
        if ($this->down) {
            throw new RuntimeException('broker down');
        }

        $this->published[] = $envelope;
    }

    public function consume(array $consumers, array $channels, callable $handle): void {}

    public function stop(): void {}
}
