<?php

namespace Signifly\LaravelQueueRabbitMQ\Queue\Strategies;

trait PrioritizeAwareTrait
{
    /**
     * @var bool|null
     */
    protected $prioritize;

    /**
     * @param bool $prioritize
     * @return PrioritizeAwareTrait
     */
    public function setPrioritize(?bool $prioritize = null)
    {
        $this->prioritize = $prioritize;
        return $this;
    }
}
