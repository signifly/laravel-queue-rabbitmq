<?php

namespace Signifly\LaravelQueueRabbitMQ;

use Illuminate\Queue\QueueManager;
use Illuminate\Support\ServiceProvider;
use Signifly\LaravelQueueRabbitMQ\Repositories\StatsRepository;
use Signifly\LaravelQueueRabbitMQ\Repositories\NullStatsRepository;
use Signifly\LaravelQueueRabbitMQ\Queue\Connectors\RabbitMQConnector;

class LaravelQueueRabbitMQServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/rabbitmq.php',
            'queue.connections.rabbitmq'
        );
    }

    /**
     * Register the application's event listeners.
     *
     * @return void
     */
    public function boot(): void
    {
        /** @var QueueManager $queue */
        $queue = $this->app['queue'];

        $queue->addConnector('rabbitmq', function () {
            return new RabbitMQConnector($this->app['events']);
        });

        $this->app->singleton(StatsRepository::class, function () {
            return new NullStatsRepository();
        });
    }
}
