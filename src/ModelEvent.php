<?php

declare(strict_types=1);

namespace Egal\LaravelEventBus;

use Illuminate\Database\Eloquent\Model;

class ModelEvent extends Event
{

    public function __construct(string $key, Model $model)
    {
        $data = $model->attributesToArray();
        if ($model->isDirty()) {
            $data['original'] = $model->getOriginal();
        }

        parent::__construct($key, $data);
    }

}
