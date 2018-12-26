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
 * @Target({"CLASS", "ANNOTATION"})
 * @Attributes({
 *   @Attribute("type",type="string"),
 *   @Attribute("name",type="string"),
 *   @Attribute("description",type="string"),
 *   @Attribute("format",type="string"),
 * })
 */
class RequestParam extends BaseValueDescribed
{
    public $required = true;

    public $type = 'string';

    protected $exampleRequired = true;

    protected $excludeKeys = ['name'];
}
