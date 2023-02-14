<?php

declare(strict_types=1);

namespace Egal\LaravelEventBus;

class Listener
{

    /**
     * @var callable
     */
    protected $callback;

    protected string $key;

    public function __construct(
        string   $key,
        callable $callback,
    )
    {
        $this->callback = $callback;
        $this->key = $key;
    }

    public function handle(Event $event): void
    {
        $callback = $this->callback;

        $callback($event);
    }

    public function getKey(): string
    {
        return $this->key;
    }

}