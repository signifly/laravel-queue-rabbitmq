<?php

namespace Signifly\LaravelQueueRabbitMQ\Queue\Strategies;

use Symfony\Component\HttpFoundation\ParameterBag;

abstract class AbstractBackoffStrategy implements BackoffStrategy
{
    protected $options;

    public function __construct(array $options = [])
    {
        $this->options = new ParameterBag($options);
    }
}
