<?php

declare(strict_types=1);

namespace Egal\LaravelEventBus;

use Exception;
use Illuminate\Support\Str;
use PhpAmqpLib\Channel\AbstractChannel;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AbstractConnection;
use PhpAmqpLib\Connection\AMQPConnectionFactory;
use PhpAmqpLib\Exception\AMQPTimeoutException;
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

    private int|float $waitTimeout;

    private string $waitQueue;

    private int $maxConnectionRetries;

    private float $connectionRetriesTimeout;

    public function __construct(array $connection)
    {
        $this->connection = AMQPConnectionFactory::create($connection['config']);
        $this->queue = $connection['queue_name'];
        $this->waitTimeout = $connection['wait_timeout'];
        $this->exchange = 'amq.' . AMQPExchangeType::FANOUT;
        $this->maxConnectionRetries = $connection['max_connection_retries'];
        $this->connectionRetriesTimeout = $connection['connection_retries_timeout'];
        $this->connect();
    }

    public function connect(): void
    {
        $failingStreak = 0;

        if (isset($this->channel) && $this->testConnectionMessage()) return;

        while (true) {
            try {
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

                break;
            } catch (\Exception $exception) {
                $failingStreak++;
                usleep((int)($this->connectionRetriesTimeout * 1000) * $failingStreak);
            }

            if ($failingStreak >= $this->maxConnectionRetries) {
                throw new ConnectionFailedException("Failed connect to RabbitMQ!");
            }

        }
    }

    public function applyBasicConsume(): void
    {
        $this->connect();

        $this->channel->basic_qos(
            prefetch_size: 0,
            prefetch_count: 1,
            a_global: false,
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

        $expectedListeners = array_filter(
            $this->listeners,
            fn(Listener $listener) => $this->isKeyMatched(
                $listener->getKey(),
                $event->getKey()
            )
        );

        array_map(
            fn(Listener $listener) => $listener->handle($event),
            $expectedListeners,
        );

        $message->ack();
    }

    public function dispatch(Event $event): void
    {
        $this->connect();

        $this->channel->basic_publish(
            new AMQPMessage(json_encode($event->getData()), [
                'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
            ]),
            $this->exchange,
            $event->getKey()
        );

    }

    /**
     * @throws EventNotCaughtException
     */
    public function wait(string $key): array
    {
        $this->connect();
        $this->upWaitQueue();

        $result = null;
        $mustDieAt = microtime(true) + $this->waitTimeout;

        $processor = function (AMQPMessage $message) use (&$result, $key) {
            if ($this->isKeyMatched($key, $message->getRoutingKey())) {
                $result = json_decode($message->getBody(), true);
            }

            $message->ack();
        };

        $channel = $this->connection->channel();
        $channel->basic_qos(
            prefetch_size: 0,
            prefetch_count: 1,
            a_global: false,
        );
        $channel->basic_consume(
            queue: $this->waitQueue,
            no_local: true,
            exclusive: true,
            callback: $processor,
        );

        while (
            microtime(true) < $mustDieAt
            && $result === null
            && $channel->is_open()
        ) {
            try {
                $channel->wait(timeout: $this->waitTimeout);
            } catch (AMQPTimeoutException $exception) {
            }
        }

        if ($result === null) {
            throw new EventNotCaughtException();
        }

        $this->downWaitQueue();
        $channel->close();

        return $result;
    }

    public function upWaitQueue(): void
    {
        if (isset($this->waitQueue)) {
            return;
        }
        $this->connect();

        $this->waitQueue = $queue = Str::uuid()->toString();

        $channel = $this->connection->channel();
        $channel->queue_declare(
            queue: $queue,
            exclusive: true,
            arguments: new AMQPTable(['x-queue-mode' => 'default']),
        );
        $channel->queue_bind($queue, $this->exchange);
    }

    public function downWaitQueue(): void
    {
        unset($this->waitQueue);
    }

    private function testConnectionMessage(): bool
    {
        try {
            $exchangeUUID = Str::uuid()->toString();

            $temporaryExchange = $this->channel->exchange_declare(
                $exchangeUUID,
                AMQPExchangeType::DIRECT,
            );

            $this->channel->basic_publish(
                new AMQPMessage(json_encode(['message' => 'test']), [
                    'delivery_mode' => AMQPMessage::DELIVERY_MODE_NON_PERSISTENT,
                ]),
                $temporaryExchange,
                $exchangeUUID
            );

            $this->channel->exchange_delete($exchangeUUID);
            return true;
        } catch (Exception) {
            return false;
        }

    }

}
