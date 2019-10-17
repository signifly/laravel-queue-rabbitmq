<?php

namespace Signifly\LaravelQueueRabbitMQ\Queue\Strategies;

trait BackoffStrategyAwareTrait
{
    /**
     * @var BackoffStrategy
     */
    protected $backoffStrategy;

    /**
     * @param BackoffStrategy|null $backoffStrategy
     * @return BackoffStrategyAwareTrait
     */
    public function setBackoffStrategy(BackoffStrategy $backoffStrategy = null)
    {
        $this->backoffStrategy = $backoffStrategy;
        return $this;
    }
}
