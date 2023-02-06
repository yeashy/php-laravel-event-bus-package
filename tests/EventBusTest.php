<?php

declare(strict_types=1);

namespace Egal\LaravelEventBus\Tests;

use Egal\LaravelEventBus\Event;
use Egal\LaravelEventBus\EventBusFactory;
use Egal\LaravelEventBus\Listener;
use Egal\LaravelEventBus\RabbitMQEventBus;
use Exception;
use PHPUnit\Framework\TestCase;

class EventBusTest extends TestCase
{

    use HasFaker;

    public function __construct(?string $name = null, array $data = [], $dataName = '')
    {
        parent::__construct($name, $data, $dataName);
        $this->setUpFaker();
    }

    public function dataProvider(): array
    {
        $config = require __DIR__ . '/../stubs/config.stub';

        $configs = [];

        $config['connections']['rabbitmq']['queue_name'] = $this->faker->uuid;

        foreach ($config['connections'] as $name => $connection) {
            $cfg = $config;
            $cfg['default'] = $name;
            $configs[$name] = [$cfg];
        }

        return $configs;
    }

    /**
     * @dataProvider dataProvider
     */
    public function test(array $config)
    {
        $bus = EventBusFactory::create($config);

        $dispatchedEvent = new Event(
            $this->faker->uuid,
            [$this->faker->colorName => $this->faker->hexColor],
        );

        $bus->dispatch($dispatchedEvent);

        $bus->consume(
            new Listener(
                $dispatchedEvent->getKey(),
                function (Event $event) use ($dispatchedEvent) {
                    $this->assertEquals($event->getData(), $dispatchedEvent->getData());
                    $this->assertEquals($event->getKey(), $dispatchedEvent->getKey());
                    throw new EventBusTestException();
                },
            )
        );

        try {
            $bus->listen();
        } catch (EventBusTestException $exception) {
            $this->assertTrue(true);
        }

        $this->assertEquals(3, $this->getCount());
    }

}

class EventBusTestException extends Exception
{

}