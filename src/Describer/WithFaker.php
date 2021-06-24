<?php

namespace DigitSoft\Swagger\Describer;

/**
 * Trait WithFaker
 */
trait WithFaker
{
    protected $faker;

    /**
     * Get faker instance
     *
     * @return \Faker\Generator
     */
    protected function faker()
    {
        if ($this->faker === null) {
            $this->faker = \Faker\Factory::create();
        }

        return $this->faker;
    }
}
