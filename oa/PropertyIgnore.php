<?php

namespace OA;

use Doctrine\Common\Annotations\Annotation;
use Doctrine\Common\Annotations\Annotation\Attribute;
use Doctrine\Common\Annotations\Annotation\Attributes;
use Doctrine\Common\Annotations\Annotation\Target;

/**
 * Used to ignore class property by name.
 *
 * @Annotation
 * @Target({"CLASS"})
 * @Attributes({
 *   @Attribute("name",type="string"),
 * })
 */
final class PropertyIgnore extends BaseAnnotation
{
    /** @var string property name to ignore */
    public $name;

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
        return $this->name;
    }
}
