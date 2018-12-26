<?php

namespace OA;

use Doctrine\Common\Annotations\Annotation;
use Doctrine\Common\Annotations\Annotation\Target;

/**
 * Used to describe controller action error response (shortcut)
 *
 * @Annotation
 * @Target({"METHOD"})
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
        $this->setProperties = $this->configureSelf($values, 'status');
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

    /**
     * @inheritdoc
     */
    public function hasData()
    {
        return true;
    }
}
