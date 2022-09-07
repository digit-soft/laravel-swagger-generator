<?php

namespace DigitSoft\Swagger\Parser;

use DigitSoft\Swagger\VariableDescriberService;

/**
 * Trait WithVariableDescriber.
 */
trait WithVariableDescriber
{
    protected VariableDescriberService $describer;

    /**
     * Get describer instance
     *
     * @return VariableDescriberService
     */
    protected function describer(): VariableDescriberService
    {
        if (! isset($this->describer)) {
            $this->describer = app('swagger.describer');
        }

        return $this->describer;
    }
}
