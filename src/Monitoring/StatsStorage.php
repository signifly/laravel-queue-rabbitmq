<?php

declare(strict_types=1);

namespace Signifly\LaravelQueueRabbitMQ\Monitoring;

interface StatsStorage
{
    public function pushConsumerStats(ConsumerStats $stats): void;
    public function pushSentMessageStats(SentMessageStats $stats): void;
    public function pushConsumedMessageStats(ConsumedMessageStats $stats): void;
}
