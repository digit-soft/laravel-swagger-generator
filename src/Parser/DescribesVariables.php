<?php

namespace DigitSoft\Swagger\Parser;

use DigitSoft\Swagger\DumperYaml;
use DigitSoft\Swagger\Yaml\Variable;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * Trait DescribesVariables
 * @package DigitSoft\Swagger\Parser
 * @mixin WithFaker
 */
trait DescribesVariables
{
    private static $varsCache = [];

    /**
     * Get example value
     * @param string      $type
     * @param string|null $varName
     * @return mixed
     */
    public static function exampleValue(string $type, $varName = null)
    {
        if (($cachedValue = DescribesVariables::getVarCache($varName, $type)) !== null) {
            return $cachedValue;
        }
        $isArray = strpos($type, '[]') !== false;
        $type = $isArray ? substr($type, 0, -2) : $type;
        if (($example = self::exampleValueInternal($type, $varName)) === null) {
            return null;
        }
        $example = $isArray ? [$example] : $example;
        return DescribesVariables::setVarCache($varName, $type, $example);
    }

    /**
     * Get example value (internal)
     * @param string      $type
     * @param string|null $varName
     * @return mixed
     * @internal
     */
    protected static function exampleValueInternal(string $type, $varName = null)
    {
        if (strpos($type, '\\') === 0) {
            $type = substr($type, 1);
        }
        $generalTypes = ['string', 'mixed', 'null'];
        if (in_array($type, $generalTypes) && $varName !== null && ($typeByName = static::exampleByName($varName)) !== null) {
            return $typeByName;
        }
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
                return static::faker()->dateTimeBetween('-1 month')->format('Y-m-d H:i:s');
                break;
            case 'array':
                return [];
                break;
        }
        return null;
    }

    /**
     * Get example value by it`s name
     * @param string $name
     * @return mixed|null
     */
    protected static function exampleByName(string $name)
    {
        $subTypes = [
            'url' => [
                'url',
            ],
            'image' => [
                'logo',
                'avatar',
                'image',
            ],
            'email' => [
                'email',
                'mail',
            ],
            'password' => [
                'password',
                'password_confirm',
                'pass',
                'new_password',
                'password_new',
            ],
            'token' => [
                'token',
                'access_token',
                'email_token',
                'remember_token',
                'service_token',
            ],
            'domain_name' => [
                'domain',
                'domain_name',
                'domainName',
            ],
            'service_name' => [
                'service_name',
                'serviceName',
            ],
            'phone' => [
                'phone',
                'phone_number',
                'phone_numbers',
                'phones',
            ],
            'first_name' => [
                'first_name',
                'firstName',
            ],
            'last_name' => [
                'last_name',
                'lastName',
            ],
            'address' => [
                'address',
                'post_address',
                'postAddress',
            ],
        ];
        // Name is nested
        if (strpos($name, '.') !== false) {
            $nameExp = explode('.', $name);
            $name = last($nameExp);
        }
        foreach ($subTypes as $subType => $names) {
            if (in_array($name, $names)) {
                return static::exampleByRule($subType);
            }
        }
        return null;
    }

    /**
     * Get example value by validation rule
     * @param string      $rule
     * @param string|null $varName
     * @return mixed
     */
    protected static function exampleByRule(string $rule, $varName = null)
    {
        $generalTypes = ['string'];
        if (in_array($rule, $generalTypes) && $varName !== null && ($typeByName = static::exampleByName($varName)) !== null) {
            return $typeByName;
        }
        $trueTypes = ['numeric' => 'integer', 'integer' => 'integer', 'boolean' => 'boolean'];
        $trueType = $trueTypes[$rule] ?? 'string';
        if (($cached = DescribesVariables::getVarCache($varName, $trueType)) !== null) {
            return $cached;
        }
        $example = null;
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
                $example = static::faker()->date();
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
        }
        return DescribesVariables::setVarCache($varName, $trueType, $example);
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
     */
    public static function getVarCache($name, $type)
    {
        if (($key = self::getVarCacheKey($name, $type)) === null) {
            return null;
        }
        return Arr::get(self::$varsCache, $key);
    }

    /**
     * Set variable value to cache
     * @param  string $name
     * @param  string $type
     * @param  mixed $value
     * @return mixed|null
     */
    public static function setVarCache($name, $type, $value)
    {
        if ($value !== null && ($key = self::getVarCacheKey($name, $type)) !== null) {
            Arr::set(self::$varsCache, $key, $value);
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
