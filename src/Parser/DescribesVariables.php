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
    /**
     * Get example value
     * @param string      $type
     * @param string|null $varName
     * @return mixed
     */
    public static function exampleValue(string $type, $varName = null)
    {
        $isArray = strpos($type, '[]') !== false;
        $type = $isArray ? substr($type, 0, -2) : $type;
        if (($example = static::exampleValueInternal($type, $varName)) === null) {
            return null;
        }
        return $isArray ? [$example] : $example;
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
                'pass',
                'remember_token',
                'email_token',
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
        ];
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
        switch ($rule) {
            case 'phone':
                return static::faker()->phoneNumber;
                break;
            case 'url':
                return static::faker()->url;
                break;
            case 'image':
                return static::faker()->imageUrl();
                break;
            case 'email':
                return static::faker()->email;
                break;
            case 'password':
                return static::faker()->password(16, 36);
                break;
            case 'token':
                return str_random(64);
                break;
            case 'service_name':
                return array_random(['fb', 'google', 'twitter']);
                break;
            case 'domain_name':
                return static::faker()->domainName;
                break;
            case 'alpha':
            case 'string':
                return array_random(['string', 'value', 'str value']);
                break;
            case 'alpha_num':
                return array_random(['string35', 'value90', 'str20value']);
                break;
            case 'alpha_dash':
                return array_random(['string_35', 'value-90', 'str_20-value']);
                break;
            case 'ip':
            case 'ipv4':
                return static::faker()->ipv4;
                break;
            case 'ipv6':
                return static::faker()->ipv6;
                break;
            case 'float':
                return static::faker()->randomFloat(2);
                break;
            case 'date':
                return static::faker()->date();
                break;
            case 'numeric':
            case 'integer':
                return static::faker()->numberBetween(1, 99);
                break;
            case 'boolean':
                return static::faker()->boolean;
                break;
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
}
