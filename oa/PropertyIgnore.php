<?php

namespace OA;

use Doctrine\Common\Annotations\Annotation;
use Doctrine\Common\Annotations\Annotation\Target;
use Doctrine\Common\Annotations\Annotation\Attribute;
use Doctrine\Common\Annotations\Annotation\Attributes;

/**
 * Used to ignore class property by name.
 *
 * @Annotation
 * @Target("CLASS")
 * @Attributes({
 *   @Attribute("name", type="string"),
 * })
 */
final class PropertyIgnore extends BaseAnnotation
{
    /** @var string property name to ignore */
    public string $name;

    public function __construct(array $values)
    {
        $this->configureSelf($values, 'name');
    }

    /**
     * Get object string representation
     *
     * @return string
     */
    public function __toString()
    {
        if (! isset($this->name)) {
            throw new \RuntimeException("You must set a \$name for 'OA\PropertyIgnore' annotation");
        }

        return $this->name;
    }
}
