<?php

namespace Signifly\LaravelQueueRabbitMQ\Queue;

use Ramsey\Uuid\Uuid;
use RuntimeException;
use Illuminate\Queue\Queue;
use Illuminate\Support\Str;
use Interop\Amqp\AmqpQueue;
use Interop\Amqp\AmqpTopic;
use Psr\Log\LoggerInterface;
use Interop\Amqp\AmqpContext;
use Interop\Amqp\AmqpMessage;
use Interop\Amqp\Impl\AmqpBind;
use Illuminate\Contracts\Queue\Queue as QueueContract;
use Signifly\LaravelQueueRabbitMQ\Queue\Jobs\RabbitMQJob;
use Signifly\LaravelQueueRabbitMQ\Monitoring\SentMessageStats;
use Signifly\LaravelQueueRabbitMQ\Repositories\StatsRepository;

class RabbitMQQueue extends Queue implements QueueContract
{
    protected $sleepOnError;
    protected $dynamicTemplateName;
    protected $dynamicTemplateEnabled;

    protected $queueOptions;
    protected $exchangeOptions;

    protected $declaredExchanges = [];
    protected $declaredQueues = [];
    /**
     * @var BasicConsumeHandler
     */
    private $jobConsumer;

    /**
     * @var AmqpContext
     */
    protected $context;
    protected $correlationId;

    public function __construct(AmqpContext $context, array $config)
    {
        $this->context = $context;

        $this->queueOptions = collect($config['options']['queues'])
            ->map(function ($queue) {
                if (isset($queue['arguments'])) {
                    $queue['arguments'] = is_string($queue['arguments'])
                        ? json_decode($queue['arguments'], true)
                        : $queue['arguments'];
                } else {
                    $queue['arguments'] = [];
                }
                return $queue;
            });

        $this->exchangeOptions = collect($config['options']['exchanges'])
            ->map(function ($exchange) {
                if (isset($exchange['arguments'])) {
                    $exchange['arguments'] = is_string($exchange['arguments'])
                        ? json_decode($exchange['arguments'], true)
                        : $exchange['arguments'];
                } else {
                    $exchange['arguments'] = [];
                }
                return $exchange;
            });

        $this->sleepOnError = $config['sleep_on_error'] ?? 5;
        $this->dynamicTemplateName = $config['dynamic_queue_template'];
        $this->dynamicTemplateEnabled = $config['dynamic_queue_enable'];
    }

    /** {@inheritdoc} */
    public function size($queueName = null): int
    {
        /** @var AmqpQueue $queue */
        [$queue] = $this->declareEverything($queueName);

        return $this->context->declareQueue($queue);
    }

    /** {@inheritdoc} */
    public function push($job, $data = '', $queue = null)
    {
        return $this->pushRaw(
            $this->createPayload($job, $queue, $data),
            $queue,
            $this->makeOptions($job)
        );
    }

    /** {@inheritdoc} */
    public function pushRaw($payload, $queueName = null, array $options = [])
    {
        try {
            /**
             * @var AmqpTopic
             * @var AmqpQueue $queue
             */
            [$queue, $topic] = $this->declareEverything(
                (isset($options['queue']) ? $options['queue'] : null) ?: $queueName,
                isset($options['exchange']) ? $options['exchange'] : null
            );

            /** @var AmqpMessage $message */
            $message = $this->context->createMessage($payload);

            $message->setCorrelationId($this->getCorrelationId());
            $message->setContentType('application/json');
            $message->setDeliveryMode(AmqpMessage::DELIVERY_MODE_PERSISTENT);

            if (isset($options['routing_key'])) {
                $message->setRoutingKey($options['routing_key']);
            } else {
                $message->setRoutingKey($queue->getQueueName());
            }

            if (isset($options['priority'])) {
                $message->setPriority($options['priority']);
            }

            if (isset($options['expiration'])) {
                $message->setExpiration($options['expiration']);
            }

            if (isset($options['headers'])) {
                $message->setHeaders($options['headers']);
            }

            if (isset($options['properties'])) {
                $message->setProperties($options['properties']);
            }

            if (isset($options['attempts'])) {
                $message->setProperty(RabbitMQJob::ATTEMPT_COUNT_HEADERS_KEY, $options['attempts']);
            }

            $producer = $this->context->createProducer();
            if (isset($options['delay']) && $options['delay'] > 0) {
                $producer->setDeliveryDelay($options['delay'] * 1000);
            }

            $producer->send($topic, $message);

            $this->container->make(StatsRepository::class)->pushSentMessageStats(new SentMessageStats(
                (int) (microtime(true) * 1000),
                $queue->getQueueName(),
                true,
                $message->getMessageId(),
                $message->getCorrelationId(),
                $message->getHeaders(),
                $message->getProperties()
            ));

            return $message->getCorrelationId();
        } catch (\Exception $exception) {
            $this->reportConnectionError('pushRaw', $exception);

            return;
        }
    }

    /** {@inheritdoc} */
    public function later($delay, $job, $data = '', $queue = null)
    {
        return $this->pushRaw(
            $this->createPayload($job, $queue, $data),
            $queue,
            $this->makeOptions($job, ['delay' => $this->secondsUntil($delay)])
        );
    }

    /**
     * Release a reserved job back onto the queue.
     *
     * @param  \DateTimeInterface|\DateInterval|int $delay
     * @param  string|object $job
     * @param  mixed $data
     * @param  string $queue
     * @param  int $attempts
     * @return mixed
     */
    public function release($delay, $job, $data, $queue, $attempts = 0)
    {
        return $this->pushRaw(
            $this->createPayload($job, $queue, $data),
            $queue,
            $this->makeOptions($job, [
                'delay' => $this->secondsUntil($delay),
                'attempts' => $attempts,
            ])
        );
    }

    /**
     * @param null $queueId
     * @return RabbitMQJob|null
     * @throws \Interop\Queue\Exception\SubscriptionConsumerNotSupportedException
     */
    private function popBasicConsume($queueName): ?RabbitMQJob
    {
        [$queue] = $this->declareEverything($queueName);

        $options = [];
        if ($timeout = $this->queueOptions[$queueName ?: 'default']['basic_consume_timeout'] ?? false) {
            $options['timeout'] = $timeout;
        }
        $this->jobConsumer = $this->jobConsumer ?: new BasicConsumeHandler(
            $this->context,
            $queue,
            $options
        );

        return $this->jobConsumer->getJob($this->container, $this);
    }

    /**
     * @param $queueId
     * @return RabbitMQJob|null
     */
    private function popPolling($queueName): ?RabbitMQJob
    {
        /** @var AmqpQueue $queue */
        [$queue] = $this->declareEverything($queueName);

        $consumer = $this->context->createConsumer($queue);

        if ($message = $consumer->receiveNoWait()) {
            return new RabbitMQJob($this->container, $this, $consumer, $message);
        }

        return null;
    }

    /** {@inheritdoc} */
    public function pop($queueName = null)
    {
        try {
            $this->ensureQueueConfig($queueName);
            if ($this->queueOptions[$queueName ?: 'default']['basic_consume'] ?? false) {
                return $this->popBasicConsume($queueName);
            }

            return $this->popPolling($queueName);
        } catch (\Throwable $exception) {
            $this->reportConnectionError('pop', $exception);

            return;
        }
    }

    protected function ensureQueueConfig($queueName = null)
    {
        if (! $this->dynamicTemplateEnabled) {
            return ;
        }

        if (isset($this->queueOptions[$queueName ?: 'default'])) {
            return;
        }

        $this->queueOptions[$queueName] = array_merge(
            $this->queueOptions[$this->dynamicTemplateName],
            ['name' => $queueName]
        );
    }

    /**
     * Retrieves the correlation id, or a unique id.
     *
     * @return string
     */
    public function getCorrelationId(): string
    {
        return $this->correlationId ?: (string) Uuid::uuid4();
    }

    /**
     * Sets the correlation id for a message to be published.
     *
     * @param string $id
     *
     * @return void
     */
    public function setCorrelationId(string $id): void
    {
        $this->correlationId = $id;
    }

    /**
     * @return AmqpContext
     */
    public function getContext(): AmqpContext
    {
        return $this->context;
    }

    /**
     * @param string $queueId
     *
     * @return array [Interop\Amqp\AmqpQueue, Interop\Amqp\AmqpTopic]
     */
    public function declareEverything(string $queueId = null, string $exchange = null): array
    {
        $this->ensureQueueConfig($queueId);
        $queueName = $this->getQueueName($queueId);

        $queueOptions = $this->queueOptions[$queueName];
        $queueName = $queueOptions['name'];

        $exchangeOptions = $this->exchangeOptions[$exchange ?: 'default'];
        $exchangeName = $exchangeOptions['name'] ?: $queueName;

        $topic = $this->context->createTopic($exchangeName);
        $topic->setType($exchangeOptions['type']);
        $topic->setArguments($exchangeOptions['arguments']);
        if ($exchangeOptions['passive']) {
            $topic->addFlag(AmqpTopic::FLAG_PASSIVE);
        }
        if ($exchangeOptions['durable']) {
            $topic->addFlag(AmqpTopic::FLAG_DURABLE);
        }
        if ($exchangeOptions['auto_delete']) {
            $topic->addFlag(AmqpTopic::FLAG_AUTODELETE);
        }

        if ($exchangeOptions['declare'] && ! in_array($exchangeName, $this->declaredExchanges, true)) {
            $this->context->declareTopic($topic);

            $this->declaredExchanges[] = $exchangeName;
        }

        $queue = $this->context->createQueue($queueName);
        $queue->setArguments($queueOptions['arguments']);
        if ($queueOptions['passive']) {
            $queue->addFlag(AmqpQueue::FLAG_PASSIVE);
        }
        if ($queueOptions['durable']) {
            $queue->addFlag(AmqpQueue::FLAG_DURABLE);
        }
        if ($queueOptions['exclusive']) {
            $queue->addFlag(AmqpQueue::FLAG_EXCLUSIVE);
        }
        if ($queueOptions['auto_delete']) {
            $queue->addFlag(AmqpQueue::FLAG_AUTODELETE);
        }

        if ($queueOptions['declare'] && ! in_array($queueName, $this->declaredQueues, true)) {
            $this->context->declareQueue($queue);

            $this->declaredQueues[] = $queueName;
        }

        if ($queueOptions['bind']) {
            $this->context->bind(new AmqpBind($queue, $topic, $queue->getQueueName()));
        }

        return [$queue, $topic];
    }

    protected function getQueueName($queueId = null)
    {
        $this->ensureQueueConfig($queueId);
        if (isset($this->queueOptions[$queueId ?: 'default'])) {
            return $this->queueOptions[$queueId ?: 'default']['name'];
        }
        return $queueId;
    }

    protected function createPayloadArray($job, $queue, $data = '')
    {
        $payload = parent::createPayloadArray($job, $queue, $data);

        if (method_exists($job, 'getJobId')) {
            $this->setCorrelationId($job->getJobId());
        }

        return array_merge($payload, [
            'id' => $this->getCorrelationId(),
        ]);
    }

    protected function makeOptions($job, array $options = [])
    {
        if (method_exists($job, 'rabbitMQOptions')) {
            return array_merge($options, $job->rabbitMQOptions());
        }

        return $options;
    }

    /**
     * Get a random ID string.
     *
     * @return string
     */
    protected function getRandomId(): string
    {
        return Str::random(32);
    }

    /**
     * @param string $action
     * @param \Throwable $e
     * @throws \Exception
     */
    protected function reportConnectionError($action, \Throwable $e)
    {
        /** @var LoggerInterface $logger */
        $logger = $this->container['log'];

        $logger->error('AMQP error while attempting '.$action.': '.$e->getMessage());

        // If it's set to false, throw an error rather than waiting
        if ($this->sleepOnError === false) {
            throw new RuntimeException('Error writing data to the connection with RabbitMQ', null, $e);
        }

        // Sleep so that we don't flood the log file
        sleep($this->sleepOnError);
    }
}
