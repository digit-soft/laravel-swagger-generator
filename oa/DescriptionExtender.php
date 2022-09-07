<?php

namespace OA;

/**
 * You can extend this annotation and use it to describe whatever you want,
 * it will be placed in description tag of YAML one per line.
 */
abstract class DescriptionExtender extends BaseAnnotation
{
    public mixed $value = null;

    protected ?string $action = null;

    /**
     * DescriptionExtender constructor.
     * @param array $values
     */
    public function __construct(array $values)
    {
        $this->configureSelf($values, 'value');
    }

    /**
     * Set controller action name (method) before dumping annotation.
     *
     * @param  string $action
     * @return $this
     */
    public function setAction(string $action): static
    {
        $this->action = $action;

        return $this;
    }

    /**
     * Get object string representation.
     *
     * @return string
     */
    public function __toString()
    {
        /** @noinspection MagicMethodsValidityInspection */
        return is_string($this->value) ? $this->value : '';
    }
}
