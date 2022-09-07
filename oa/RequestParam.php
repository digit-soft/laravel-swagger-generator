<?php

namespace OA;

use Doctrine\Common\Annotations\Annotation;
use Doctrine\Common\Annotations\Annotation\Target;
use Doctrine\Common\Annotations\Annotation\Attribute;
use Doctrine\Common\Annotations\Annotation\Attributes;

/**
 * Used in annotations (RequestBody) to describe request body parameter
 *
 * @Annotation
 * @Target({"CLASS", "ANNOTATION", "METHOD"})
 * @Attributes({
 *   @Attribute("type", type="string"),
 *   @Attribute("name", type="string"),
 *   @Attribute("description", type="string"),
 *   @Attribute("format", type="string"),
 * })
 */
class RequestParam extends BaseValueDescribed
{
    /**
     * @inheritdoc
     */
    protected function isExampleRequired(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    protected function getExcludedKeys(): array
    {
        return ['name'];
    }

    /**
     * @inheritdoc
     */
    protected function getExcludedEmptyKeys(): array
    {
        return ['type'];
    }
}
