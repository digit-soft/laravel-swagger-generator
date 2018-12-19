<?php

namespace OA;

use DigitSoft\Swagger\DumperYaml;
use Doctrine\Common\Annotations\Annotation\Attribute;
use Doctrine\Common\Annotations\Annotation\Attributes;
use Doctrine\Common\Annotations\Annotation\Target;
use Illuminate\Support\Arr;

/**
 * @Annotation
 * @Target({"CLASS"})
 * @Attributes({
 *   @Attribute("name",type="string"),
 *   @Attribute("type",type="string"),
 *   @Attribute("description",type="string"),
 * })
 */
class Property extends BaseAnnotation
{
    /**
     * @var mixed
     */
    public $example;
    /**
     * @var string
     */
    public $name;
    /**
     * @var string
     */
    public $type;
    /**
     * @var string
     */
    public $description;

    /**
     * Parameter constructor.
     * @param $values
     */
    public function __construct($values)
    {
        $this->configureSelf($values, 'name');
    }

    /**
     * @inheritdoc
     */
    public function toArray()
    {
        $data = [
            'name' => $this->name,
            'type' => $this->getType(),
        ];
        if ($this->description !== null) {
            $data['description'] = $this->description;
        }
        if (($example = $this->getExample()) !== null) {
            $data['example'] = $example;
        }
        return $data;
    }

    /**
     * Get object string representation
     * @return string
     */
    public function __toString()
    {
        return $this->name;
    }

    /**
     * Get example
     * @return mixed
     */
    protected function getExample()
    {
        if ($this->example === null && $this->type !== null) {
            $this->example = DumperYaml::getExampleValue($this->type, $this->name);
        }
        return $this->example;
    }

    /**
     * Get type
     * @return string|null
     */
    protected function getType()
    {
        if ($this->type === null && $this->example !== null) {
            $this->type = gettype($this->example);
            if (is_array($this->example) && Arr::isAssoc($this->example)) {
                $this->type = 'object';
            }
        }
        return $this->type;
    }
}
