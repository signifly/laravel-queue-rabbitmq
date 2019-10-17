<?php

namespace Signifly\LaravelQueueRabbitMQ\Tests\Strategies;

use PHPUnit\Framework\TestCase;
use Signifly\LaravelQueueRabbitMQ\Queue\Strategies\ExponentialBackoffStrategy;

class ExponentialBackoffStrategyTest extends TestCase
{
    /**
     * @dataProvider exponentialDataProvider
     */
    public function testShouldCalculateDelayCorrectly($delay, $attempt, $expected)
    {
        $strategy = new ExponentialBackoffStrategy();

        $this->assertEquals($expected, $strategy->backoffDelayTime($delay, $attempt));
    }

    public function exponentialDataProvider()
    {
        return [
            'Delay 1, Attempt 1' => [1, 1, 1],
            'Delay 1, Attempt 2' => [1, 2, 4],
            'Delay 1, Attempt 3' => [1, 3, 8],
            'Delay 2, Attempt 1' => [2, 1, 2],
            'Delay 2, Attempt 2' => [2, 2, 8],
            'Delay 2, Attempt 3' => [2, 3, 16],
        ];
    }
}
