<?php

namespace OA;

use Doctrine\Common\Annotations\Annotation\Attribute;
use Doctrine\Common\Annotations\Annotation\Target;

/**
 * Used to describe request body FormRequest class
 *
 * @Annotation
 * @Target({"CLASS"})
 * @Attributes({
 *   @Attribute("description", type="string"),
 *   @Attribute("contentType", type="string"),
 * })
 * @internal
 */
class RequestBody extends BaseAnnotation
{
    public $description;

    public $contentType = 'application/json';

    public $content;

    public function __construct(array $values)
    {
        $this->configureSelf($values, 'content');
    }

    /**
     * Get object string representation
     * @return string
     */
    public function __toString()
    {
        return $this->description;
    }
}
