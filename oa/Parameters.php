<?php

namespace OA;

/**
 * Class Parameters
 * @Annotation
 * @package OA
 */
class Parameters extends BaseAnnotation
{
    /**
     * @var Parameter[]
     */
    public $parameters = [];

    /**
     * Parameters constructor.
     * @param $values
     */
    public function __construct($values)
    {
        $this->configureSelf($values, 'parameters');
    }

    /**
     * Get object string representation
     * @return string
     */
    public function __toString()
    {
        return 'Parameters ' . count($this->parameters);
    }

    /**
     * @inheritDoc
     */
    public function toArray(): array
    {
        $data = [];
        foreach ($this->parameters as $parameter) {
            $data[] = $parameter->toArray();
        }
        return $data;
    }
}
