<?php

namespace Signifly\LaravelQueueRabbitMQ\Queue\Strategies;

interface PrioritizeAware
{
    public function setPrioritize(?bool $prioritize = null);
}
