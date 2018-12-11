<?php

namespace DigitSoft\Swagger\Yaml;

use DigitSoft\Swagger\DumperYaml;

class ResponseEntry extends Component
{
    /**
     * @var int Status code
     */
    public $status = 200;
    /**
     * @var string Content type
     */
    protected $contentType = 'application/json';
    /**
     * @var string|null Response description
     */
    protected $description;
    /**
     * @var mixed Response body
     */
    protected $body;

    /**
     * Set status
     * @param integer $status
     * @return $this
     */
    public function withStatus($status)
    {
        $this->status = $status;
        return $this;
    }

    /**
     * Set content type
     * @param string $contentType
     * @return $this
     */
    public function withContentType($contentType)
    {
        $this->contentType = $contentType;
        return $this;
    }

    /**
     * Set response description
     * @param string $description
     * @return $this
     */
    public function withDescription($description)
    {
        $this->description = $description;
        return $this;
    }

    /**
     * Set response body
     * @param mixed $body
     * @return $this
     */
    public function withBody($body)
    {
        $this->body = $body;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function toArray()
    {
        $contentType = $this->contentType;
        $data = [
            'description' => $this->description,
            'content' => [
                $contentType => [
                    'schema' => DumperYaml::describe($this->body),
                ],
            ],
        ];
        return $data;
    }
}
