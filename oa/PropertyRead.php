<?php

namespace OA;

use Doctrine\Common\Annotations\Annotation;
use Doctrine\Common\Annotations\Annotation\Target;
use Doctrine\Common\Annotations\Annotation\Attribute;
use Doctrine\Common\Annotations\Annotation\Attributes;

/**
 * Used to describe an only read class property.
 *
 * @Annotation()
 * @Target("CLASS")
 * @Attributes({
 *   @Attribute("name",type="string"),
 *   @Attribute("type",type="string"),
 *   @Attribute("description",type="string"),
 * })
 */
class PropertyRead extends BaseValueDescribed
{
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
        return ['required'];
    }
}
