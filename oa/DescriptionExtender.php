<?php

namespace OA;

/**
 * You can extend this annotation and use it to describe whatever you want,
 * it will be placed in description tag of YAML one per line.
 * @package OA
 */
abstract class DescriptionExtender extends BaseAnnotation
{
    public $value;

    /**
     * DescriptionExtender constructor.
     * @param array $values
     */
    public function __construct(array $values)
    {
        $this->configureSelf($values, 'value');
    }

    /**
     * Get object string representation
     * @return string
     */
    public function __toString()
    {
        return $this->value;
    }
}
