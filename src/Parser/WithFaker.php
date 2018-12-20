<?php

namespace DigitSoft\Swagger\Parser;

use Faker\Factory;
use Faker\Generator;

/**
 * Trait WithFaker
 * @package DigitSoft\Swagger\Parser
 */
trait WithFaker
{
    protected static $faker;

    /**
     * Get faker instance
     * @return Generator
     */
    protected static function faker()
    {
        if (static::$faker === null) {
            static::$faker = Factory::create();
        }
        return static::$faker;
    }
}
