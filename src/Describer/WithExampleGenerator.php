<?php

namespace DigitSoft\Swagger\Describer;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Date;

/**
 * Trait WithExampleGenerator
 * @mixin WithTypeParser
 */
trait WithExampleGenerator
{
    use WithFaker;

    /**
     * @var array Generated variables cache
     */
    protected $varsCache = [];
    /**
     * @var array Generated sequences
     */
    protected $varsSequences = [];

    /**
     * Get variable example
     *
     * @param  string|null $type
     * @param  string|null $varName
     * @param  string|null $rule
     * @param  bool        $normalizeType
     * @return mixed|null
     */
    public function example(?string &$type, ?string $varName = null, ?string $rule = null, bool $normalizeType = false)
    {
        if ($varName === 'sex') { dump(func_get_args()); }
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
        $rule = $rule === null || $this->isBasicType($rule) ? $this->getVariableRule($varName, $rule ?? $varName) : $rule;
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
     * Generate example sequence by type
     *
     * @param  string|null $type
     * @param  int         $count
     * @return array
     */
    protected function generateExampleByTypeSequence(?string $type, int $count = 10)
    {
        $type = is_string($type) ? $this->normalizeType($type, true) : null;
        $sequence = [];
        for ($i = 1; $i <= $count; $i++) {
            $elem = $this->exampleByTypeSequential($type, $i);
            $sequence[] = $elem;
            if ($elem === null) {
                break;
            }
        }

        return $sequence;
    }

    /**
     * Generate example sequence by rule
     *
     * @param  string $rule
     * @param  int    $count
     * @return array
     */
    protected function generateExampleByRuleSequence(string $rule, int $count = 10)
    {
        $sequence = [];
        for ($i = 1; $i <= $count; $i++) {
            $elem = $this->exampleByRuleSequential($rule, $i);
            $sequence[] = $elem;
            if ($elem === null) {
                break;
            }
        }

        return $sequence;
    }

    /**
     * Get example by given type for sequence
     *
     * @param  string|null $type
     * @param  int         $iteration
     * @return mixed
     */
    protected function exampleByTypeSequential(?string $type, int $iteration = 1)
    {
        $dateStr = '2019-01-01 00:00:00';
        switch ($type) {
            case 'int':
            case 'integer':
                return (int)($iteration * 3);
            case 'float':
            case 'double':
                return 1.65 * $iteration;
            case 'string':
                $strArr = ['string', 'str value', 'str example', 'string data', 'some txt'];
                return $this->takeFromArray($strArr, $iteration);
            case 'bool':
            case 'boolean':
                return (bool)(($iteration % 2));
            case 'date':
                $date = Date::createFromFormat('Y-m-d H:i:s', $dateStr);
                $date->addSeconds($iteration * 800636);
                return $date->format('Y-m-d');
            case 'Illuminate\Support\Carbon':
            case 'dateTime':
            case 'datetime':
                $date = Date::createFromFormat('Y-m-d H:i:s', $dateStr);
                $date->addSeconds($iteration * 800636);
                return $date->format('Y-m-d H:i:s');
            case 'array':
                return [];
        }

        return null;
    }

    /**
     * Get example value by validation rule
     *
     * @param  string $rule
     * @param  int    $iteration
     * @return mixed
     */
    protected function exampleByRuleSequential(string $rule, int $iteration = 1)
    {
        $dateStr = '2019-01-01 00:00:00';
        switch ($rule) {
            case 'phone':
                $strArr = ['+380971234567', '+380441234567', '+15411234567', '+4901511234567'];
                $example = $this->takeFromArray($strArr, $iteration);
                break;
            case 'url':
                $example = 'http://example.com/url-' . $iteration . '-generated';
                break;
            case 'image':
                $example = 'https://lorempixel.com/640/480/?' . $iteration * 6842;
                break;
            case 'email':
                $examples = [
                    'nikolaus.jo@haag.net', 'oral46@gleichner.com', 'triston73@gmail.com', 'sedrick.russel@gmail.com',
                    'christian64@hotmail.com', 'lwill@baumbach.com', 'stanton.nicolas@schulist.net', 'fisher.benedict@yahoo.com',
                    'jaren85@dicki.info', 'thiel.maxwell@ortiz.org',
                ];
                $example = $this->takeFromArray($examples, $iteration);
                break;
            case 'password':
                $examples = [
                    'FwsU63aIflde0t2x', 'nYZxNonFfkPwmKRB', 'cLRdk4y0yVdK3QP4', 'LkWwjjdaVK1pGDQf', 'LdKBH0RXvlOOD6kg',
                    'YOgNltJWQrWf5AmQ', '8E4BbRtJO4MgRlJP', 'KLuOcU5EYjhLqbHB', 'zr3rZ5GNu1oSaeHQ', 'VpCgk7BS2QP2X5VT',
                ];
                $example = $this->takeFromArray($examples, $iteration);
                break;
            case 'token':
                $examples = [
                    'AmUfolr4CMVihtjvHgPcA3IAPGmV9Vknr44sAdAYcmNauKXBssVOjQrZFPlizrKO',
                    'KDr5Js6MkvH6XSDdgLF0sQv8RQvDBla3I2YADdtSON3JFK10sYIARZvL7MHv7FG8',
                    'aIoZAUw6cfcdOpKhlTFl6btgdbWAazQeujD35aFg6mW2c3RKYgtqtF7i03Vfe4Du',
                    'LeJuw7tMULngkbeePqA61Nv88Y4tZWRJkrW7ISkGMRdhYUuh9MhFJVdye81hlQQ1',
                    'C6ZJFs4zgJ3ejE13RVJvAwDG1fgv4w4nMoo5MbMDjp4y3JkFibWnrTlmutIQdBtH',
                    'tBWDClhFDE0kZJlL0v2iVp1xDJGCakrc3S7M4Btcc19f7x5SqbzxLRtvPMmdKcgO',
                    'ZOyU88s36oiCheUTxWwnDW0D21WkzG5RVl7x47Mo7hFmwJkHI4g9LoVgug6jSdsC',
                    'AGoIz32MiUcX7W58zeCThiQgyeMoiDa9As4cJz5lJPNaJyOh3XLKovKS69HJ4y6s',
                    'Z63TlMogLQ7ibz92psjTO8KrkVgp9KYuOFldOXcvbv2icpPtaaW08ekGqj0b7O8s',
                    'ITbeGihCUzEnolxEZbjSWYYTHxQDrNQIaHFPIdJfT36yZ1KimVxKN9b240NEsNpw',
                ];
                $example = $this->takeFromArray($examples, $iteration);
                break;
            case 'service_name':
                $example = $this->takeFromArray(['fb', 'google', 'twitter'], $iteration);
                break;
            case 'domain_name':
                $example = 'example-' . $iteration . '.com';
                break;
            case 'alpha':
            case 'string':
                $example = $this->takeFromArray(['string', 'value', 'str value'], $iteration);
                break;
            case 'text':
                $examples = [
                    'Ut tempora hic iusto assumenda. In aut quae possimus provident.',
                    'Culpa eius voluptate quae accusantium aut aut. Et ipsa quia aut sint facilis pariatur.',
                    'Et itaque qui omnis vero aut. Ipsa esse quae error sed enim. Est et ad similique.',
                    'Id aut et voluptatum odio rerum sint veritatis. Omnis dolores quisquam animi.',
                    'Omnis et sed sapiente ab. Consequatur voluptatem occaecati nihil atque et.',
                    'Beatae optio aperiam voluptatem dolor facere. Nesciunt cum ullam accusantium enim.',
                    'Illo ut eum sint. Possimus est quo vel assumenda. Sequi dolorem minus atque et iusto.',
                    'Dolores tempora quasi fugit alias. Soluta expedita autem dolor quasi minus.',
                    'Sequi nulla omnis quis atque. Sint et adipisci magni qui. Laboriosam et saepe tempora vel.',
                    'Delectus quia illo quia et molestiae. Vitae ex non modi sed iste non velit.',
                ];
                $example = $this->takeFromArray($examples, $iteration);
                break;
            case 'textShort':
                $examples = [
                    'Soluta quisquam qui tenetur molestias sequi.',
                    'Quia et rerum tenetur.',
                    'Ea est labore est sit et id.',
                    'Aut qui sed ut reprehenderit beatae est qui.',
                    'Ducimus id sed eaque id doloremque.',
                    'Odit molestias porro quia natus est quo.',
                    'Cupiditate aut aut sunt dolor ab sunt sunt.',
                    'Velit ut odio dolorem deleniti quas.',
                    'Consectetur at molestias repellendus.',
                    'Velit fuga culpa et et consequatur ea maxime.',
                ];
                $example = $this->takeFromArray($examples, $iteration);
                break;
            case 'alpha_num':
                $example = $this->takeFromArray(['string35', 'value90', 'str20value'], $iteration);
                break;
            case 'alpha_dash':
                $example = $this->takeFromArray(['string_35', 'value-90', 'str_20-value'], $iteration);
                break;
            case 'ip':
            case 'ipv4':
                $examples = [
                    '106.198.17.238', '92.249.253.53', '68.8.150.135', '57.37.186.183', '192.89.34.71',
                    '94.195.220.102', '24.185.102.94', '1.152.115.28', '72.47.37.220', '62.64.250.209',
                ];
                $example = $this->takeFromArray($examples, $iteration);
                break;
            case 'ipv6':
                $examples = [
                    'bcd2:21a4:3e52:6427:8b21:c58c:74e8:88a7',
                    '8b44:561e:9514:e750:95a9:57a:aacd:4a37',
                    'cacc:4f6a:76ba:2d45:cf08:401c:b2d3:e48',
                    '5547:bd8f:39fe:230c:750c:9e6c:b6d2:6440',
                    '1fbe:2af5:6f3f:1d1c:eaf4:cae1:8ba1:7e23',
                    '66e6:1e1e:8bc6:fa4f:279a:32ef:8489:4fac',
                    '8221:32fe:1ed3:d582:879a:55ca:61c4:7516',
                    'aa68:41af:f8a6:932f:4e84:bf11:f08a:aead',
                    '553f:be11:1c25:4da8:88c:d498:83e2:c2b1',
                    '6cf7:d7d9:11a4:5fd0:7627:a0a:cec4:d5b6',
                ];
                $example = $this->takeFromArray($examples, $iteration);
                break;
            case 'float':
                $example = $iteration * 1.65;
                break;
            case 'date_format':
            case 'date':
                $date = Date::createFromFormat('Y-m-d H:i:s', $dateStr);
                $date->addSeconds($iteration * 800636);
                $example = $date->format('Y-m-d');
                break;
            case 'date-time':
            case 'dateTime':
            case 'datetime':
                $date = Date::createFromFormat('Y-m-d H:i:s', $dateStr);
                $date->addSeconds($iteration * 800636);
                $example = $date->format('Y-m-d H:i:s');
                break;
            case 'numeric':
            case 'integer':
                $example = (int)($iteration * 3);
                break;
            case 'boolean':
                $example = $this->takeFromArray([false, true], $iteration);
                break;
            case 'company_name':
                $examples = [
                    "Walker Group", "Bogan, Abernathy and Parker", "Gutkowski, Stracke and Treutel", "Green Group", "Ortiz-Schmidt",
                    "Reilly Inc", "Dach-Donnelly", "Koepp, Raynor and Gerlach", "Padberg PLC", "Graham, Lowe and Harber",
                ];
                $example = $this->takeFromArray($examples, $iteration);
                break;
            case 'first_name':
                $examples = [
                    'Myriam', 'Sylvan', 'Eldred', 'Joana', 'Carson', 'Madisyn', 'Trever', 'Scotty', 'Oran', 'Guadalupe',
                ];
                $example = $this->takeFromArray($examples, $iteration);
                break;
            case 'last_name':
                $examples = [
                    'Fay', 'Cartwright', 'Hansen', 'Swift', 'Crooks', 'Ortiz', 'Johns', 'Howell', 'Stehr', 'Brown',
                ];
                $example = $this->takeFromArray($examples, $iteration);
                break;
            case 'address':
                $examples = [
                    "611 Thompson Way Suite 012\nEast Elza, FL 57666-8738",
                    "30319 Fay Spurs Apt. 662\nMurphystad, LA 07944",
                    "779 Lamont Landing\nPort Allenburgh, AL 54941-5287",
                    "8360 Imogene Turnpike Suite 023\nLake Baby, FL 26431",
                    "60943 Keira Turnpike\nSteuberport, DE 69137-2357",
                    "77390 Koby Crescent Suite 624\nWintheiserton, AR 67349",
                    "117 Rice Ramp Suite 251\nSouth Horace, AK 97749-6015",
                    "1825 Tiara Path\nNew Maia, SC 74108",
                    "790 Sallie Rest\nMaggioland, CO 32943",
                    "2523 Bergnaum Ferry Suite 247\nJoaquinberg, MS 54512",
                ];
                $example = $this->takeFromArray($examples, $iteration);
                break;
            case 'currency_code':
                $example = $this->takeFromArray(['UAH', 'USD', 'EUR', 'CZK', 'AUD', 'CHF', 'CAD'], $iteration);
                break;
            case 'sex':
                $example = $this->takeFromArray(['male', 'female'], $iteration);
                break;
            default:
                $example = null;
        }

        return $example;
    }

    /**
     * Get example by given type
     *
     * @param  string|null $type
     * @return array|int|string|null
     */
    protected function exampleByType(?string $type)
    {
        $type = is_string($type) ? $this->normalizeType($type, true) : null;
        $key = $type;
        if (! isset($this->varsSequences[$key])) {
            $this->varsSequences[$key] = $this->generateExampleByTypeSequence($type, 10);
        }
        $example = current($this->varsSequences[$key]);
        if (next($this->varsSequences[$key]) === false) {
            reset($this->varsSequences[$key]);
        }

        return $example;
    }

    /**
     * Get example value by validation rule
     *
     * @param  string $rule
     * @return mixed
     */
    protected function exampleByRule(string $rule)
    {
        $key = '__' . $rule;
        if (! isset($this->varsSequences[$key])) {
            $this->varsSequences[$key] = $this->generateExampleByRuleSequence($rule, 10);
        }
        $example = current($this->varsSequences[$key]);
        if (next($this->varsSequences[$key]) === false) {
            reset($this->varsSequences[$key]);
        }

        return $example;
    }

    /**
     * Get variable value from cache
     *
     * @param  string      $name
     * @param  string|null $type
     * @return mixed|null
     * @internal
     */
    protected function getVarCache(string $name, ?string $type)
    {
        if (($key = $this->getVarCacheKey($name, $type)) === null) {
            return null;
        }

        return Arr::get($this->varsCache, $key);
    }

    /**
     * Set variable value to cache
     *
     * @param  string      $name
     * @param  string|null $type
     * @param  mixed       $value
     * @return mixed|null
     * @internal
     */
    protected function setVarCache(string $name, ?string $type, $value)
    {
        if ($value !== null && ($key = $this->getVarCacheKey($name, $type)) !== null) {
            Arr::set($this->varsCache, $key, $value);
        }

        return $value;
    }

    /**
     * @param  string $type
     * @return mixed
     * @internal
     */
    protected function exampleByTypeInternal(string $type)
    {
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
                return Arr::random(['string', 'value', 'str value']);
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
     * @param  string $rule
     * @return mixed
     * @internal
     */
    protected function exampleByRuleInternal(string $rule)
    {
        switch ($rule) {
            case 'phone':
                $example = Arr::random(['+380971234567', '+380441234567', '+15411234567', '+4901511234567']);
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
                $example = Str::random(16);
                break;
            case 'token':
                $example = Str::random(64);
                break;
            case 'service_name':
                $example = Arr::random(['fb', 'google', 'twitter']);
                break;
            case 'domain_name':
                $example = $this->faker()->domainName;
                break;
            case 'alpha':
            case 'string':
                $example = Arr::random(['string', 'value', 'str value']);
                break;
            case 'text':
                $example = $this->faker()->text(100);
                break;
            case 'textShort':
                $example = $this->faker()->text(50);
                break;
            case 'alpha_num':
                $example = Arr::random(['string35', 'value90', 'str20value']);
                break;
            case 'alpha_dash':
                $example = Arr::random(['string_35', 'value-90', 'str_20-value']);
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
                $example = $this->faker()->lastName;
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
     * Create variable cache string key
     *
     * @param  string      $name
     * @param  string|null $type
     * @return string|null
     */
    private function getVarCacheKey(string $name, ?string $type)
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

    /**
     * Take value from array even if it does not exists by given offset
     *
     * @param  array $array
     * @param  int   $number
     * @return mixed
     */
    private function takeFromArray(array $array, int $number)
    {
        if (empty($array)) {
            return null;
        }
        if (! isset($array[$number])) {
            $number = $number % count($array);
        }

        return $array[$number];
    }
}
