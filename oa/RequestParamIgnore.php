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
final class RequestParamIgnore extends BaseAnnotation
{
    /**
     * @var string name of the request parameter to ignore
     */
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
            throw new \RuntimeException(sprintf("You must set a \$name for '%s' annotation", __CLASS__));
        }

        return $this->name;
    }
}

