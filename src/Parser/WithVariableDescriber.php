<?php

namespace DigitSoft\Swagger\Parser;

use DigitSoft\Swagger\VariableDescriberService;

/**
 * Trait WithVariableDescriber.
 */
trait WithVariableDescriber
{
    protected $describer;

    /**
     * Get describer instance
     *
     * @return VariableDescriberService
     */
    protected function describer()
    {
        if ($this->describer === null) {
            $this->describer = app('swagger.describer');
        }

        return $this->describer;
    }
}
