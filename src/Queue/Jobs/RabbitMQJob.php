<?php

namespace Signifly\LaravelQueueRabbitMQ\Queue\Jobs;

use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Interop\Amqp\AmqpMessage;
use Illuminate\Queue\Jobs\Job;
use Interop\Amqp\AmqpConsumer;
use Illuminate\Queue\Jobs\JobName;
use Illuminate\Container\Container;
use Illuminate\Database\DetectsLostConnections;
use Illuminate\Contracts\Queue\Job as JobContract;
use Signifly\LaravelQueueRabbitMQ\Events\JobRetry;
use Signifly\LaravelQueueRabbitMQ\Queue\RabbitMQQueue;
use Signifly\LaravelQueueRabbitMQ\Repositories\StatsRepository;
use Signifly\LaravelQueueRabbitMQ\Monitoring\ConsumedMessageStats;
use Signifly\LaravelQueueRabbitMQ\Horizon\RabbitMQQueue as HorizonRabbitMQQueue;

class RabbitMQJob extends Job implements JobContract
{
    use DetectsLostConnections;

    /**
     * Same as RabbitMQQueue, used for attempt counts.
     */
    public const ATTEMPT_COUNT_HEADERS_KEY = 'attempts_count';

    protected $connection;
    protected $consumer;
    protected $message;
    protected $receivedAt;

    public function __construct(
        Container $container,
        RabbitMQQueue $connection,
        AmqpConsumer $consumer,
        AmqpMessage $message
    ) {
        $this->container = $container;
        $this->connection = $connection;
        $this->consumer = $consumer;
        $this->message = $message;
        $this->queue = $consumer->getQueue()->getQueueName();
        $this->connectionName = $connection->getConnectionName();
        $this->receivedAt = (int) (microtime(true) * 1000);
    }

    /**
     * Fire the job.
     *
     * @throws Exception
     *
     * @return void
     */
    public function fire(): void
    {
        try {
            $payload = $this->payload();

            [$class, $method] = JobName::parse($payload['job']);

            with($this->instance = $this->resolve($class))->{$method}($this, $payload['data']);

            $this->pushStats(ConsumedMessageStats::STATUS_ACK);
        } catch (Exception $exception) {
            if (
                $this->causedByLostConnection($exception) ||
                Str::contains($exception->getMessage(), ['detected deadlock'])
            ) {
                sleep(2);
                $this->fire();

                return;
            }

            throw $exception;
        }
    }

    public function failed($exception)
    {
        $this->pushStats(ConsumedMessageStats::STATUS_FAILED);

        return parent::failed($exception);
    }

    protected function pushStats($status)
    {
        $this->container->make(StatsRepository::class)->pushConsumedMessageStats(new ConsumedMessageStats(
            $this->consumer->getConsumerTag() ?? 'unknown',
            Arr::get($this->message->getProperties(), 'x-queued-at', 0),
            $this->receivedAt,
            (int) (microtime(true) * 1000), // now
            $this->queue,
            $this->message->getMessageId(),
            $this->message->getCorrelationId(),
            $this->message->getHeaders(),
            $this->message->getProperties(),
            $this->message->isRedelivered(),
            $status
        ));
    }

    /**
     * Get the number of times the job has been attempted.
     *
     * @return int
     */
    public function attempts(): int
    {
        // set default job attempts to 1 so that jobs can run without retry
        $defaultAttempts = 1;

        return $this->message->getProperty(self::ATTEMPT_COUNT_HEADERS_KEY, $defaultAttempts);
    }

    /**
     * Get the raw body string for the job.
     *
     * @return string
     */
    public function getRawBody(): string
    {
        return $this->message->getBody();
    }

    /** {@inheritdoc} */
    public function delete(): void
    {
        parent::delete();

        $this->consumer->acknowledge($this->message);

        // required for Laravel Horizon
        if ($this->connection instanceof HorizonRabbitMQQueue) {
            $this->connection->deleteReserved($this->queue, $this);
        }

        $this->pushStats(ConsumedMessageStats::STATUS_REJECTED);
    }

    /** {@inheritdoc}
     * @throws Exception
     */
    public function release($delay = 0): void
    {
        parent::release($delay);

        $this->delete();

        $body = $this->payload();

        /*
         * Some jobs don't have the command set, so fall back to just sending it the job name string
         */
        if (isset($body['data']['command']) === true) {
            $job = $this->unserialize($body);
        } else {
            $job = $this->getName();
        }

        $data = $body['data'];

        $newId = $this->connection->release($delay, $job, $data, $this->getQueue(), $this->attempts() + 1);

        event(new JobRetry($this, $newId));

        $this->pushStats(ConsumedMessageStats::STATUS_REQUEUED);
    }

    /**
     * Get the job identifier.
     *
     * @return string|null
     */
    public function getJobId(): ?string
    {
        return $this->message->getCorrelationId();
    }

    /**
     * Sets the job identifier.
     *
     * @param string $id
     *
     * @return void
     */
    public function setJobId($id): void
    {
        $this->connection->setCorrelationId($id);
    }

    /**
     * Unserialize job.
     *
     * @param array $body
     *
     * @throws Exception
     *
     * @return mixed
     */
    protected function unserialize(array $body)
    {
        try {
            /* @noinspection UnserializeExploitsInspection */
            return unserialize($body['data']['command']);
        } catch (Exception $exception) {
            if (
                $this->causedByLostConnection($exception) ||
                Str::contains($exception->getMessage(), ['detected deadlock'])
            ) {
                sleep(2);

                return $this->unserialize($body);
            }

            throw $exception;
        }
    }
}
