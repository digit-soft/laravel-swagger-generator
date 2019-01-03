<?php

namespace DigitSoft\Swagger\Describer;

use Illuminate\Support\Arr;

/**
 * Trait WithExampleGenerator
 * @package DigitSoft\Swagger\Parser
 * @mixin WithFaker
 * @mixin WithTypeParser
 */
trait WithExampleGenerator
{
    /**
     * @var array Generated variables cache
     */
    protected $varsCache = [];

    /**
     * Get variable example
     * @param  string|null $type
     * @param  string|null $varName
     * @param  string|null $rule
     * @param  bool        $normalizeType
     * @return mixed|null
     */
    public function example(&$type, $varName = null, $rule = null, $normalizeType = false)
    {
        $typeUsed = $type;
        // Guess variable type to get from cache
        if ($typeUsed === null && $rule !== null) {
            $typeUsed = $this->getRuleType($rule);
        }
        // Get from cache
        if (($cachedValue = $this->getVarCache($varName, $typeUsed)) !== null) {
            return $cachedValue;
        }
        // Fill rule and type
        $rule = $rule === null || $this->isBasicType($rule) ? $this->getVariableRule($varName, $rule) : $rule;
        $typeUsed = $typeUsed === null && $rule !== null ? $this->getRuleType($rule) : $typeUsed;
        // Can't guess => leaving
        if ($typeUsed === null) {
            return null;
        }
        $isArray = strpos($typeUsed, '[]') !== false;
        if ($rule === null || ($example = $this->exampleByRule($rule)) === null) {
            $typeClean = $isArray ? substr($typeUsed, 0, -2) : $type;
            $example = $this->exampleByType($typeClean);
        }
        if ($normalizeType && is_string($typeUsed) && ($typeNormalized = $this->swaggerType($typeUsed)) !== null) {
            $type = $typeNormalized;
        }
        $example = $isArray ? [$example] : $example;
        return $this->setVarCache($varName, $type, $example);
    }

    /**
     * Get example by given type
     * @param  string $type
     * @return array|int|string|null
     */
    protected function exampleByType($type)
    {
        $type = is_string($type) ? $this->normalizeType($type, true) : null;
        switch ($type) {
            case 'int':
            case 'integer':
                return $this->faker()->numberBetween(1, 99);
                break;
            case 'float':
            case 'double':
                return $this->faker()->randomFloat(2);
                break;
            case 'string':
                return array_random(['string', 'value', 'str value']);
                break;
            case 'bool':
            case 'boolean':
                return $this->faker()->boolean;
                break;
            case 'date':
                return $this->faker()->dateTimeBetween('-1 month')->format('Y-m-d');
                break;
            case 'Illuminate\Support\Carbon':
            case 'dateTime':
            case 'datetime':
                return $this->faker()->dateTimeBetween('-1 month')->format('Y-m-d H:i:s');
                break;
            case 'array':
                return [];
                break;
        }
        return null;
    }

    /**
     * Get example value by validation rule
     * @param  string $rule
     * @return mixed
     */
    protected function exampleByRule(string $rule)
    {
        switch ($rule) {
            case 'phone':
                $example = array_random(['+380971234567', '+380441234567', '+15411234567', '+4901511234567']);
                break;
            case 'url':
                $example = $this->faker()->url;
                break;
            case 'image':
                $example = $this->faker()->imageUrl();
                break;
            case 'email':
                $example = $this->faker()->email;
                break;
            case 'password':
                $example = str_random(16);
                break;
            case 'token':
                $example = str_random(64);
                break;
            case 'service_name':
                $example = array_random(['fb', 'google', 'twitter']);
                break;
            case 'domain_name':
                $example = $this->faker()->domainName;
                break;
            case 'alpha':
            case 'string':
                $example = array_random(['string', 'value', 'str value']);
                break;
            case 'text':
                $example = $this->faker()->text(100);
                break;
            case 'textShort':
                $example = $this->faker()->text(50);
                break;
            case 'alpha_num':
                $example = array_random(['string35', 'value90', 'str20value']);
                break;
            case 'alpha_dash':
                $example = array_random(['string_35', 'value-90', 'str_20-value']);
                break;
            case 'ip':
            case 'ipv4':
                $example = $this->faker()->ipv4;
                break;
            case 'ipv6':
                $example = $this->faker()->ipv6;
                break;
            case 'float':
                $example = $this->faker()->randomFloat(2);
                break;
            case 'date':
                $example = $this->faker()->dateTimeBetween('-1 month')->format('Y-m-d');
                break;
            case 'date-time':
            case 'dateTime':
            case 'datetime':
                $example = $this->faker()->dateTimeBetween('-1 month')->format('Y-m-d H:i:s');
                break;
            case 'numeric':
            case 'integer':
                $example = $this->faker()->numberBetween(1, 99);
                break;
            case 'boolean':
                $example = $this->faker()->boolean;
                break;
            case 'first_name':
                $example = $this->faker()->firstName;
                break;
            case 'last_name':
                $example = $this->faker()->firstName;
                break;
            case 'address':
                $example = trim($this->faker()->address);
                break;
            default:
                $example = null;
        }
        return $example;
    }

    /**
     * Get variable value from cache
     * @param  string $name
     * @param  string $type
     * @return mixed|null
     * @internal
     */
    protected function getVarCache($name, $type)
    {
        if (($key = $this->getVarCacheKey($name, $type)) === null) {
            return null;
        }
        return Arr::get($this->varsCache, $key);
    }

    /**
     * Set variable value to cache
     * @param  string $name
     * @param  string $type
     * @param  mixed $value
     * @return mixed|null
     * @internal
     */
    protected function setVarCache($name, $type, $value)
    {
        if ($value !== null && ($key = $this->getVarCacheKey($name, $type)) !== null) {
            Arr::set($this->varsCache, $key, $value);
        }
        return $value;
    }

    /**
     * Create variable cache string key
     * @param  string $name
     * @param  string $type
     * @return string|null
     */
    private function getVarCacheKey($name, $type)
    {
        $suffixes = ['_confirm', '_original', '_example', '_new'];
        if ($name === null || $type === null) {
            return null;
        }
        foreach ($suffixes as $suffix) {
            $len = strlen($suffix);
            if (substr($name, -$len) === (string) $suffix) {
                $name = substr($name, 0, -$len);
                break;
            }
        }
        return $name . '|' . $type;
    }
}
