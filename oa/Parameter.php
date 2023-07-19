<?php

namespace OA;

use Doctrine\Common\Annotations\Annotation;
use Doctrine\Common\Annotations\Annotation\Target;
use Doctrine\Common\Annotations\Annotation\Attribute;
use Doctrine\Common\Annotations\Annotation\Attributes;

/**
 * Used to declare controller action parameter
 *
 * @Annotation
 * @Target({"METHOD", "CLASS", "ANNOTATION"})
 * @Attributes({
 *   @Attribute("name",type="string"),
 *   @Attribute("type",type="string"),
 *   @Attribute("in",type="string"),
 *   @Attribute("style",type="string"),
 *   @Attribute("description",type="string"),
 * })
 */
class Parameter extends BaseValueDescribed
{
    /**
     * @Enum({"path", "query", "header"})
     * @var string Swagger parameter position
     */
    public string $in = 'path';
    /**
     * @Enum({"string", "integer", "number", "boolean", "array", "object"})
     * @var string|null Swagger or PHP type
     */
    public ?string $type = 'integer';

    public ?bool $required = true;

    /**
     * @Enum({"simple", "matrix", "label", "form", "spaceDelimited", "pipeDelimited", "deepObject"})
     */
    public ?string $style = null;

    /**
     * @Enum({true, false})
     * @var boolean
     */
    public ?bool $explode = null;

    /**
     * @inheritdoc
     */
    public function toArray(): array
    {
        $data = parent::toArray();
        $data['in'] = $this->in;
        // Path parameters are always required
        $data['required'] = $this->in === 'path' ? true : $this->required;
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
    protected function getDumpedKeys(): array
    {
        return [
            'name', 'type', 'format', 'description',
            'required', 'enum', 'example',
        ];
    }

    /**
     * @inheritdoc
     */
    protected function isSchemaTypeUsed(): bool
    {
        return true;
    }

    /**
     * Get parameter style.
     *
     * @param  string      $in
     * @param  string|null $type
     * @return string|null
     */
    protected function getStyle(string $in, ?string $type): ?string
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
     *
     * @return array
     */
    protected static function getDefaultStyles(): array
    {
        return [
            // 'in.type' => 'style',
            // 'in.*' => 'style',
            'query.array' => 'form',
        ];
    }
}
