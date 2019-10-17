<?php

namespace Signifly\LaravelQueueRabbitMQ\Queue\Strategies;

interface BackoffStrategyAware
{
    public function setBackoffStrategy(BackoffStrategy $backoffStrategy = null);
}
