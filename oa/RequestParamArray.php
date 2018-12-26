<?php

namespace OA;

use Doctrine\Common\Annotations\Annotation;
use Doctrine\Common\Annotations\Annotation\Target;

/**
 * Used in annotations (RequestBody) to describe request body array parameter
 *
 * @Annotation
 * @Target({"CLASS", "ANNOTATION"})
 */
class RequestParamArray extends RequestParam
{
    /**
     * RequestParamArray constructor.
     * @param array $values
     */
    public function __construct(array $values)
    {
        $values['type'] = $values['type'] ?? 'array';
        parent::__construct($values);
    }
}
