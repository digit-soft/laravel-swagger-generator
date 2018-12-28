<?php

namespace OA;

use Doctrine\Common\Annotations\Annotation;
use Doctrine\Common\Annotations\Annotation\Attribute;
use Doctrine\Common\Annotations\Annotation\Attributes;
use Doctrine\Common\Annotations\Annotation\Target;

/**
 * Used to describe class property.
 *
 * @Annotation()
 * @Target("CLASS")
 * @Attributes({
 *   @Attribute("name",type="string"),
 *   @Attribute("type",type="string"),
 *   @Attribute("description",type="string"),
 * })
 */
class Property extends BaseValueDescribed
{
    protected $_exampleRequired = true;

    protected $_excludeKeys = ['required'];
}
