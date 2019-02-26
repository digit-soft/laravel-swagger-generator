<?php

namespace DigitSoft\Swagger;

use DigitSoft\Swagger\Describer\WithExampleGenerator;
use DigitSoft\Swagger\Describer\WithRecursiveDescriber;
use DigitSoft\Swagger\Describer\WithTypeParser;
use DigitSoft\Swagger\Describer\WithFaker;
use DigitSoft\Swagger\Parser\WithReflections;
use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;

/**
 * Service made to describe variables in swagger format
 * @package DigitSoft\Swagger
 */
class VariableDescriberService
{
    /**
     * @var Filesystem
     */
    protected $files;
    /**
     * @var array
     */
    protected $classShortcuts = [];

    use WithFaker, WithTypeParser, WithExampleGenerator, WithRecursiveDescriber, WithReflections;

    /**
     * VariableDescriberService constructor.
     * @param Filesystem $files
     */
    public function __construct(Filesystem $files)
    {
        $this->files = $files;
    }

    /**
     * Export YAML data to file
     * @param array  $content
     * @param string $filePath
     * @param bool   $describe
     * @return string
     */
    public function toYml($content = [], $describe = false, $filePath = null)
    {
        $arrayContent = $content;
        if ($describe) {
            $arrayContent = static::describe($arrayContent);
        }
        $yamlContent = Yaml::dump($arrayContent, 20, 2, Yaml::DUMP_EMPTY_ARRAY_AS_SEQUENCE);
        if ($filePath !== null) {
            $this->files->put($filePath, $yamlContent);
        }
        return $yamlContent;
    }

    /**
     * Parse Yaml file
     * @param string $filePath
     * @return array
     */
    public function fromYml($filePath)
    {
        $contentStr = $this->files->get($filePath);
        $content = Yaml::parse($contentStr);
        return $content;
    }

    /**
     * Describe variable
     * @param mixed $variable
     * @param bool $withExample
     * @return array
     */
    public function describe($variable, $withExample = true)
    {
        return $this->describeValue($variable, $withExample);
    }

    /**
     * Shorten class name
     * @param  string $className
     * @return string
     */
    public function shortenClass($className)
    {
        $className = ltrim($className, '\\');
        if (isset($this->classShortcuts[$className])) {
            return $this->classShortcuts[$className];
        }
        $classNameArray = explode('\\', $className);
        $classNameShort = $classNameShortBase = end($classNameArray);
        $num = 0;
        while (in_array($classNameShort, $this->classShortcuts)) {
            $classNameShort = $classNameShortBase . '_' . $num;
            $num++;
        }
        return $this->classShortcuts[$className] = $classNameShort;
    }

    /**
     * Merge arrays
     * @param  array $a
     * @param  array $b
     * @return array
     */
    public function merge($a, $b)
    {
        $args = func_get_args();
        $res = array_shift($args);
        while (!empty($args)) {
            foreach (array_shift($args) as $k => $v) {
                if (is_int($k)) {
                    if (array_key_exists($k, $res)) {
                        $res[] = $v;
                    } else {
                        $res[$k] = $v;
                    }
                } elseif (is_array($v) && isset($res[$k]) && is_array($res[$k])) {
                    $res[$k] = $this->merge($res[$k], $v);
                } else {
                    $res[$k] = $v;
                }
            }
        }

        return $res;
    }
}
