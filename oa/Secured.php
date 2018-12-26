<?php
namespace OA;

use Doctrine\Common\Annotations\Annotation;
use Doctrine\Common\Annotations\Annotation\Attribute;
use Doctrine\Common\Annotations\Annotation\Attributes;
use Doctrine\Common\Annotations\Annotation\Target;

/**
 * Used to mark controller method as secured (with optional security scheme)
 *
 * @Annotation
 * @Target({"METHOD"})
 * @Attributes({
 *   @Attribute("scheme", type = "string"),
 * })
 */
class Secured extends BaseAnnotation
{
    public $scheme;

    public $content = [];

    /**
     * Secured constructor.
     * @param array $values
     */
    public function __construct(array $values)
    {
        $this->configureSelf($values, 'scheme');
    }

    /**
     * Get object string representation
     * @return string
     */
    public function __toString()
    {
        return $this->scheme;
    }

    /**
     * @inheritdoc
     */
    public function toArray()
    {
        return $this->getSecurityScheme();
    }

    /**
     * Get security scheme data
     * @return array
     * @throws \Exception
     */
    protected function getSecurityScheme()
    {
        $schemes = config('swagger-generator.content.components.securitySchemes', []);
        if (empty($schemes)) {
            throw new \Exception("There are no security schemes defined");
        }
        $key = $this->scheme ?? key($schemes);
        if (!isset($schemes[$key])) {
            throw new \Exception("Security scheme '{$key}' not defined");
        }
        return [$key => $this->content];
    }
}
