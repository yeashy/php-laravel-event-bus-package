<?php

declare(strict_types=1);

namespace Egal\LaravelEventBus;

class Event
{

    protected string $key;

    protected array $data;

    public function __construct(string $key, array $data)
    {
        $this->key = $key;
        $this->data = $data;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getData(): array
    {
        return $this->data;
    }

}