<?php

namespace OA;

use Doctrine\Common\Annotations\Annotation\Target;
use Illuminate\Database\Eloquent\Model;

/**
 * @Annotation
 * @Target({"CLASS", "ANNOTATION"})
 * @deprecated
 */
class ParamObject extends BaseAnnotation
{
    /** @var string */
    public $class;

    public $create = true;

    /**
     * RequestParam constructor.
     * @param array $values
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

    /**
     * @inheritdoc
     */
    public function toArray()
    {
        return $this->createObject()->toArray();
    }

    /**
     * @return Model
     */
    protected function createObject()
    {
        /** @var Model $className */
        $className = $this->class;
        if ($this->create) {
            return factory($className)->create()->refresh();
        } else {
            return $className::query()->first();
        }
    }
}
