<?php

declare(strict_types=1);

namespace Egal\LaravelEventBus;

use Exception;

class EventBusFactory
{

    public static function create(array $config): AbstractEventBus
    {
        $name = $config['default'];
        $connection = $config['connections'][$name];

        if (!$connection) {
            throw new Exception("Event Bus connection config `{$name}` not found");
        }

        $driver = $connection['driver'];

        $class = match ($driver) {
            'rabbitmq' => RabbitMQEventBus::class,
        };

        if (!$class) {
            throw new Exception("Event Bus connection driver `{$driver}` not found");
        }

        return new $class($connection);
    }

}