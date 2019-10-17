<?php

namespace Signifly\LaravelQueueRabbitMQ\Tests\Strategies;

use PHPUnit\Framework\TestCase;
use Signifly\LaravelQueueRabbitMQ\Queue\Strategies\PolynomialBackoffStrategy;

class PolynomialBackoffStrategyTest extends TestCase
{
    /**
     * @dataProvider polynomialDataProvider
     */
    public function testShouldCalculateDelayCorrectly($delay, $attempt, $factor, $expected)
    {
        $strategy = new PolynomialBackoffStrategy(['factor' => $factor]);

        $this->assertEquals($expected, $strategy->backoffDelayTime($delay, $attempt));
    }

    public function polynomialDataProvider()
    {
        return [
            'Delay 1, Attempt 1, Factor 2' => [1, 1, 2, 1],
            'Delay 1, Attempt 2, Factor 2' => [1, 2, 2, 4],
            'Delay 1, Attempt 3, Factor 2' => [1, 3, 2, 9],
            'Delay 2, Attempt 1, Factor 2' => [2, 1, 2, 2],
            'Delay 2, Attempt 2, Factor 2' => [2, 2, 2, 8],
            'Delay 2, Attempt 3, Factor 2' => [2, 3, 2, 18],
            'Delay 1, Attempt 1, Factor 3' => [1, 1, 3, 1],
            'Delay 1, Attempt 2, Factor 3' => [1, 2, 3, 8],
            'Delay 1, Attempt 3, Factor 3' => [1, 3, 3, 27],
            'Delay 2, Attempt 1, Factor 3' => [2, 1, 3, 2],
            'Delay 2, Attempt 2, Factor 3' => [2, 2, 3, 16],
            'Delay 2, Attempt 3, Factor 3' => [2, 3, 3, 54],
        ];
    }
}
