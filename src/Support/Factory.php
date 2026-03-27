<?php

namespace Crescat\SaloonSdkGenerator\Support;

use Faker\Factory as FakerFactory;
use Faker\Generator;

abstract class Factory
{
    protected Generator $faker;

    public function __construct()
    {
        $this->faker = FakerFactory::create();
    }

    /**
     * Define the factory's default state.
     */
    abstract protected function definition(): array;

    /**
     * Create a new instance of the factory.
     */
    public static function new(): static
    {
        return new static;
    }

    /**
     * Make a single instance using the factory definition.
     */
    public function make(): array
    {
        return $this->definition();
    }
}
