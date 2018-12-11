<?php

namespace OA;

use DigitSoft\Swagger\DumperYaml;
use Doctrine\Common\Annotations\Annotation\Attribute;
use Doctrine\Common\Annotations\Annotation\Target;

/**
 * @Annotation
 * @Target({"CLASS"})
 * @Attributes({
 *   @Attribute("description", type="string", required=false),
 *   @Attribute("contentType", type="string", required=false),
 *   @Attribute("content", type="array", required=true),
 * })
 */
class RequestBodyJson extends RequestBody
{
    public $contentType = 'application/json';

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
     */
    protected function processContent($content = [])
    {
        $result = [];
        $described = [];
        $examples = [];
        foreach ($content as $key => $row) {
            $rowRes = $row;
            if (is_object($row)) {
                if (!method_exists($row, 'toArray')) {
                    continue;
                }
                if ($row instanceof RequestParam) {
                    $described[$row->name] = $row->toArray();
                } else {
                    $examples[$key] = $row->toArray();
                }
            } elseif (is_array($row)) {
                $rowRes = $this->processContent($row);
            }
            if (is_object($row) && method_exists($row, 'toArray')) {
                $rowRes = $row->toArray();
                $key = $row instanceof RequestParam ? $row->name : $key;
            } elseif (is_array($row)) {
                $rowRes = $this->processContent($row);
            }
            $result[$key] = $rowRes;
        }
        return $result;
    }

    protected function processContent2($content, array &$examples, array &$described)
    {
        $result = [];
        foreach ($content as $key => $row) {
            $rowRes = $row;
            if (is_object($row)) {
                if (!method_exists($row, 'toArray')) {
                    continue;
                }
                if ($row instanceof RequestParam) {
                    $described[$row->name] = $row->toArray();
                } else {
                    $examples[$key] = $row->toArray();
                }
            } elseif (is_array($row)) {
                $this->processContent($row, $examples, $described);
            }
            if (is_object($row) && method_exists($row, 'toArray')) {
                $rowRes = $row->toArray();
                $key = $row instanceof RequestParam ? $row->name : $key;
            } elseif (is_array($row)) {
                $rowRes = $this->processContent($row);
            }
            $result[$key] = $rowRes;
        }
    }

    /**
     * @inheritdoc
     */
    public function toArray()
    {
        $content = $this->processContent($this->content);
        dd($content);
        $contentDescribed = DumperYaml::describe($content);
        $data = [
            'description' => $this->description ?? '',
            'required' => true,
            'content' => [
                $this->contentType => [
                    'schema' => $contentDescribed,
                ],
            ],
        ];
        return $data;
    }
}
