<?php

namespace OA;

use Doctrine\Common\Annotations\Annotation;
use Doctrine\Common\Annotations\Annotation\Target;

/**
 * Used to describe controller action error response (shortcut)
 *
 * @Annotation
 * @Target("METHOD")
 */
class ResponseError extends Response
{
    public $status = 400;
    public $content;

    protected $usedDefaultContent = false;
    protected $defaultDescription;

    /**
     * Response constructor.
     * @param array $values
     */
    public function __construct(array $values)
    {
        $this->_setProperties = $this->configureSelf($values, 'status');
        if (!$this->wasSetInConstructor('content')) {
            $this->content = $this->getDefaultContentByStatus();
            $this->usedDefaultContent = true;
        }
    }

    /**
     * @inheritdoc
     */
    public function getComponentKey()
    {
        $isDefault = $this->usedDefaultContent && $this->description === $this->defaultDescription;
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

    /**
     * Get default content for given error code
     * @return mixed|null
     */
    protected function getDefaultContentByStatus()
    {
        $content = static::defaultContentList();
        return $content[$this->status] ?? null;
    }

    /**
     * Get default content list by status
     * @return array
     */
    protected static function defaultContentList()
    {
        return [
            422 => ['request_attribute' => ['Error message #1', 'Error message #2']],
        ];
    }
}
