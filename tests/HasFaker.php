<?php

declare(strict_types=1);

namespace Egal\LaravelEventBus\Tests;

use Faker\Factory as FakerFactory;
use Faker\Generator;

trait HasFaker
{

    protected Generator $faker;

    protected function setUpFaker(): void
    {
        $this->faker = FakerFactory::create();
    }

}