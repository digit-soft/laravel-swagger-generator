<?php

namespace OA;

use Doctrine\Common\Annotations\Annotation\Target;

/**
 * Used in annotations (RequestBody) to describe request body array parameter
 *
 * @Annotation
 * @Target({"CLASS", "ANNOTATION"})
 */
class RequestParamArray extends RequestParam
{
    public $items = 'string';

    /**
     * RequestParam constructor.
     * @param array $values
     */
    public function __construct(array $values)
    {
        parent::__construct($values);
        $this->type = 'array';
    }

    /**
     * Get object string representation
     * @return string
     */
    public function __toString()
    {
        return $this->name;
    }
}
