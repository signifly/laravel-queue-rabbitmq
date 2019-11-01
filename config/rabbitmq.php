<?php

/**
 * This is an example of queue connection configuration.
 * It will be merged into config/queue.php.
 * You need to set proper values in `.env`.
 */
return [

    'driver' => 'rabbitmq',

    /*
     * Set to "horizon" if you wish to use Laravel Horizon.
     */
    'worker' => env('RABBITMQ_WORKER', 'default'),

    'dsn' => env('RABBITMQ_DSN', null),
    'dynamic_queue_enable' => env('RABBITMQ_DYNAMIC_ENABLE', true),
    'dynamic_queue_template' => env('RABBITMQ_DYNAMIC_TEMPLATE', 'default'),

    /*
     * Could be one a class that implements \Interop\Amqp\AmqpConnectionFactory for example:
     *  - \EnqueueAmqpExt\AmqpConnectionFactory if you install enqueue/amqp-ext
     *  - \EnqueueAmqpLib\AmqpConnectionFactory if you install enqueue/amqp-lib
     *  - \EnqueueAmqpBunny\AmqpConnectionFactory if you install enqueue/amqp-bunny
     */
    'factory_class' => Enqueue\AmqpLib\AmqpConnectionFactory::class,

    'host' => env('RABBITMQ_HOST', '127.0.0.1'),
    'port' => env('RABBITMQ_PORT', 5672),

    'vhost' => env('RABBITMQ_VHOST', '/'),
    'login' => env('RABBITMQ_LOGIN', 'guest'),
    'password' => env('RABBITMQ_PASSWORD', 'guest'),

    'queue' => env('RABBITMQ_QUEUE', 'default'),

    'options' => [

        'exchanges' => [
            'default' => [
                'name' => env('RABBITMQ_EXCHANGE_NAME'),

                /*
                * Determine if exchange should be created if it does not exist.
                */
                'declare' => env('RABBITMQ_EXCHANGE_DECLARE', true),

                /*
                * Read more about possible values at https://www.rabbitmq.com/tutorials/amqp-concepts.html
                */
                'type' => env('RABBITMQ_EXCHANGE_TYPE', \Interop\Amqp\AmqpTopic::TYPE_DIRECT),
                'passive' => env('RABBITMQ_EXCHANGE_PASSIVE', false),
                'durable' => env('RABBITMQ_EXCHANGE_DURABLE', true),
                'auto_delete' => env('RABBITMQ_EXCHANGE_AUTODELETE', false),
                'arguments' => env('RABBITMQ_EXCHANGE_ARGUMENTS'),
            ]
        ],

        'queues' => [
            'default' => [
                /*
                * Determine if queue should be created if it does not exist.
                */
                'declare' => env('RABBITMQ_QUEUE_DECLARE', true),

                /*
                * Determine if queue should be binded to the exchange created.
                */
                'bind' => env('RABBITMQ_QUEUE_DECLARE_BIND', true),

                /*
                * Read more about possible values at https://www.rabbitmq.com/tutorials/amqp-concepts.html
                */
                'passive' => env('RABBITMQ_QUEUE_PASSIVE', false),
                'durable' => env('RABBITMQ_QUEUE_DURABLE', true),
                'exclusive' => env('RABBITMQ_QUEUE_EXCLUSIVE', false),
                'auto_delete' => env('RABBITMQ_QUEUE_AUTODELETE', false),
                'arguments' => env('RABBITMQ_QUEUE_ARGUMENTS'),

                'basic_consume' => env('RABBITMQ_QUEUE_BASIC_CONSUME', false),
                'basic_consume_timeout' => env('RABBITMQ_QUEUE_BASIC_CONSUME_TIMEOUT', false),
            ]
        ],
    ],

    /*
     * Determine the number of seconds to sleep if there's an error communicating with rabbitmq
     * If set to false, it'll throw an exception rather than doing the sleep for X seconds.
     */
    'sleep_on_error' => env('RABBITMQ_ERROR_SLEEP', 5),

    /*
     * Optional SSL params if an SSL connection is used
     */
    'ssl_params' => [
        'ssl_on' => env('RABBITMQ_SSL', false),
        'cafile' => env('RABBITMQ_SSL_CAFILE', null),
        'local_cert' => env('RABBITMQ_SSL_LOCALCERT', null),
        'local_key' => env('RABBITMQ_SSL_LOCALKEY', null),
        'verify_peer' => env('RABBITMQ_SSL_VERIFY_PEER', true),
        'passphrase' => env('RABBITMQ_SSL_PASSPHRASE', null),
    ],

    /*
     * ## Manage the delay strategy from the config.
     *
     * The delay strategy can be set to:
     *  - \Signifly\LaravelQueueRabbitMQ\Queue\Strategies\DlxDelayStrategy::class
     *
     * ### Backoff Strategy
     *
     * The `DlxDelayStrategy` is BackoffAware and by default a ConstantBackoffStrategy is assigned.
     * This ensures the same behavior as if the `RabbitMqDlxDelayStrategy` was assigned.
     *
     * You can assign different backoffStrategies with extra options, for example:
     *  - \Signifly\LaravelQueueRabbitMQ\Queue\Strategies\ConstantBackoffStrategy::class
     *  - \Signifly\LaravelQueueRabbitMQ\Queue\Strategies\LinearBackoffStrategy::class
     *  - \Signifly\LaravelQueueRabbitMQ\Queue\Strategies\ExponentialBackoffStrategy::class
     *  - \Signifly\LaravelQueueRabbitMQ\Queue\Strategies\PolynomialBackoffStrategy::class
     *
     * The options must be an array of key -> value.
     *
     * For reference about RabbitMQ backoff strategy, see the following article:
     * https://m.alphasights.com/exponential-backoff-with-rabbitmq-78386b9bec81
     *
     * ### First-in First-out concept
     *
     * U can easily prioritize delayed messages. When set to `true` a message will be set with a higher priority.
     * This means that delayed messages are handled first when returning to the queue.
     *
     * This is useful when your queue has allot of jobs, and you want to make sure, a job will be handled
     * as soon as possible. This way RabbitMq handles the jobs and the way they are consumed by workers.
     *
     */
    'delay' => [
        'strategy' => env('RABBITMQ_DELAY_STRATEGY', \Signifly\LaravelQueueRabbitMQ\Queue\Strategies\DlxDelayStrategy::class),
        'backoff'  => [
            'strategy' => env('RABBITMQ_DELAY_BACKOFF_STRATEGY', \Signifly\LaravelQueueRabbitMQ\Queue\Strategies\ExponentialBackoffStrategy::class),
            'options'  => [],
        ],
        'prioritize'=> env('RABBITMQ_DELAY_PRIORITIZE'),
    ],

];
