<?php

namespace OA;

use Doctrine\Common\Annotations\Annotation;
use Doctrine\Common\Annotations\Annotation\Attribute;
use Doctrine\Common\Annotations\Annotation\Attributes;
use Doctrine\Common\Annotations\Annotation\Target;

/**
 * Used in annotations (RequestBody) to describe request body parameter
 *
 * @Annotation
 * @Target({"CLASS", "ANNOTATION", "METHOD"})
 * @Attributes({
 *   @Attribute("type",type="string"),
 *   @Attribute("name",type="string"),
 *   @Attribute("description",type="string"),
 *   @Attribute("format",type="string"),
 * })
 */
class RequestParam extends BaseValueDescribed
{
    public $required = false;
    /**
     * @Enum({"string", "integer", "numeric", "boolean", "array", "object"})
     * @var string Swagger or PHP type
     */
    public $type = 'string';

    /**
     * @inheritdoc
     */
    protected function isExampleRequired()
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    protected function getExcludedKeys()
    {
        return ['name'];
    }

    /**
     * @inheritdoc
     */
    protected function getExcludedEmptyKeys()
    {
        return ['required'];
    }
}
