<?php

namespace OA;

use Doctrine\Common\Annotations\Annotation;
use Doctrine\Common\Annotations\Annotation\Attributes;
use Doctrine\Common\Annotations\Annotation\Attribute;

/**
 * Symlink to link class reader to another one
 *
 * @Annotation()
 * @Attributes({
 *  @Attribute("class",type="string"),
 *  @Attribute("merge",type="boolean",required=false),
 * })
 */
class Symlink extends BaseAnnotation
{
    /**
     * @var string
     */
    public $class;
    /**
     * @var boolean
     */
    public $merge = false;

    /**
     * Symlink constructor.
     * @param  array $values
     */
    public function __construct(array $values)
    {
        $this->configureSelf($values, 'class');
    }

    /**
     * Get object string representation
     * @return string
     */
    public function __toString()
    {
        return $this->class;
    }
}
