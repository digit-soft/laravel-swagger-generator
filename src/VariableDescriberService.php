<?php

namespace DigitSoft\Swagger;

use Symfony\Component\Yaml\Yaml;
use Illuminate\Filesystem\Filesystem;
use DigitSoft\Swagger\Parser\WithReflections;
use DigitSoft\Swagger\Describer\WithTypeParser;
use DigitSoft\Swagger\Describer\WithExampleGenerator;
use DigitSoft\Swagger\Describer\WithRecursiveDescriber;

/**
 * Service made to describe variables in swagger format
 */
class VariableDescriberService
{
    use WithTypeParser, WithExampleGenerator, WithRecursiveDescriber, WithReflections;

    /**
     * @var Filesystem
     */
    protected $files;
    /**
     * @var array
     */
    protected $classShortcuts = [];

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
     *
     * @param  array       $content
     * @param  string|null $filePath
     * @param  bool        $describe
     * @return string
     */
    public function toYml(array $content = [], bool $describe = false, ?string $filePath = null)
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
     *
     * @param  string $filePath
     * @return array
     */
    public function fromYml(string $filePath)
    {
        $contentStr = $this->files->get($filePath);

        return Yaml::parse($contentStr);
    }

    /**
     * Describe variable
     *
     * @param  mixed $variable
     * @param  array $additionalData
     * @param  bool  $withExample
     * @return array
     */
    public function describe($variable, array $additionalData = [], bool $withExample = true)
    {
        return $this->describeValue($variable, $additionalData, $withExample);
    }

    /**
     * Shorten class name.
     *
     * @param  string $className
     * @return string
     */
    public function shortenClass(string $className)
    {
        $className = ltrim($className, '\\');
        if (isset($this->classShortcuts[$className])) {
            return $this->classShortcuts[$className];
        }
        $classNameArray = explode('\\', $className);
        $classNameShort = $classNameShortBase = end($classNameArray);
        $num = 0;
        while (in_array($classNameShort, $this->classShortcuts, true)) {
            $classNameShort = $classNameShortBase . '_' . $num;
            $num++;
        }

        return $this->classShortcuts[$className] = $classNameShort;
    }

    /**
     * Merge arrays.
     *
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

    /**
     * Merge arrays without merging keys under `properties` key.
     *
     * In such way we are trying to rewrite all under properties key, without recursive merge.
     *
     * @param  array $a
     * @param  array $b
     * @return array
     */
    public function mergeWithPropertiesRewrite($a, $b)
    {
        $args = func_get_args();
        $prevKey = null;
        if (is_string(end($args))) {
            $prevKey = array_pop($args);
        }
        $res = array_shift($args);
        while (! empty($args)) {
            foreach (array_shift($args) as $k => $v) {
                if (is_int($k)) {
                    if (array_key_exists($k, $res)) {
                        $res[] = $v;
                    } else {
                        $res[$k] = $v;
                    }
                } elseif ($prevKey !== 'properties' && is_array($v) && isset($res[$k]) && is_array($res[$k])) {
                    $res[$k] = $this->mergeWithPropertiesRewrite($res[$k], $v, $k);
                } else {
                    $res[$k] = $v;
                }
            }
        }

        return $res;
    }
}
