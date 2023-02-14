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
    abstract public function wait(string $key): array;

    public function isKeyMatched(string $listenKey, string $eventKey): bool
    {
        return str_contains($listenKey, '*')
            ? (bool)preg_match(
                "/^\." . str_replace('*', '[^\.]+', $listenKey) . "\.$/",
                ".$eventKey."
            )
            : $listenKey === $eventKey;
    }

}