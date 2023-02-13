<?php

declare(strict_types=1);

namespace Egal\LaravelEventBus\Tests;

use Egal\LaravelEventBus\Event;
use Egal\LaravelEventBus\EventBusFactory;
use Egal\LaravelEventBus\EventNotCaughtException;
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
        $config['connections']['rabbitmq']['wait_timeout'] = 0.1;

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
    public function testListen(array $config)
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

    /**
     * @dataProvider dataProvider
     */
    public function testWait(array $config)
    {
        $bus = EventBusFactory::create($config);

        if ($bus instanceof RabbitMQEventBus) {
            $bus->upWaitQueue();
        }

        $event = new Event(
            $this->faker->uuid,
            [$this->faker->colorName => $this->faker->hexColor],
        );

        $bus->dispatch($event);

        $data = $bus->wait($event->getKey());

        $this->assertEquals($event->getData(), $data);
    }

    /**
     * @dataProvider dataProvider
     */
    public function testWaitWithoutCaught(array $config)
    {
        $bus = EventBusFactory::create($config);

        $this->expectException(EventNotCaughtException::class);

        $bus->wait($this->faker->uuid);
    }

}

class EventBusTestException extends Exception
{

}