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

    /**
     * Response constructor.
     * @param array $values
     */
    public function __construct(array $values)
    {
        $this->configureSelf($values, 'status');
    }
}
