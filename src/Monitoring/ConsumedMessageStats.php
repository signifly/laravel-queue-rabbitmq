<?php

declare(strict_types=1);

namespace Signifly\LaravelQueueRabbitMQ\Monitoring;

class ConsumedMessageStats implements Stats
{
    const STATUS_ACK = 'acknowledged';
    const STATUS_REJECTED = 'rejected';
    const STATUS_REQUEUED = 'requeued';
    const STATUS_FAILED = 'failed';

    /**
     * @var string
     */
    protected $consumerId;

    /**
     * @var int
     */
    protected $queuedAtMs;

    /**
     * @var int
     */
    protected $receivedAtMs;

    /**
     * @var int
     */
    protected $processedAtMs;

    /**
     * @var string
     */
    protected $queue;

    /**
     * @var string
     */
    protected $messageId;

    /**
     * @var string
     */
    protected $correlationId;

    /**
     * @var array
     */
    protected $headers;

    /**
     * @var array
     */
    protected $properties;

    /**
     * @var bool;
     */
    protected $redelivered;

    /**
     * @var string
     */
    protected $status;

    public function __construct(
        string $consumerId,
        int $queuedAtMs,
        int $receivedAtMs,
        int $processedAtMs,
        string $queue,
        ?string $messageId,
        ?string $correlationId,
        array $headers,
        array $properties,
        bool $redelivered,
        string $status
    ) {
        $this->consumerId = $consumerId;
        $this->queuedAtMs = $queuedAtMs;
        $this->receivedAtMs = $receivedAtMs;
        $this->processedAtMs = $processedAtMs;
        $this->queue = $queue;
        $this->messageId = $messageId;
        $this->correlationId = $correlationId;
        $this->headers = $headers;
        $this->properties = $properties;
        $this->redelivered = $redelivered;
        $this->status = $status;
    }

    public function getConsumerId(): string
    {
        return $this->consumerId;
    }

    public function getqueuedAtMs(): int
    {
        return $this->queuedAtMs;
    }

    public function getReceivedAtMs(): int
    {
        return $this->receivedAtMs;
    }

    public function getProcessedAtMs(): int
    {
        return $this->processedAtMs;
    }

    public function getQueue(): string
    {
        return $this->queue;
    }

    public function getMessageId(): ?string
    {
        return $this->messageId;
    }

    public function getCorrelationId(): ?string
    {
        return $this->correlationId;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getProperties(): array
    {
        return $this->properties;
    }

    public function isRedelivered(): bool
    {
        return $this->redelivered;
    }

    public function getStatus(): string
    {
        return $this->status;
    }
}
