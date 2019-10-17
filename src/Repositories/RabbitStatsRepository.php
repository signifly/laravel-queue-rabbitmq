<?php

namespace Signifly\LaravelQueueRabbitMQ\Repositories;

use Signifly\LaravelQueueRabbitMQ\Monitoring\SentMessageStats;
use Signifly\LaravelQueueRabbitMQ\Monitoring\ConsumedMessageStats;
use Signifly\LaravelQueueRabbitMQ\Monitoring\GenericStatsStorageFactory;

class RabbitStatsRepository
{
    public function pushSentMessageStats(SentMessageStats $stats)
    {
        if (! config('queue.connections.rabbitmq.monitor.enabled')) {
            return;
        }

        $statsStorage = (new GenericStatsStorageFactory())->create(config('queue.connections.rabbitmq.monitor.dsn'));
        $statsStorage->pushSentMessageStats($stats);
    }
    public function pushConsumedMessageStats(ConsumedMessageStats $stats)
    {
        if (! config('queue.connections.rabbitmq.monitor.enabled')) {
            return;
        }

        $statsStorage = (new GenericStatsStorageFactory())->create(config('queue.connections.rabbitmq.monitor.dsn'));
        $statsStorage->pushConsumedMessageStats($stats);
    }
}
