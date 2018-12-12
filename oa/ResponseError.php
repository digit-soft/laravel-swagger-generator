<?php

namespace OA;

/**
 * @Annotation
 */
class ResponseError extends Response
{
    public $status = 400;
    public $content = [];
}
