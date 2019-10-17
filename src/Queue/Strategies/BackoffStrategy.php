<?php

namespace Signifly\LaravelQueueRabbitMQ\Queue\Strategies;

interface BackoffStrategy
{
    /**
     * Delay in milliseconds.
     *
     * @param int $delay
     * @param int $attempt
     * @return int
     */
    public function backoffDelayTime(int $delay, int $attempt): int;
}
