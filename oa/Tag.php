<?php

namespace OA;

use Doctrine\Common\Annotations\Annotation;
use Doctrine\Common\Annotations\Annotation\Attribute;
use Doctrine\Common\Annotations\Annotation\Attributes;
use Doctrine\Common\Annotations\Annotation\Target;

/**
 * Used to mark controller method with given tag.
 * Can be used on controller itself (all methods will have those tags).
 *
 * @Annotation
 * @Target({"METHOD", "CLASS"})
 * @Attributes({
 *   @Attribute("name",type="string"),
 * })
 */
class Tag extends BaseAnnotation
{
    public string $name;

    /**
     * Tag constructor.
     * @param array $values
     */
    public function __construct(array $values)
    {
        $this->configureSelf($values, 'name');
    }

    /**
     * Get object string representation
     * @return string
     */
    public function __toString()
    {
        if (! isset($this->name)) {
            throw new \RuntimeException("'OA\Tag::\$name' is required");
        }

        return preg_replace('/[\s_]+/u', '-', $this->name);
    }
}
