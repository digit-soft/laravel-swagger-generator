<?php
namespace OA;

use Doctrine\Common\Annotations\Annotation;
use Doctrine\Common\Annotations\Annotation\Target;

/**
 * Used to make controller or it`s action ignored
 *
 * @Annotation
 * @Target({"METHOD", "CLASS"})
 */
class Ignore extends BaseAnnotation
{
    /**
     * Get object string representation
     * @return string
     */
    public function __toString()
    {
        return 'ignored';
    }

    /**
     * @inheritDoc
     */
    public function toArray()
    {
        return [];
    }
}
