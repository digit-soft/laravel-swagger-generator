<?php

namespace OA;

use DigitSoft\Swagger\DumperYaml;
use Doctrine\Common\Annotations\Annotation\Attribute;
use Doctrine\Common\Annotations\Annotation\Attributes;
use Doctrine\Common\Annotations\Annotation\Target;
use Illuminate\Support\Arr;

/**
 * Used to describe class property.
 *
 * @Annotation
 * @Target({"CLASS"})
 * @Attributes({
 *   @Attribute("name",type="string"),
 *   @Attribute("type",type="string"),
 *   @Attribute("description",type="string"),
 * })
 */
class Property extends BaseValueDescribed
{
    protected $exampleRequired = true;

    protected $excludeKeys = ['required'];
}
