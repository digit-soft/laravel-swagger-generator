<?php

namespace OA;

/**
 * Used to describe controller action error response (shortcut)
 *
 * @Annotation
 */
class ResponseError extends Response
{
    public $status = 400;
    public $content = [];

    protected $defaultContent = [];
    protected $defaultDescription;

    /**
     * Response constructor.
     * @param array $values
     */
    public function __construct(array $values)
    {
        $this->configureSelf($values, 'status');
    }

    /**
     * @inheritdoc
     */
    public function getComponentKey()
    {
        $isDefault = $this->content === $this->defaultContent && $this->description === $this->defaultDescription;
        if (!$isDefault) {
            return null;
        }
        return 'ResponseError_' . $this->status;
    }
}
