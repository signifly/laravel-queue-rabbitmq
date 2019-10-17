<?php

namespace Signifly\LaravelQueueRabbitMQ\Queue\Strategies;

class LinearBackoffStrategy extends AbstractBackoffStrategy
{
    /**
     * Delay in milliseconds.
     *
     * @param int $delay
     * @param int $attempt
     * @return int
     */
    public function backoffDelayTime(int $delay, int $attempt): int
    {
        return $attempt * $delay;
    }
}
