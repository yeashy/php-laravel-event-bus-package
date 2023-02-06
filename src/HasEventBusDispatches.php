<?php

declare(strict_types=1);

namespace Egal\LaravelEventBus;

use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * @mixin Model
 */
trait HasEventBusDispatches
{

    protected function getShortName(): string
    {
        return $this->shortName ?? Str::snake(basename(str_replace('\\', '/', static::class)));
    }

    protected function fireModelEvent($event, $halt = true)
    {
        parent::fireModelEvent($event, $halt);

        if (!$this instanceof Model) {
            $class = static::class;
            $model = Model::class;
            throw new Exception("$class must be instance of $model");
        }

        $service = config('app.service_name', config('app.name'));
        $model = $this->getShortName();

        $event = new ModelEvent("$service.$model.$event", $this);

        EventBus::dispatch($event);
    }

}