<?php

namespace OA;

use Doctrine\Common\Annotations\Annotation\Attribute;
use Doctrine\Common\Annotations\Annotation\Attributes;

/**
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

    public $type;

    public $description;

    /**
     * Parameter constructor.
     * @param $values
     */
    public function __construct($values)
    {
        if (isset($values['value'])) {
            $values = ['name' => $values['value']];
        }
        $this->name = $values['name'];
        $this->type = $values['type'] ?? 'integer';
        $this->in = $values['in'] ?? 'path';
    }

    /**
     * @inheritdoc
     */
    public function toArray()
    {
        $data = [
            'in' => $this->in,
            'name' => $this->name,
            'description' => $this->description ?? '',
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
