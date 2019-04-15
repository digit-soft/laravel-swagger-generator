<?php

namespace OA;

use DigitSoft\Swagger\Parser\CleanupsDescribedData;
use DigitSoft\Swagger\Parser\WithVariableDescriber;
use Doctrine\Common\Annotations\Annotation;
use Doctrine\Common\Annotations\Annotation\Attribute;
use Doctrine\Common\Annotations\Annotation\Attributes;
use Doctrine\Common\Annotations\Annotation\Target;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;

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
 *   @Attribute("withPager",type="boolean"),
 * })
 */
class Response extends BaseAnnotation
{
    public $content;
    public $contentType = 'application/json';
    public $status = 200;
    public $description;
    public $asList = false;
    public $asPagedList = false;

    protected $_hasNoData = false;
    protected $_setProperties = [];

    use CleanupsDescribedData, WithVariableDescriber;

    /**
     * Response constructor.
     * @param array $values
     */
    public function __construct(array $values)
    {
        $this->_setProperties = $this->configureSelf($values, 'content');
    }

    /**
     * @return array
     */
    public function toArray()
    {
        $asList = $this->asList || $this->asPagedList;
        if ($asList) {
            $contentRaw = $this->describer()->describe([""]);
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
                    'schema' => $content
                ],
            ],
        ];
    }

    /**
     * Get component key
     * @return string|null
     */
    public function getComponentKey()
    {
        return null;
    }

    /**
     * Check that response has Data
     * @return bool
     */
    public function hasData()
    {
        return !$this->_hasNoData;
    }

    /**
     * Get content array
     * @return array
     */
    protected function getContent()
    {
        $this->_hasNoData = !$this->wasSetInConstructor('content') && empty($this->content)
            ? true : $this->_hasNoData;
        return $this->content !== null ? $this->describer()->describe($this->content) : null;
    }

    /**
     * Check that properties was set in construction
     * @param  array|string $properties Properties list
     * @param  bool         $any        Check if any of given properties was set, otherwise checks all given properties list
     * @return bool
     */
    protected function wasSetInConstructor($properties, $any = false)
    {
        $properties = (array)$properties;
        if (count($properties) === 1) {
            return in_array(reset($properties), $this->_setProperties);
        }
        $intersection = array_intersect($this->_setProperties, $properties);
        return ($any && !empty($intersection)) || count($intersection) === count($properties);
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
        $content = $content ?? $this->content;
        $responseData = static::getDefaultResponse($this->contentType, $this->status);
        if ($responseData === null) {
            return $content;
        }
        list($responseRaw, $resultKey) = array_values($responseData);
        if ($this->asPagedList && static::isSuccessStatus($this->status)) {
            $responseRaw['pagination'] = static::getPagerExample();
        }
        $response = $this->describer()->describe($responseRaw);
        $content !== null ? Arr::set($response, $resultKey, $content) : Arr::forget($response, $resultKey);
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
        $pager = new LengthAwarePaginator(array_fill(0, 10, null), 100, 10, 2);
        return Arr::except($pager->toArray(), ['items', 'data']);
    }

    /**
     * Get default description
     * @return string
     */
    protected function getDefaultDescription()
    {
        $list = static::getDefaultStatusDescriptions();
        return $list[$this->status] ?? '';
    }

    /**
     * Get default statuses descriptions
     * @return array
     */
    protected static function getDefaultStatusDescriptions()
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
