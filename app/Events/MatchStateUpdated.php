<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Pushes a fresh match state to every open screen the instant an operator acts,
 * replacing the HTTP poll that each screen used to run every two seconds.
 *
 * ShouldBroadcastNow (not ShouldBroadcast) on purpose: the payload goes out
 * inline with the request rather than through the queue. A scoreboard can't wait
 * on a queue worker, and QUEUE_CONNECTION is "database" here, so a queued
 * broadcast would simply never arrive unless a worker happened to be running.
 *
 * The channel is public. The payload is the scoreline that is already going out
 * on a live stream, and keeping it public means a venue display or an OBS
 * browser source does not need an authenticated session to subscribe. Nothing
 * private is carried — no operator identity, no match feed, no credentials.
 */
class MatchStateUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public int $matchId,
        public array $state,
    ) {
    }

    public function broadcastOn(): Channel
    {
        return new Channel('match.' . $this->matchId);
    }

    /** Short, stable event name the browser subscribes to. */
    public function broadcastAs(): string
    {
        return 'state';
    }

    public function broadcastWith(): array
    {
        return $this->state;
    }
}
