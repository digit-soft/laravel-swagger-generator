<?php

namespace DigitSoft\Swagger\Parser;

use DigitSoft\Swagger\DumperYaml;
use DigitSoft\Swagger\Yaml\Variable;
use Illuminate\Support\Arr;

/**
 * Trait DescribesVariables
 * @package DigitSoft\Swagger\Parser
 * @mixin WithFaker
 */
trait DescribesVariables
{
    private static $_varsCache = [];

    protected static $_varRules = [
        'url' => [
            'type' => 'string',
            'names' => [
                'url',
                'site',
            ],
        ],
        'image' => [
            'type' => 'string',
            'names' => [
                'logo',
                'avatar',
                'image',
            ],
        ],
        'email' => [
            'type' => 'string',
            'names' => [
                'email',
                'mail',
            ],
        ],
        'password' => [
            'type' => 'string',
            'names' => [
                'password',
                'password_confirm',
                'pass',
                'new_password',
                'password_new',
            ],
        ],
        'token' => [
            'type' => 'string',
            'names' => [
                'token',
                'access_token',
                'email_token',
                'remember_token',
                'service_token',
            ],
        ],
        'domain_name' => [
            'type' => 'string',
            'names' => [
                'domain',
                'domain_name',
                'domainName',
                'site',
            ],
        ],
        'service_name' => [
            'type' => 'string',
            'names' => [
                'service_name',
                'serviceName',
            ],
        ],
        'phone' => [
            'type' => 'string',
            'names' => [
                'phone',
                'phone_number',
                'phone_numbers',
                'phones',
            ],
        ],
        'first_name' => [
            'type' => 'string',
            'names' => [
                'first_name',
                'firstName',
            ],
        ],
        'last_name' => [
            'type' => 'string',
            'names' => [
                'last_name',
                'lastName',
            ],
        ],
        'address' => [
            'type' => 'string',
            'names' => [
                'address',
                'post_address',
                'postAddress',
            ],
        ],
    ];

    /**
     * Get variable example
     * @param  string|null $type
     * @param  string|null $varName
     * @param  string|null $rule
     * @param  bool        $normalizeType
     * @return mixed|null
     */
    public static function example(&$type, $varName = null, $rule = null, $normalizeType = false)
    {
        $typeUsed = $type;
        // Guess variable type to get from cache
        if ($typeUsed === null && $rule !== null) {
            $typeUsed = static::getRuleType($rule);
        }
        // Get from cache
        if (($cachedValue = DescribesVariables::getVarCache($varName, $typeUsed)) !== null) {
            return $cachedValue;
        }
        // Fill rule and type
        $rule = $rule === null || DumperYaml::isBasicType($rule) ? static::getVariableRule($varName, $rule) : $rule;
        $typeUsed = $typeUsed === null && $rule !== null ? static::getRuleType($rule) : $typeUsed;
        // Can't guess => leaving
        if ($typeUsed === null) {
            return null;
        }
        $isArray = strpos($typeUsed, '[]') !== false;
        if ($rule === null || ($example = static::exampleByRule($rule)) === null) {
            $typeClean = $isArray ? substr($typeUsed, 0, -2) : $type;
            $example = static::exampleByType($typeClean);
        }
        if ($normalizeType && is_string($typeUsed) && ($typeNormalized = static::swaggerType($typeUsed)) !== null) {
            $type = $typeNormalized;
        }
        $example = $isArray ? [$example] : $example;
        return DescribesVariables::setVarCache($varName, $type, $example);
    }

    /**
     * Get example by given type
     * @param  string $type
     * @return array|int|string|null
     */
    protected static function exampleByType($type)
    {
        $type = is_string($type) ? DumperYaml::normalizeType($type, true) : null;
        switch ($type) {
            case 'int':
            case 'integer':
                return static::faker()->numberBetween(1, 99);
                break;
            case 'float':
            case 'double':
                return static::faker()->randomFloat(2);
                break;
            case 'string':
                return array_random(['string', 'value', 'str value']);
                break;
            case 'bool':
            case 'boolean':
                return static::faker()->boolean;
                break;
            case 'date':
                return static::faker()->dateTimeBetween('-1 month')->format('Y-m-d');
                break;
            case 'Illuminate\Support\Carbon':
            case 'dateTime':
            case 'datetime':
                return static::faker()->dateTimeBetween('-1 month')->format('Y-m-d H:i:s');
                break;
            case 'array':
                return [];
                break;
        }
        return null;
    }

    /**
     * Get possible rule for a variable name
     * @param  string      $varName
     * @param  string|null $default
     * @return string|null
     */
    protected static function getVariableRule($varName, $default = null)
    {
        if ($varName === null) {
            return null;
        }
        $cleanName = static::cleanupVariableName($varName);
        $varNames = $cleanName !== null && $cleanName !== $varName ? [$varName, $cleanName] : [$varName];
        foreach ($varNames as $name) {
            foreach (static::$_varRules as $rule => $ruleData) {
                if (in_array($name, $ruleData['names'])) {
                    return $rule;
                }
            }
        }
        return $default;
    }

    /**
     * Cleanup variable name (if nested or have suffix appended)
     * @param  string $name
     * @return string|null
     */
    protected static function cleanupVariableName($name)
    {
        $suffixes = ['_confirm', '_original', '_example', '_new', '_old'];
        // Name is not a string
        if (!is_string($name)) {
            return null;
        }
        // Name is nested
        if (strpos($name, '.') !== false) {
            $nameExp = explode('.', $name);
            $name = last($nameExp);
        }
        foreach ($suffixes as $suffix) {
            $len = strlen($suffix);
            if (substr($name, -$len) === $suffix) {
                $name = substr($name, 0, -$len);
                break;
            }
        }
        return $name;

    }

    /**
     * Get example value by validation rule
     * @param string      $rule
     * @return mixed
     */
    protected static function exampleByRule(string $rule)
    {
        switch ($rule) {
            case 'phone':
                $example = array_random(['+380971234567', '+380441234567', '+15411234567', '+4901511234567']);
                break;
            case 'url':
                $example = static::faker()->url;
                break;
            case 'image':
                $example = static::faker()->imageUrl();
                break;
            case 'email':
                $example = static::faker()->email;
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
                $example = static::faker()->domainName;
                break;
            case 'alpha':
            case 'string':
                $example = array_random(['string', 'value', 'str value']);
                break;
            case 'alpha_num':
                $example = array_random(['string35', 'value90', 'str20value']);
                break;
            case 'alpha_dash':
                $example = array_random(['string_35', 'value-90', 'str_20-value']);
                break;
            case 'ip':
            case 'ipv4':
                $example = static::faker()->ipv4;
                break;
            case 'ipv6':
                $example = static::faker()->ipv6;
                break;
            case 'float':
                $example = static::faker()->randomFloat(2);
                break;
            case 'date':
                $example = static::faker()->dateTimeBetween('-1 month')->format('Y-m-d');
                break;
            case 'date-time':
            case 'dateTime':
            case 'datetime':
                $example = static::faker()->dateTimeBetween('-1 month')->format('Y-m-d H:i:s');
                break;
            case 'numeric':
            case 'integer':
                $example = static::faker()->numberBetween(1, 99);
                break;
            case 'boolean':
                $example = static::faker()->boolean;
                break;
            case 'first_name':
                $example = static::faker()->firstName;
                break;
            case 'last_name':
                $example = static::faker()->firstName;
                break;
            case 'address':
                $example = trim(static::faker()->address);
                break;
            default:
                $example = null;
        }
        return $example;
    }

    /**
     * Get rule type (for php)
     * @param  string $rule
     * @return string|null
     */
    private static function getRuleType($rule)
    {
        if (is_string($rule) && isset(static::$_varRules[$rule])) {
            return static::$_varRules[$rule]['type'];
        }
        if (DumperYaml::isBasicType($rule)) {
            return $rule;
        }
        return null;
    }

    /**
     * Get swagger type by example variable
     * @param mixed $example
     * @return string|null
     */
    protected function swaggerTypeByExample($example)
    {
        if (is_null($example)) {
            return null;
        }
        $swType = static::swaggerType(gettype($example));
        if ($swType === Variable::SW_TYPE_ARRAY && Arr::isAssoc($example)) {
            $swType = Variable::SW_TYPE_OBJECT;
        }
        return $swType;
    }

    /**
     * Get swagger type by given PHP type
     * @param  string $phpType
     * @return string|null
     */
    protected static function swaggerType($phpType)
    {
        if (DumperYaml::isTypeArray($phpType)) {
            $phpType = 'array';
        } elseif (DumperYaml::isTypeClassName($phpType)) {
            $phpType = static::simplifyClassName($phpType);
        }
        switch ($phpType) {
            case 'string':
                return Variable::SW_TYPE_STRING;
                break;
            case 'integer':
                return Variable::SW_TYPE_INTEGER;
                break;
            case 'float':
                return Variable::SW_TYPE_NUMBER;
                break;
            case 'object':
                return Variable::SW_TYPE_OBJECT;
                break;
            case 'array':
                return Variable::SW_TYPE_ARRAY;
                break;
            default:
                return $phpType;
        }
    }

    /**
     * Get PHP type by given Swagger type
     * @param string $swType
     * @return string
     */
    protected function phpType($swType)
    {
        switch($swType) {
            case Variable::SW_TYPE_OBJECT:
                return 'array';
                break;
            case Variable::SW_TYPE_NUMBER:
                return 'float';
                break;
            default:
                return $swType;
        }
    }

    /**
     * Describe object properties
     * @param array $target
     * @param array $properties
     * @deprecated
     */
    protected static function describeProperties(&$target, $properties = [])
    {
        $target['properties'] = $target['properties'] ?? [];
        $obj = &$target['properties'];
        foreach ($properties as $key => $row) {
            $obj[$key] = Arr::only($properties, ['type', 'format', 'description', 'example']);
        }
    }

    /**
     * Get variable value from cache
     * @param  string $name
     * @param  string $type
     * @return mixed|null
     * @internal
     */
    public static function getVarCache($name, $type)
    {
        if (($key = self::getVarCacheKey($name, $type)) === null) {
            return null;
        }
        return Arr::get(self::$_varsCache, $key);
    }

    /**
     * Set variable value to cache
     * @param  string $name
     * @param  string $type
     * @param  mixed $value
     * @return mixed|null
     * @internal
     */
    public static function setVarCache($name, $type, $value)
    {
        if ($value !== null && ($key = self::getVarCacheKey($name, $type)) !== null) {
            Arr::set(self::$_varsCache, $key, $value);
        }
        return $value;
    }

    /**
     * Create variable cache string key
     * @param  string $name
     * @param  string $type
     * @return string|null
     */
    private static function getVarCacheKey($name, $type)
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
