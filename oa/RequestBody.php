<?php

namespace OA;

use Doctrine\Common\Annotations\Annotation;
use Doctrine\Common\Annotations\Annotation\Target;
use Doctrine\Common\Annotations\Annotation\Attribute;

/**
 * Used to describe request body FormRequest class
 *
 * @Annotation
 * @Target({"CLASS", "METHOD"})
 * @Attributes({
 *   @Attribute("description", type="string"),
 *   @Attribute("contentType", type="string"),
 * })
 * @internal
 */
class RequestBody extends BaseAnnotation
{
    public ?string $description = null;

    public string $contentType = 'application/json';

    public mixed $content;

    public function __construct(array $values)
    {
        $this->configureSelf($values, 'content');
    }

    /**
     * Get object string representation
     *
     * @return string
     */
    public function __toString()
    {
        return (string)$this->description;
    }
}
