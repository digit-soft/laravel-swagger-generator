<?php

namespace OA;

use Illuminate\Support\Arr;
use DigitSoft\Swagger\Yaml\Variable;
use Doctrine\Common\Annotations\Annotation;
use Doctrine\Common\Annotations\Annotation\Target;
use DigitSoft\Swagger\Parser\CleanupsDescribedData;
use DigitSoft\Swagger\Parser\WithVariableDescriber;
use Doctrine\Common\Annotations\Annotation\Attribute;
use Doctrine\Common\Annotations\Annotation\Attributes;

/**
 * Used to describe controller action response (content, content-type, status etc.)
 *
 * @Annotation
 * @Target("METHOD")
 * @Attributes({
 *   @Attribute("status",type="integer"),
 *   @Attribute("contentType",type="string"),
 *   @Attribute("description",type="string"),
 *   @Attribute("asList",type="boolean"),
 *   @Attribute("asPagedList",type="boolean"),
 *   @Attribute("asCursorPagedList",type="boolean"),
 * })
 */
class Response extends BaseAnnotation
{
    public mixed $content = null;
    public string $contentType = 'application/json';
    public int $status = 200;
    public ?string $description = null;
    public bool $asList = false;
    public bool $asPagedList = false;
    public bool $asCursorPagedList = false;

    protected bool $_hasNoData = false;
    protected array $_setProperties = [];

    use CleanupsDescribedData, WithVariableDescriber;

    /**
     * Response constructor.
     *
     * @param  array $values
     */
    public function __construct(array $values)
    {
        $this->_setProperties = $this->configureSelf($values, 'content');
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        if ($this->isList()) {
            $contentRaw = $this->describer()->describe(['']);
            Arr::set($contentRaw, 'items', $this->getContent());
        } else {
            $contentRaw = $this->getContent();
        }
        $content = $this->wrapInDefaultResponse($contentRaw);
        static::handleIncompatibleTypeKeys($content);

        return [
            'description' => $this->description ?? $this->getDefaultDescription(),
            'content' => [
                $this->contentType => [
                    'schema' => $content,
                ],
            ],
        ];
    }

    /**
     * Get component key
     *
     * @return string|null
     */
    public function getComponentKey(): ?string
    {
        return null;
    }

    /**
     * Check that response has Data
     *
     * @return bool
     */
    public function hasData(): bool
    {
        return ! $this->_hasNoData;
    }

    /**
     * Get content array
     *
     * @return array|null
     */
    protected function getContent(): ?array
    {
        $this->_hasNoData = ! $this->wasSetInConstructor('content') && empty($this->content)
            ? true : $this->_hasNoData;

        if (($contentByAnnotations = $this->getContentByAnnotations()) !== null) {
            return $contentByAnnotations;
        }

        return $this->content !== null ? $this->describer()->describe($this->content) : null;
    }

    /**
     * Get content generated by used annotations.
     *
     * @return array|null
     */
    protected function getContentByAnnotations(): ?array
    {
        if (! is_array($this->content) || Arr::isAssoc($this->content)) {
            return null;
        }

        $newContent = [];
        foreach ($this->content as $contentKey => $contentRow) {
            if ($contentRow instanceof ResponseParam) {
                if (! isset($contentRow->name)) {
                    throw new \RuntimeException("Attribute 'name' in ResponseParam annotation is required.");
                }
                $newContent[$contentRow->name] = $contentRow->toArray();
            }
        }

        if (! empty($newContent)) {
            return [
                'type' => Variable::SW_TYPE_OBJECT,
                'properties' => $newContent,
            ];
        }

        return null;
    }

    /**
     * Check that properties was set in construction.
     *
     * @param  array|string $properties Properties list
     * @param  bool         $any        Check if any of given properties was set, otherwise checks all given properties list
     * @return bool
     */
    protected function wasSetInConstructor(array|string $properties, bool $any = false): bool
    {
        $properties = (array)$properties;
        if (count($properties) === 1) {
            return in_array(reset($properties), $this->_setProperties, true);
        }
        $intersection = array_intersect($this->_setProperties, $properties);

        return ($any && ! empty($intersection)) || count($intersection) === count($properties);
    }

    /**
     * Get object string representation
     *
     * @return string
     */
    public function __toString()
    {
        return (string)$this->status;
    }

    /**
     * Wrap response content in default response
     *
     * @param  mixed $content
     * @return array|mixed
     */
    protected function wrapInDefaultResponse(mixed $content = null): mixed
    {
        $content = $content ?? $this->content;
        $responseData = static::getDefaultResponse($this->contentType, $this->status);
        if ($responseData === null) {
            return $content;
        }
        [$responseRaw, $resultKey] = array_values($responseData);
        if (($this->asPagedList || $this->asCursorPagedList) && static::isSuccessStatus($this->status)) {
            if ($this->asPagedList) {
                $responseRaw['pagination'] = static::getPagerExample();
            } elseif ($this->asCursorPagedList) {
                $responseRaw['pagination'] = static::getCursorPagerExample();
            }
        }
        $response = $this->describer()->describe($responseRaw);
        $content !== null ? Arr::set($response, $resultKey, $content) : Arr::forget($response, $resultKey);

        return $response;
    }

    /**
     * Get default response by content type [response, result_array_key].
     *
     * @param  string $contentType
     * @param  int    $status
     * @return mixed|null
     */
    protected static function getDefaultResponse(string $contentType, int $status = 200): mixed
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
                    'resultKey' => 'properties.errors',
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
     * Check that status is successful.
     *
     * @param  int|string $status
     * @return bool
     */
    protected static function isSuccessStatus($status): bool
    {
        $statusInt = (int)$status;

        return $statusInt >= 200 && $statusInt < 400;
    }

    /**
     * Determines whether response is a list of items.
     *
     * @return bool
     */
    protected function isList(): bool
    {
        return $this->asList || $this->asPagedList || $this->asCursorPagedList;
    }

    /**
     * Get pager example.
     *
     * @return array
     */
    protected static function getPagerExample(): array
    {
        $data = array_fill(0, 100, null);
        $pager = new \Illuminate\Pagination\LengthAwarePaginator($data, 100, 10, 2);

        return Arr::except($pager->toArray(), ['items', 'data', 'links']);
    }

    /**
     * Get cursor pager example.
     *
     * @return array
     */
    protected static function getCursorPagerExample(): array
    {
        $cursor = new \Illuminate\Pagination\Cursor(['id' => 20]);
        $data = array_map(function ($v) { return ['id' => $v]; }, array_keys(array_fill(1, 100, null)));
        $pager = new \Illuminate\Pagination\CursorPaginator($data, 10, $cursor);

        return Arr::except($pager->toArray(), ['data']);
    }

    /**
     * Get default description.
     *
     * @return string
     */
    protected function getDefaultDescription(): string
    {
        $list = static::getDefaultStatusDescriptions();

        return $list[$this->status] ?? '';
    }

    /**
     * Get default statuses descriptions
     *
     * @return array
     */
    protected static function getDefaultStatusDescriptions(): array
    {
        return [
            200 => 'OK',
            201 => 'Created',
            202 => 'Accepted',
            203 => 'Non-authoritative Information',
            204 => 'No Content',
            205 => 'Reset Content',
            206 => 'Partial Content',
            207 => 'Multi-Status',
            208 => 'Already Reported',
            226 => 'IM Used',
            300 => 'Multiple Choices',
            301 => 'Moved Permanently',
            302 => 'Found',
            303 => 'See Other',
            304 => 'Not Modified',
            305 => 'Use Proxy',
            307 => 'Temporary Redirect',
            308 => 'Permanent Redirect',
            400 => 'Bad Request',
            401 => 'Unauthorized',
            402 => 'Payment Required',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            406 => 'Not Acceptable',
            407 => 'Proxy Authentication Required',
            408 => 'Request Timeout',
            409 => 'Conflict',
            410 => 'Gone',
            411 => 'Length Required',
            412 => 'Precondition Failed',
            413 => 'Payload Too Large',
            414 => 'Request-URI Too Long',
            415 => 'Unsupported Media Type',
            416 => 'Requested Range Not Satisfiable',
            417 => 'Expectation Failed',
            418 => 'I\'m a teapot',
            421 => 'Misdirected Request',
            422 => 'Unprocessable Entity',
            423 => 'Locked',
            424 => 'Failed Dependency',
            426 => 'Upgrade Required',
            428 => 'Precondition Required',
            429 => 'Too Many Requests',
            431 => 'Request Header Fields Too Large',
            444 => 'Connection Closed Without Response',
            451 => 'Unavailable For Legal Reasons',
            499 => 'Client Closed Request',
            500 => 'Internal Server Error',
            501 => 'Not Implemented',
            502 => 'Bad Gateway',
            503 => 'Service Unavailable',
            504 => 'Gateway Timeout',
            505 => 'HTTP Version Not Supported',
            506 => 'Variant Also Negotiates',
            507 => 'Insufficient Storage',
            508 => 'Loop Detected',
            510 => 'Not Extended',
            511 => 'Network Authentication Required',
            599 => 'Network Connect Timeout Error',
        ];
    }
}
