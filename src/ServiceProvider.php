<?php

declare(strict_types=1);

namespace Egal\LaravelEventBus;

use App\Console\Commands\EventBusListenCommand;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;

abstract class ServiceProvider extends BaseServiceProvider
{

    /**
     * @return void
     */
    public function register()
    {
        $this->app->singleton(
            AbstractEventBus::class,
            fn(): AbstractEventBus => EventBusFactory::create(config("event-bus")),
        );

        if ($this->app->runningInConsole()) {
            $this->commands([
                EventBusListenCommand::class,
            ]);
        }

        $this->registerEvents(app(AbstractEventBus::class));
    }

    abstract protected function registerEvents(EventBus $bus): void;

}