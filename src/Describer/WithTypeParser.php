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
    protected array $basicTypes = [
        'string', 'integer', 'float', 'object', 'boolean', 'null', 'array', 'resource',
    ];
    /** @var array Basic types shortcuts */
    protected array $basicTypesSyn = [
        'int' => 'integer',
        'bool' => 'boolean',
    ];
    /** @var array List of simplified class types */
    protected array $classSimpleTypes = [
        \Illuminate\Support\Carbon::class => 'string',
        \Carbon\Carbon::class => 'string',
    ];
    //TODO: Combine rules description and example generation
    /** @var array Rules data (with PHP type and variable names) */
    protected array $varRules = [
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
        'company_name' => [
            'type' => 'string',
            'names' => [
                'company_name',
                'companyName',
                'company',
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

    protected ?array $varRuleNames = null;
    protected ?string $varRuleNamesSortedRegex = null;
    protected array $classExistChecks = [];

    /**
     * Check that given type is basic
     *
     * @param  string $type
     * @return bool
     */
    public function isBasicType(string $type): bool
    {
        $type = $this->normalizeType($type, true);

        return in_array($type, $this->basicTypes, true);
    }

    /**
     * Check that given type is array of types
     *
     * @param  string $type
     * @param  bool   $normalize
     * @return bool
     */
    public function isTypeArray(string $type, bool $normalize = true): bool
    {
        $type = $normalize ? $this->normalizeType($type) : $type;

        return str_contains($type, '[]');
    }

    /**
     * Check that given type is a class name
     *
     * @param  string $type
     * @return bool
     */
    public function isTypeClassName(string $type): bool
    {
        if (isset($this->classExistChecks[$type])) {
            return $this->classExistChecks[$type];
        }
        $typeClean = $this->normalizeType($type, true);

        return $this->classExistChecks[$type] = $this->classExistChecks[$typeClean] =
            (! in_array($typeClean, $this->basicTypes, true) && (class_exists($typeClean) || interface_exists($typeClean)));
    }

    /**
     * Normalize type name.
     *
     * @param  string $type
     * @param  bool   $stripArray
     * @return string
     */
    public function normalizeType(string $type, bool $stripArray = false): string
    {
        $type = strpos($type, '|') ? explode('|', $type)[0] : $type;
        if ($stripArray && $this->isTypeArray($type, false)) {
            $type = substr($type, 0, -2);
        }
        $typeLower = strtolower($type);
        if (isset($this->basicTypesSyn[$typeLower])) {
            return $this->basicTypesSyn[$typeLower];
        }
        if (str_contains($type, '\\') || class_exists($type) || interface_exists($type)) {
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
    public function swaggerTypeByExample(mixed $example): ?string
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
    public function swaggerType(string $phpType): ?string
    {
        if ($this->isTypeArray($phpType)) {
            $phpType = 'array';
        } elseif ($this->isTypeClassName($phpType)) {
            $phpType = $this->simplifyClassName($phpType);
        }

        return match ($phpType) {
            'string', 'null' => Variable::SW_TYPE_STRING,
            'int', 'integer' => Variable::SW_TYPE_INTEGER,
            'float', 'double' => Variable::SW_TYPE_NUMBER,
            'object' => Variable::SW_TYPE_OBJECT,
            'array' => Variable::SW_TYPE_ARRAY,
            default => $phpType,
        };
    }

    /**
     * Get PHP type by given Swagger type
     *
     * @param  string|null $swType
     * @return string|null
     */
    public function phpType(?string $swType): ?string
    {
        return match ($swType) {
            Variable::SW_TYPE_OBJECT => 'array',
            Variable::SW_TYPE_NUMBER => 'float',
            default => $swType,
        };
    }

    /**
     * Simplify class name to basic type
     *
     * @param  string $className
     * @return string
     */
    public function simplifyClassName(string $className): string
    {
        $className = ltrim($className, '\\');

        return $this->classSimpleTypes[$className] ?? $className;
    }

    /**
     * Determines whether a value is suitable for given swagger type.
     *
     * @param  string $swType       Swagger type
     * @param  mixed  $value        Value to check
     * @param  bool   $excludeEmpty Exclude empty values for array and object
     * @return bool
     */
    public function isValueSuitableForType(string $swType, mixed $value, bool $excludeEmpty = true): bool
    {
        return match ($swType) {
            Variable::SW_TYPE_OBJECT => is_array($value) && (! $excludeEmpty || ! empty($value)) && array_keys($value) !== array_keys(array_values($value)),
            Variable::SW_TYPE_ARRAY => is_array($value) && (! $excludeEmpty || ! empty($value)) && array_keys($value) === array_keys(array_values($value)),
            Variable::SW_TYPE_NUMBER => is_numeric($value),
            Variable::SW_TYPE_INTEGER => is_int($value),
            Variable::SW_TYPE_STRING => is_string($value),
            Variable::SW_TYPE_BOOLEAN => is_bool($value),
            default => false,
        };
    }

    /**
     * Get possible rule for a variable name
     *
     * @param  string|null $varName
     * @param  string|null $default
     * @return string|null
     */
    protected function getVariableRule(?string $varName, ?string $default = null): ?string
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
    protected function getRuleType(string $rule): ?string
    {
        if (isset($this->varRules[$rule])) {
            return $this->varRules[$rule]['type'];
        }

        return $this->isBasicType($rule) ? $rule : null;
    }

    /**
     * Cleanup variable name (if nested or have suffix appended)
     *
     * @param  string $name
     * @return string
     */
    protected function cleanupVariableName(string $name): string
    {
        $suffixes = ['_confirm', '_original', '_example', '_new', '_old'];
        // Name is nested
        if (str_contains($name, '.')) {
            $nameExp = explode('.', $name);
            /** @noinspection CallableParameterUseCaseInTypeContextInspection */
            $name = end($nameExp);
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
    protected function getRulesPossibleVarNamesRegex(): string
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
    protected function getRulesPossibleVarNames(): array
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
