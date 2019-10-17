<?php

namespace Signifly\LaravelQueueRabbitMQ\Queue\Tools;

trait RabbitMQAware
{
    public $rabbitExchange = 'default';
    public $rabbitQueue = 'default';
    public $rabbitRoutingKey = null;
    public $rabbitPriority = 0;

    public function rabbitMQOptions(): array
    {
        return [
            'exchange' => $this->rabbitExchange,
            'queue' => $this->rabbitQueue,
            'routing_key' => $this->rabbitRoutingKey ?? $this->rabbitQueue,
            'priority' => $this->rabbitPriority,
        ];
    }

    public function withPriority(int $priority)
    {
        $this->rabbitPriority = $priority;
        return $this;
    }

    /**
     * Dispatch the next job on the chain.
     *
     * @return void
     */
    public function dispatchNextJobInChain()
    {
        if (! empty($this->chained)) {
            dispatch(tap(unserialize(array_shift($this->chained)), function ($next) {
                $next->chained = $this->chained;

                $next->onConnection($next->connection ?: $this->chainConnection);
                $next->onQueue($next->queue ?: $this->chainQueue);
                $next->withPriority(1);

                $next->chainConnection = $this->chainConnection;
                $next->chainQueue = $this->chainQueue;
            }));
        }
    }
}
