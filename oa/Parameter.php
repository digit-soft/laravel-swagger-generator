<?php

namespace OA;

use Doctrine\Common\Annotations\Annotation;
use Doctrine\Common\Annotations\Annotation\Attribute;
use Doctrine\Common\Annotations\Annotation\Attributes;
use Doctrine\Common\Annotations\Annotation\Target;
use Illuminate\Support\Arr;

/**
 * Used to declare controller action parameter
 *
 * @Annotation
 * @Target({"METHOD", "CLASS"})
 * @Attributes({
 *   @Attribute("name",type="string"),
 *   @Attribute("type",type="string"),
 *   @Attribute("in",type="string"),
 *   @Attribute("description",type="string"),
 * })
 */
class Parameter extends BaseValueDescribed
{
    /**
     * @Enum({"path", "query"})
     * @var string Swagger parameter position
     */
    public $in = 'path';
    /**
     * @Enum({"string", "integer", "number", "boolean", "array", "object"})
     * @var string Swagger or PHP type
     */
    public $type = 'integer';

    public $required = true;

    /**
     * @inheritdoc
     */
    public function toArray()
    {
        $data = parent::toArray();
        $data['in'] = $this->in;
        Arr::set($data, 'schema.type', $data['type']);
        Arr::forget($data, 'type');
        return $data;
    }
}
