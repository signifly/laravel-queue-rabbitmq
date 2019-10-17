<?php

namespace Signifly\LaravelQueueRabbitMQ\Queue\Tools;

interface PrioritizeAware
{
    public function setPrioritize(?bool $prioritize = null);
}
