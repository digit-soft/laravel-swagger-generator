<?php

namespace DigitSoft\Swagger\Describer;

use Faker\Factory;
use Faker\Generator;

/**
 * Trait WithFaker
 * @package DigitSoft\Swagger\Describer
 */
trait WithFaker
{
    protected $faker;

    /**
     * Get faker instance
     * @return Generator
     */
    protected function faker()
    {
        if ($this->faker === null) {
            $this->faker = Factory::create();
        }
        return $this->faker;
    }
}
