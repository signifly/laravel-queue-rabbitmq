<?php

namespace Signifly\LaravelQueueRabbitMQ\Events;

use Illuminate\Contracts\Queue\Job;

class JobRetry
{
    public $job;
    public $newId;

    public function __construct(Job $job, $newId)
    {
        $this->job = $job;
        $this->newId = $newId;
    }
}
