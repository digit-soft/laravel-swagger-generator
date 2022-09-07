<?php

namespace DigitSoft\Swagger\Describer;

use Faker\Generator;

/**
 * Trait WithFaker
 */
trait WithFaker
{
    protected Generator $faker;

    /**
     * Get faker instance
     *
     * @return \Faker\Generator
     */
    protected function faker(): Generator
    {
        if (! isset($this->faker)) {
            $this->faker = \Faker\Factory::create();
        }

        return $this->faker;
    }
}
