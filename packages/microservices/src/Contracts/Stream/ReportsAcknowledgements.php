<?php

declare(strict_types=1);

namespace Microservices\Contracts\Stream;

use Microservices\Data\Envelope;

/** A transport that tells, once an envelope is acknowledged, which consumer acknowledged it. */
interface ReportsAcknowledgements
{
    /** @param callable(Envelope, string): void $callback the envelope and the consumer that acknowledged it */
    public function onAcknowledged(callable $callback): void;
}
