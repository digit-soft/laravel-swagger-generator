<?php
namespace OA;

/**
 * Used to mark controller method with given tag.
 * Can be used on controller itself (all methods will have those tags).
 *
 * @Annotation
 * @Target({"METHOD", "CLASS"})
 * @Attributes({
 *   @Attribute("name", type = "string"),
 * })
 */
class Tag
{
    public $name;

    /**
     * Tag constructor.
     * @param array $values
     */
    public function __construct(array $values)
    {
        if (isset($values['value'])) {
            $values = ['name' => $values['value']];
        }
        $this->name = $values['name'];
    }
}
