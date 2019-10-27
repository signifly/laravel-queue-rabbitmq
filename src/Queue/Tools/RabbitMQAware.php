<?php

namespace Signifly\LaravelQueueRabbitMQ\Queue\Tools;

use Signifly\LaravelQueueRabbitMQ\Events\JobChainPushed;

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
            'properties' => [
                'x-queued-at' => (int) (microtime(true) * 1000),
            ],
        ];
    }

    public function withPriority(int $priority)
    {
        $this->rabbitPriority = $priority;
        return $this;
    }

    public function getBucketIdentifier()
    {
        return $this->rabbitQueue;
    }

    /**
     * Dispatch the next job on the chain.
     *
     * @return void
     */
    public function dispatchNextJobInChain()
    {
        if (! empty($this->chained)) {
            $newId = app(\Illuminate\Contracts\Bus\Dispatcher::class)->dispatch(tap(unserialize(array_shift($this->chained)), function ($next) {
                $next->chained = $this->chained;

                $next->onConnection($next->connection ?: $this->chainConnection);
                $next->onQueue($next->queue ?: $this->chainQueue);
                $next->withPriority(1);

                $next->chainConnection = $this->chainConnection;
                $next->chainQueue = $this->chainQueue;
            }));

            event(new JobChainPushed($this->job, $newId));
        }
    }
}
