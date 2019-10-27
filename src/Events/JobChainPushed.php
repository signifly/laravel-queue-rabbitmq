<?php

namespace Signifly\LaravelQueueRabbitMQ\Events;

use Illuminate\Contracts\Queue\Job;

class JobChainPushed
{
    public $job;
    public $newId;

    public function __construct(Job $job, string $newId)
    {
        $this->job = $job;
        $this->newId = $newId;
    }
}
