<?php

namespace OA;

use DigitSoft\Swagger\DumperYaml;
use Doctrine\Common\Annotations\Annotation\Target;
use Illuminate\Support\Arr;

/**
 * @Annotation
 * @Target({"METHOD"})
 */
class Response extends BaseAnnotation
{
    public $content;
    public $contentType = 'application/json';
    public $status = 200;
    public $description;

    protected static $defaultResponses = [];

    /**
     * Response constructor.
     * @param array $values
     */
    public function __construct(array $values)
    {
        $this->configureSelf($values, 'content');
    }

    /**
     * @return array
     */
    public function toArray()
    {
        $content = static::wrapInDefaultResponse($this->status, $this->getContent());
        return [
            'description' => $this->description ?? '',
            'content' => [
                $this->contentType => [
                    'schema' => $content
                ],
            ],
        ];
    }

    /**
     * Get content array
     * @return array
     */
    protected function getContent()
    {
        return DumperYaml::describe($this->content);
    }

    /**
     * Get object string representation
     * @return string
     */
    public function __toString()
    {
        return $this->status;
    }

    /**
     * Wrap response content in default response
     * @param int  $status
     * @param bool $content
     * @return array
     */
    protected static function wrapInDefaultResponse($status, $content = true)
    {
        $status = intval($status);
        $key = in_array($status, [200, 201]) ? 'ok' : 'error';
        $contentKey = $key === 'ok' ? 'properties.result' : 'properties.errors';
        $responses = [
            'ok' => [
                'success' => true,
                'message' => 'OK',
                'result' => false,
            ],
            'error' => [
                'success' => false,
                'message' => 'Error',
                'errors' => [],
            ],
        ];

        $response = DumperYaml::describe($responses[$key]);
        Arr::set($response, $contentKey, $content);

        return $response;
    }
}
