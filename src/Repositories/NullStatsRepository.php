<?php

namespace Signifly\LaravelQueueRabbitMQ\Repositories;

use Signifly\LaravelQueueRabbitMQ\Monitoring\ConsumerStats;
use Signifly\LaravelQueueRabbitMQ\Monitoring\SentMessageStats;
use Signifly\LaravelQueueRabbitMQ\Monitoring\ConsumedMessageStats;

class NullStatsRepository implements StatsRepository
{
    public function pushConsumerStats(ConsumerStats $stats): void
    {
    }
    public function pushSentMessageStats(SentMessageStats $stats): void
    {
    }
    public function pushConsumedMessageStats(ConsumedMessageStats $stats): void
    {
    }
}
