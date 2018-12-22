<?php

namespace OA;

/**
 * Used to declare controller action parameters (bulk)
 *
 * @Annotation
 */
class Parameters extends BaseAnnotation
{
    /**
     * @var Parameter[]
     */
    public $value;

    /**
     * Get object string representation
     * @return string
     */
    public function __toString()
    {
        $params = [];
        foreach ($this->value as $parameter) {
            $params[] = $parameter->__toString();
        }
        return json_encode($params);
    }

    /**
     * @inheritdoc
     */
    public function toArray()
    {
        if (!is_array($this->value)) {
            return [];
        }
        $params = [];
        foreach ($this->value as $parameter) {
            $params[] = $parameter->toArray();
        }
        return $params;
    }
}
