<?php

declare(strict_types=1);

namespace Microservices\Services\Stream;

use Illuminate\Database\DatabaseManager;
use Microservices\Config\Streamer;
use Microservices\Contracts\Colocation;
use Microservices\Contracts\Stream\Bus;
use Microservices\Contracts\Stream\Event;
use Microservices\Services\Stream\Outbox\Relay;
use Microservices\Services\Stream\Outbox\Writer;

/**
 * The single emission path: an event becomes an envelope stamped with its emitting service, then
 * goes onto its stream right after the commit of the transaction it was emitted in. With
 * reliable_publish, its row is first written in that transaction and stays pending if the sending fails.
 */
final class Emitter implements Bus
{
    public function __construct(
        private readonly EnvelopeFactory $envelopes,
        private readonly TransportManager $transports,
        private readonly Writer $outbox,
        private readonly Relay $relay,
        private readonly DatabaseManager $db,
        private readonly Colocation $colocation,
        private readonly Streamer $config,
    ) {}

    public function emit(Event $event): void
    {
        $envelope = $this->envelopes->for($event);

        $connection = $this->db->connection($this->colocation->connection($envelope->emitter));

        if (! $this->config->publishesReliably()) {
            $connection->afterCommit(fn () => $this->transports->stream($envelope->stream)->publish($envelope));

            return;
        }

        $this->outbox->write($envelope);

        $connection->afterCommit(fn () => $this->relay->publishNow($envelope->emitter, $envelope->id));
    }
}
