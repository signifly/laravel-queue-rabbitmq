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

    public $parentJob = null;
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
                // Set the priority of the job, to one higher than its parent
                $next->withPriority($this->rabbitPriority + ($this->parentJob ? 0 : 1));
                $next->rabbitExchange = $this->rabbitExchange;
                $next->rabbitQueue = $this->rabbitQueue;
                $next->rabbitRoutingKey = $this->rabbitRoutingKey;
                $next->parentJob = $this->parentJob ?? $this->job->getJobId();

                $next->shopId = $this->shopId;
                $next->shopHandle = $this->shopHandle;
                $next->provider = $this->provider;
                $next->rateLimit = $this->rateLimit;

                $next->chainConnection = $this->chainConnection;
                $next->chainQueue = $this->chainQueue;
            }));
        }
    }
}
