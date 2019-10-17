<?php

namespace Signifly\LaravelQueueRabbitMQ\Queue\Strategies;

abstract class AbstractBackoffStrategy implements BackoffStrategy
{
    protected $options;

    public function __construct(array $options = [])
    {
        $this->options = $options;
    }
}
