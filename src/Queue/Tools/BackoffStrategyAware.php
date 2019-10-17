<?php

namespace Signifly\LaravelQueueRabbitMQ\Queue\Tools;

interface BackoffStrategyAware
{
    public function setBackoffStrategy(BackoffStrategy $backoffStrategy = null);
}
