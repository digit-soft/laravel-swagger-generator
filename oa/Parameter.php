<?php

namespace OA;

use Doctrine\Common\Annotations\Annotation\Attribute;
use Doctrine\Common\Annotations\Annotation\Attributes;

/**
 * Used to declare controller action parameter
 *
 * @Annotation
 * @Attributes({
 *   @Attribute("name", type="string"),
 *   @Attribute("type", type="string"),
 *   @Attribute("in", type="string"),
 *   @Attribute("description", type="string"),
 * })
 */
class Parameter extends BaseAnnotation
{
    public $in = 'path';

    public $name;

    public $type = 'integer';

    public $description;

    public $required = true;

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
            'in' => $this->in,
            'description' => $this->description ?? '',
            'required' => $this->required,
            'schema' => [
                'type' => $this->type,
            ],
        ];
        if (($example = $this->getExample($this->type)) !== null) {
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
     * Get type example
     * @param string $type
     * @return mixed|null
     */
    protected function getExample($type = 'integer')
    {
        $examples = [
            'integer' => 1,
            'string' => 'string',
            'float' => 15.00,
            'boolean' => true,
        ];
        return $examples[$type] ?? null;
    }
}
