<?php

declare(strict_types=1);

namespace Egal\LaravelEventBus;

use Illuminate\Support\Facades\Facade;

/**
 * @method static listen(): void;
 * @method static consume(Listener $listener): void;
 * @method static dispatch(Event $event): void;
 */
class EventBus extends Facade
{

    /**
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return AbstractEventBus::class;
    }

}