<?php

namespace OA;

use Doctrine\Common\Annotations\Annotation\Target;

/**
 * Used in annotations (RequestBody) to describe request body parameter
 *
 * @Annotation
 * @Target({"CLASS", "ANNOTATION"})
 */
class RequestParam extends BaseAnnotation
{
    public $required = true;

    public $type = 'string';

    public $name;

    public $items;

    public $description;

    /**
     * RequestParam constructor.
     * @param array $values
     */
    public function __construct(array $values)
    {
        $this->configureSelf($values, 'name');
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
     * @inheritdoc
     */
    public function toArray()
    {
        $data = [
            'type' => $this->type,
            'required' => $this->required,
        ];
        if ($this->description !== null) {
            $data['description'] = $this->description;
        }
        if ($this->type === 'array') {
            $data['items'] = [
                'type' => $this->items,
            ];
        }

        return $data;
    }
}
