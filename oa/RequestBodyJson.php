<?php

namespace OA;

use DigitSoft\Swagger\DumperYaml;
use DigitSoft\Swagger\Parser\CleanupsDescribedData;
use Doctrine\Common\Annotations\Annotation;
use Doctrine\Common\Annotations\Annotation\Attribute;
use Doctrine\Common\Annotations\Annotation\Target;

/**
 * Used to describe request body FormRequest class
 *
 * @Annotation
 * @Target({"CLASS", "METHOD"})
 * @Attributes({
 *   @Attribute("description", type="string", required=false),
 *   @Attribute("contentType", type="string", required=false),
 * })
 */
class RequestBodyJson extends RequestBody
{
    public $contentType = 'application/json';

    use CleanupsDescribedData;

    /**
     * Get object string representation
     * @return string
     */
    public function __toString()
    {
        return json_encode($this->content);
    }

    /**
     * Process content row by row recursively
     * @param array $content
     * @return array
     */
    protected function processContent($content)
    {
        $result = [];
        foreach ($content as $key => $row) {
            if (is_object($row)) {
                if (!method_exists($row, 'toArray')) {
                    continue;
                }
                if ($row instanceof RequestParam) {
                    $row->toArrayRecursive($result);
                } else {
                    $result[$key] = DumperYaml::describe($row->toArray());
                }
            } elseif (is_array($row)) {
                $result[$key] = $this->processContent($row);
            } else {
                $result[$key] = $row;
            }
            if (isset($result[$key]) && is_array($result[$key])) {
                $currentRow = &$result[$key];
                static::handleIncompatibleTypeKeys($currentRow);
            }
        }
        return $result;
    }

    /**
     * @inheritdoc
     */
    public function toArray()
    {
        $content = $this->processContent($this->content);
        $data = [
            'description' => $this->description ?? '',
            'required' => true,
            'content' => [
                $this->contentType => [
                    'schema' => [
                        'type' => 'object',
                        'properties' => $content,
                    ],
                ],
            ],
        ];
        return $data;
    }
}
