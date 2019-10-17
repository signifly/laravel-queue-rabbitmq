<?php

declare(strict_types=1);

namespace Signifly\LaravelQueueRabbitMQ\Repositories;

use Signifly\LaravelQueueRabbitMQ\Monitoring\ConsumerStats;
use Signifly\LaravelQueueRabbitMQ\Monitoring\SentMessageStats;
use Signifly\LaravelQueueRabbitMQ\Monitoring\ConsumedMessageStats;

interface StatsRepository
{
    public function pushConsumerStats(ConsumerStats $stats): void;
    public function pushSentMessageStats(SentMessageStats $stats): void;
    public function pushConsumedMessageStats(ConsumedMessageStats $stats): void;
}
