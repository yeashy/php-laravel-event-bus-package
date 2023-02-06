<?php

declare(strict_types=1);

namespace Egal\LaravelEventBus;

class Listener
{

    /**
     * @var callable
     */
    protected $callback;

    protected string $eventKey;

    public function __construct(
        string   $eventKey,
        callable $callback,
    )
    {
        $this->callback = $callback;
        $this->eventKey = $eventKey;
    }

    public function handle(Event $event): void
    {
        $callback = $this->callback;

        $callback($event);
    }

    public function getEventKey(): string
    {
        return $this->eventKey;
    }

}