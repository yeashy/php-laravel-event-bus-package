<?php

declare(strict_types=1);

namespace Egal\LaravelEventBus;

use PhpAmqpLib\Channel\AbstractChannel;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AbstractConnection;
use PhpAmqpLib\Connection\AMQPConnectionFactory;
use PhpAmqpLib\Exchange\AMQPExchangeType;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;

class RabbitMQEventBus extends AbstractEventBus
{

    private AbstractConnection $connection;

    private AbstractChannel|AMQPChannel $channel;

    private string $queue;

    /**
     * @var Listener[]
     */
    private array $listeners;

    private string $exchange;

    public function __construct(array $connection)
    {
        $this->connection = AMQPConnectionFactory::create($connection['config']);
        $this->queue = $connection['queue_name'];
        $this->exchange = 'amq.' . AMQPExchangeType::FANOUT;

        $this->channel = $this->connection->channel();

        $this->channel->queue_declare(
            queue: $this->queue,
            passive: false,
            durable: true,
            exclusive: false,
            auto_delete: false,
            nowait: false,
            arguments: new AMQPTable(['x-queue-mode' => 'default']),
        );
        $this->channel->queue_bind($this->queue, $this->exchange);
    }

    public function applyBasicConsume(): void
    {
        $this->channel->basic_qos(
            prefetch_size: null,
            prefetch_count: 1,
            a_global: null,
        );

        $this->channel->basic_consume(
            queue: $this->queue,
            consumer_tag: '',
            no_local: true,
            no_ack: false,
            exclusive: false,
            nowait: false,
            callback: fn(AMQPMessage $message) => $this->processMessage($message),
        );
    }

    public function listen(): void
    {
        $this->applyBasicConsume();

        while ($this->channel->is_open()) {
            $this->channel->wait();
        }
    }

    public function consume(Listener $listener): void
    {
        $this->listeners[] = $listener;
    }

    protected function processMessage(AMQPMessage $message): void
    {
        $event = new Event(
            $message->getRoutingKey(),
            json_decode($message->getBody(), true)
        );

        if ($event->getKey() === $message->getRoutingKey()) {
            $expectedListeners = array_filter(
                $this->listeners,
                fn(Listener $listener) => $listener->getEventKey() === $event->getKey()
            );

            array_map(
                fn(Listener $listener) => $listener->handle($event),
                $expectedListeners,
            );
        }

        $message->ack();
    }

    public function dispatch(Event $event): void
    {
        $this->channel->basic_publish(
            new AMQPMessage(json_encode($event->getData()), [
                'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
            ]),
            $this->exchange,
            $event->getKey()
        );
    }

}