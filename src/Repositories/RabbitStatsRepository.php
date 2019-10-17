<?php

namespace Signifly\LaravelQueueRabbitMQ\Repositories;

use InfluxDB\Client;
use Illuminate\Support\Str;
use Signifly\LaravelQueueRabbitMQ\Monitoring\SentMessageStats;
use Signifly\LaravelQueueRabbitMQ\Monitoring\ConsumedMessageStats;

class InfluxStatsRepository implements StatsStorage
{
    /**
     * @var Client
     */
    protected $client;

    /**
     * @var Database
     */
    protected $database;

    /**
     * @var array
     */
    protected $config;

    public function __construct($dsn)
    {
        if (! config('queue.connections.rabbitmq.monitor.enabled')) {
            return ;
        }

        if (! Str::startsWith($dsn, 'influxdb')) {
            throw new \LogicException(sprintf('A given scheme "%s" is not supported.', $dsn->getScheme()));
        }

        $this->config = array_replace([
            'host' => '127.0.0.1',
            'port' => '8086',
            'user' => '',
            'password' => '',
            'db' => 'enqueue',
            'measurementSentMessages' => 'sent-messages',
            'measurementConsumedMessages' => 'consumed-messages',
            'measurementConsumers' => 'consumers',
            'retentionPolicy' => null,
        ], $this->parseDsn($dsn));
    }

    public function pushSentMessageStats(SentMessageStats $stats): void
    {
        $tags = [
            'destination' => $stats->getDestination(),
        ];

        $properties = $stats->getProperties();

        if (false === empty($properties[Config::TOPIC])) {
            $tags['topic'] = $properties[Config::TOPIC];
        }

        if (false === empty($properties[Config::COMMAND])) {
            $tags['command'] = $properties[Config::COMMAND];
        }

        $points = [
            new Point($this->config['measurementSentMessages'], 1, $tags, [], $stats->getTimestampMs()),
        ];

        $this->doWrite($points);
    }
    public function pushConsumedMessageStats(ConsumedMessageStats $stats): void
    {
        $tags = [
            'queue' => $stats->getQueue(),
            'status' => $stats->getStatus(),
        ];

        $properties = $stats->getProperties();

        if (false === empty($properties[Config::TOPIC])) {
            $tags['topic'] = $properties[Config::TOPIC];
        }

        if (false === empty($properties[Config::COMMAND])) {
            $tags['command'] = $properties[Config::COMMAND];
        }

        $values = [
            'queuedAt' => $stats->getQueuedAtMs(),
            'receivedAt' => $stats->getReceivedAtMs(),
            'processedAt' => $stats->getProcessedAtMs(),
            'redelivered' => $stats->isRedelivered(),
        ];

        if (ConsumedMessageStats::STATUS_FAILED === $stats->getStatus()) {
            $values['failed'] = 1;
        }

        $runtime = $stats->getProcessedAtMs() - $stats->getReceivedAtMs();

        $points = [
            new Point($this->config['measurementConsumedMessages'], $runtime, $tags, $values, $stats->getProcessedAtMs()),
        ];

        $this->doWrite($points);
    }

    public function pushConsumerStats(ConsumerStats $stats): void
    {
        $points = [];

        foreach ($stats->getQueues() as $queue) {
            $tags = [
                'queue' => $queue,
                'consumerId' => $stats->getConsumerId(),
            ];

            $values = [
                'startedAtMs' => $stats->getStartedAtMs(),
                'started' => $stats->isStarted(),
                'finished' => $stats->isFinished(),
                'failed' => $stats->isFailed(),
                'received' => $stats->getReceived(),
                'acknowledged' => $stats->getAcknowledged(),
                'rejected' => $stats->getRejected(),
                'requeued' => $stats->getRequeued(),
                'memoryUsage' => $stats->getMemoryUsage(),
                'systemLoad' => $stats->getSystemLoad(),
            ];

            if ($stats->getFinishedAtMs()) {
                $values['finishedAtMs'] = $stats->getFinishedAtMs();
            }

            $points[] = new Point($this->config['measurementConsumers'], null, $tags, $values, $stats->getTimestampMs());
        }

        $this->doWrite($points);
    }

    protected function parseDsn(string $dsn): array
    {
        $dsn = Dsn::parseFirst($dsn);

        if (false === in_array($dsn->getSchemeProtocol(), ['influxdb'], true)) {
            throw new \LogicException(sprintf(
                'The given scheme protocol "%s" is not supported. It must be "influxdb"',
                $dsn->getSchemeProtocol()
            ));
        }

        return array_filter(array_replace($dsn->getQuery(), [
            'host' => $dsn->getHost(),
            'port' => $dsn->getPort(),
            'user' => $dsn->getUser(),
            'password' => $dsn->getPassword(),
            'db' => $dsn->getString('db'),
            'measurementSentMessages' => $dsn->getString('measurementSentMessages'),
            'measurementConsumedMessages' => $dsn->getString('measurementConsumedMessages'),
            'measurementConsumers' => $dsn->getString('measurementConsumers'),
            'retentionPolicy' => $dsn->getString('retentionPolicy'),
        ]), function ($value) {
            return null !== $value;
        });
    }

    protected function doWrite(array $points): void
    {
        if (! config('queue.connections.rabbitmq.monitor.enabled')) {
            return;
        }

        if (null === $this->client) {
            $this->client = new Client(
                $this->config['host'],
                $this->config['port'],
                $this->config['user'],
                $this->config['password']
            );
        }

        if ($this->client->getDriver() instanceof QueryDriverInterface) {
            if (null === $this->database) {
                $this->database = $this->client->selectDB($this->config['db']);
                $this->database->create();
            }

            $this->database->writePoints($points, Database::PRECISION_MILLISECONDS, $this->config['retentionPolicy']);
        } else {
            // Code below mirrors what `writePoints` method of Database does.
            try {
                $parameters = [
                    'url' => sprintf('write?db=%s&precision=%s', $this->config['db'], Database::PRECISION_MILLISECONDS),
                    'database' => $this->config['db'],
                    'method' => 'post',
                ];
                if (null !== $this->config['retentionPolicy']) {
                    $parameters['url'] .= sprintf('&rp=%s', $this->config['retentionPolicy']);
                }

                $this->client->write($parameters, $points);
            } catch (\Exception $e) {
                throw new InfluxDBException($e->getMessage(), $e->getCode());
            }
        }
    }
}
