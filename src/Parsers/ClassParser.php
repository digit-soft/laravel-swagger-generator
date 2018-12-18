<?php

namespace DigitSoft\Swagger\Parsers;

use DigitSoft\Swagger\Parser\WithDocParser;
use DigitSoft\Swagger\Parser\WithReflections;
use Illuminate\Database\Eloquent\Model;

class ClassParser
{
    use WithReflections, WithDocParser;

    /**
     * @var string
     */
    public $className;
    /**
     * @var array
     */
    public $constructorParams = [];

    protected $instance;

    protected $isModel;

    /**
     * ModelParser constructor.
     * @param $className
     */
    public function __construct($className)
    {
        $this->className = $className;
    }

    /**
     * Check that class is model subclass
     * @return bool
     */
    protected function isModel()
    {
        if ($this->isModel === null) {
            $className = $this->className;
            $this->isModel = is_subclass_of($className, Model::class);
        }
        return $this->isModel;
    }

    /**
     * @return object
     */
    protected function instantiate()
    {
        return $this->instance ?? $this->instance = app()->make($this->className, $this->constructorParams);
    }
}
