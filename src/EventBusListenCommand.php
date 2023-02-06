<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Egal\LaravelEventBus\EventBus;
use Illuminate\Console\Command;

class EventBusListenCommand extends Command
{

    /**
     * @var string
     */
    protected $signature = 'event-bus:listen';

    /**
     * @var string
     */
    protected $description = 'Event Bus listen';

    public function handle(): void
    {
        EventBus::listen();
    }

}
