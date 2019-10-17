<?php

declare(strict_types=1);

namespace Signifly\LaravelQueueRabbitMQ\Monitoring;

interface StatsStorageFactory
{
    public function create($config): StatsStorage;
}
