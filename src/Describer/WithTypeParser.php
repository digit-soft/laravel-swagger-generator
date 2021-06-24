<?php

namespace DigitSoft\Swagger\Describer;

use Illuminate\Support\Arr;
use DigitSoft\Swagger\Yaml\Variable;

/**
 * Trait WithTypeParser
 * @package DigitSoft\Swagger\Describer
 * @mixin WithExampleGenerator
 */
trait WithTypeParser
{
    /** @var array Basic types list */
    protected $basicTypes = [
        'string', 'integer', 'float', 'object', 'boolean', 'null', 'array', 'resource',
    ];
    /** @var array Basic types shortcuts */
    protected $basicTypesSyn = [
        'int' => 'integer',
        'bool' => 'boolean',
    ];
    /** @var array List of simplified class types */
    protected $classSimpleTypes = [
        \Illuminate\Support\Carbon::class => 'string',
    ];
    //TODO: Combine rules description and example generation
    /** @var array Rules data (with PHP type and variable names) */
    protected $varRules = [
        'url' => [
            'type' => 'string',
            'names' => [
                'url',
                'site',
            ],
        ],
        'numeric' => [
            'type' => 'float',
            'names' => [],
        ],
        'image' => [
            'type' => 'string',
            'names' => [
                'logo',
                'avatar',
                'image',
                'attachment_url',
                'file_url',
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
        'text' => [
            'type' => 'string',
            'names' => [
                'description',
            ],
        ],
        'textShort' => [
            'type' => 'string',
            'names' => [
                'title',
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
        'date' => [
            'type' => 'string',
            'names' => [],
        ],
        'date_format' => [
            'type' => 'string',
            'names' => [],
        ],
    ];

    protected $varRuleNames;
    protected $varRuleNamesSortedRegex;

    /**
     * Check that given type is basic
     *
     * @param  string $type
     * @return bool
     */
    public function isBasicType($type)
    {
        $type = $this->normalizeType($type, true);

        return in_array($type, $this->basicTypes, true);
    }

    /**
     * Check that given type is array of types
     *
     * @param  string $type
     * @return bool
     */
    public function isTypeArray($type)
    {
        $type = $this->normalizeType($type);

        return strpos($type, '[]') !== false;
    }

    /**
     * Check that given type is a class name
     *
     * @param  string $type
     * @return bool
     */
    public function isTypeClassName($type)
    {
        $type = $this->normalizeType($type, true);

        return ! in_array($type, $this->basicTypes, true) && (class_exists($type) || interface_exists($type));
    }

    /**
     * Normalize type name
     *
     * @param  string $type
     * @param  bool   $stripArray
     * @return string
     */
    public function normalizeType(string $type, $stripArray = false)
    {
        $type = strpos($type, '|') ? explode('|', $type)[0] : $type;
        if ($stripArray && $this->isTypeArray($type)) {
            $type = substr($type, 0, -2);
        }
        $typeLower = strtolower($type);
        if (isset($this->basicTypesSyn[$typeLower])) {
            return $this->basicTypesSyn[$typeLower];
        }
        if (strpos($type, '\\') !== false || class_exists($type)) {
            return ltrim($type, '\\');
        }

        return $typeLower;
    }

    /**
     * Get swagger type by example variable
     *
     * @param  mixed $example
     * @return string|null
     */
    public function swaggerTypeByExample($example)
    {
        if ($example === null) {
            return null;
        }
        $swType = $this->swaggerType(gettype($example));
        if ($swType === Variable::SW_TYPE_ARRAY && Arr::isAssoc($example)) {
            $swType = Variable::SW_TYPE_OBJECT;
        }

        return $swType;
    }

    /**
     * Get swagger type by given PHP type
     *
     * @param  string $phpType
     * @return string|null
     */
    public function swaggerType($phpType)
    {
        if ($this->isTypeArray($phpType)) {
            $phpType = 'array';
        } elseif ($this->isTypeClassName($phpType)) {
            $phpType = $this->simplifyClassName($phpType);
        }
        switch ($phpType) {
            case 'string':
                return Variable::SW_TYPE_STRING;
            case 'integer':
                return Variable::SW_TYPE_INTEGER;
            case 'float':
            case 'double':
                return Variable::SW_TYPE_NUMBER;
            case 'object':
                return Variable::SW_TYPE_OBJECT;
            case 'array':
                return Variable::SW_TYPE_ARRAY;
            default:
                return $phpType;
        }
    }

    /**
     * Get PHP type by given Swagger type
     *
     * @param  string $swType
     * @return string
     */
    public function phpType($swType)
    {
        switch ($swType) {
            case Variable::SW_TYPE_OBJECT:
                return 'array';
            case Variable::SW_TYPE_NUMBER:
                return 'float';
            default:
                return $swType;
        }
    }

    /**
     * Simplify class name to basic type
     *
     * @param  string $className
     * @return mixed|string
     */
    public function simplifyClassName($className)
    {
        $className = ltrim($className, '\\');

        return $this->classSimpleTypes[$className] ?? $className;
    }

    /**
     * Get possible rule for a variable name
     *
     * @param  string      $varName
     * @param  string|null $default
     * @return string|null
     */
    protected function getVariableRule($varName, $default = null)
    {
        if ($varName === null) {
            return null;
        }
        $cleanName = $this->cleanupVariableName($varName);
        $varNames = $cleanName !== null && $cleanName !== $varName ? [$varName, $cleanName] : [$varName];
        foreach ($varNames as $name) {
            foreach ($this->varRules as $rule => $ruleData) {
                if (in_array($name, $ruleData['names'], true)) {
                    return $rule;
                }
            }
        }

        return $default;
    }

    /**
     * Get rule type (for php)
     *
     * @param  string $rule
     * @return string|null
     */
    protected function getRuleType($rule)
    {
        if (is_string($rule) && isset($this->varRules[$rule])) {
            return $this->varRules[$rule]['type'];
        }
        if ($this->isBasicType($rule)) {
            return $rule;
        }

        return null;
    }

    /**
     * Cleanup variable name (if nested or have suffix appended)
     *
     * @param  string $name
     * @return string|null
     */
    protected function cleanupVariableName($name)
    {
        $suffixes = ['_confirm', '_original', '_example', '_new', '_old'];
        // Name is not a string
        if (! is_string($name)) {
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

        // Check for ending
        $regex = $this->getRulesPossibleVarNamesRegex();
        if (preg_match($regex, $name, $matches)) {
            return end($matches);
        }

        return $name;
    }

    /**
     * Get regEx for matching var name with possible var names in rules.
     *
     * @return string
     */
    protected function getRulesPossibleVarNamesRegex()
    {
        if ($this->varRuleNamesSortedRegex !== null) {
            return $this->varRuleNamesSortedRegex;
        }

        $names = array_keys($this->getRulesPossibleVarNames());
        sort($names);
        usort($names, function ($a, $b) {
            $al = mb_strlen($a);
            $bl = mb_strlen($b);

            if ($al === $bl) {
                return 0;
            }

            return $al > $bl ? -1 : 1;
        });

        array_walk($names, function (&$name) {
            $name = "_({$name})";
        });
        $regex = '/(?:' . implode('|', $names) . ')$/';

        return $this->varRuleNamesSortedRegex = $regex;
    }

    /**
     * Get possible variable names for rules.
     *
     * @return array
     */
    protected function getRulesPossibleVarNames()
    {
        if ($this->varRuleNames !== null) {
            return $this->varRuleNames;
        }
        $names = [];
        foreach ($this->varRules as $ruleName => $data) {
            $dataShort = ['rule' => $ruleName, 'type' => $data['type']];
            $names[$ruleName] = $dataShort;
            if (empty($data['names'])) {
                continue;
            }
            foreach ($data['names'] as $name) {
                // Throw an error if duplicates occur
                if (isset($names[$name]) && $names[$name]['type'] !== $dataShort['type']) {
                    throw new \RuntimeException(sprintf("Duplicate name '%s' in variable rules.", $name));
                }
                $names[$name] = $dataShort;
            }
        }

        return $this->varRuleNames = $names;
    }
}
