<?php

namespace Signifly\LaravelQueueRabbitMQ\Queue\Strategies;

use Illuminate\Support\Arr;

class PolynomialBackoffStrategy extends AbstractBackoffStrategy
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
        return intval(pow($attempt, Arr::get($this->options, 'factor', 2)) * $delay);
    }
}
