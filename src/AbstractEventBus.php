<?php

declare(strict_types=1);

namespace Egal\LaravelEventBus;

abstract class AbstractEventBus
{

    abstract public function listen(): void;

    abstract public function consume(Listener $listener): void;

    abstract public function dispatch(Event $event): void;

    /**
     * @throws EventNotCaughtException
     */
    abstract public function wait(string $eventKey): array;

}