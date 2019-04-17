<?php

namespace OA;

use Doctrine\Common\Annotations\Annotation;
use Doctrine\Common\Annotations\Annotation\Attribute;
use Doctrine\Common\Annotations\Annotation\Attributes;
use Doctrine\Common\Annotations\Annotation\Target;

/**
 * Used to declare controller action parameter
 *
 * @Annotation
 * @Target({"METHOD", "CLASS", "ANNOTATION"})
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
     * @Enum({"simple", "matrix", "label", "form", "spaceDelimited", "pipeDelimited", "deepObject"})
     * @var string
     */
    public $style;

    /**
     * @Enum({true, false})
     * @var boolean
     */
    public $explode;

    /**
     * @inheritdoc
     */
    public function toArray()
    {
        $data = parent::toArray();
        $data['in'] = $this->in;
        $type = $data['schema']['type'] ?? 'string';
        if (($style = $this->getStyle($this->in, $type)) !== null) {
            $data['style'] = $style;
        }
        if ($this->explode !== null) {
            $data['explode'] = $this->explode;
        }
        return $data;
    }

    /**
     * @inheritdoc
     */
    protected function isSchemaTypeUsed()
    {
        return true;
    }

    /**
     * Get param style
     * @param  string $in
     * @param  string $type
     * @return string|null
     */
    protected function getStyle($in, $type)
    {
        if ($this->style !== null) {
            return $this->style;
        }
        $default = static::getDefaultStyles();
        $key = $in . '.' . $type;
        return $default[$key] ?? $default['*'] ?? null;
    }

    /**
     * Get default styles keyed by `in`, `type` params
     * @return array
     */
    protected static function getDefaultStyles()
    {
        return [
            // 'in.type' => 'style',
            // 'in.*' => 'style',
            'query.array' => 'deepObject',
        ];
    }
}
