<?php

namespace OA;

use DigitSoft\Swagger\DumperYaml;
use Doctrine\Common\Annotations\Annotation\Target;
use Illuminate\Pagination\LengthAwarePaginator;
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
    public $asList = false;
    public $withPager = false;

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
        if ($this->asList) {
            $contentRaw = DumperYaml::describe([""]);
            Arr::set($contentRaw, 'items', $this->getContent());
        } else {
            $contentRaw = $this->asList ? [$this->getContent()] : $this->getContent();
        }
        $content = $this->wrapInDefaultResponse($contentRaw);
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
     * @param bool $content
     * @return array|mixed
     */
    protected function wrapInDefaultResponse($content = null)
    {
        $withPager = $this->withPager;
        $content = $content ?? $this->content;
        $responseData = static::getDefaultResponse($this->contentType, $this->status);
        if ($responseData === null) {
            return $content;
        }
        list($responseRaw, $resultKey) = array_values($responseData);
        if ($withPager && static::isSuccessStatus($this->status)) {
            $responseRaw['pagination'] = static::getPagerExample();
        }
        $response = DumperYaml::describe($responseRaw);
        Arr::set($response, $resultKey, $content);
        return $response;
    }

    /**
     * Get default response by content type [response, result_array_key]
     * @param string $contentType
     * @param int $status
     * @return mixed|null
     */
    protected static function getDefaultResponse($contentType, $status = 200)
    {
        $key = static::isSuccessStatus($status) ? 'ok' : 'error';
        $responses = [
            'application/json' => [
                'ok' => [
                    'response' => [
                        'success' => true,
                        'message' => 'OK',
                        'result' => false,
                    ],
                    'resultKey' => 'properties.result',
                ],
                'error' => [
                    'response' => [
                        'success' => false,
                        'message' => 'Error',
                        'errors' => [],
                    ],
                    'resultKey' => 'errors',
                ],
            ],
        ];
        $responseKey = $contentType . '.' . $key;
        if (($responseData = Arr::get($responses, $responseKey, null)) === null) {
            return null;
        }
        return $responseData;
    }

    /**
     * Check that status is successful
     * @param int|string $status
     * @return bool
     */
    protected static function isSuccessStatus($status)
    {
        $status = intval($status);
        return in_array($status, [200, 201]);
    }

    /**
     * Get pager example
     * @return array
     */
    protected static function getPagerExample()
    {
        $pager = new LengthAwarePaginator([], 100, 10, 1);
        return Arr::except($pager->toArray(), ['items']);
    }
}
