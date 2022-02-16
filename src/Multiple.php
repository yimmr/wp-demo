<?php

namespace Imon\WP\Demo;

class Multiple
{
    public static function createReplace($key, $array)
    {
        if (!is_array($key)) {
            return array_combine(array_map(fn($index) => $index . '.' . $key, array_keys($array)), $array);
        }

        $result = [];

        foreach ($key as $name => $values) {
            foreach ($values as $index => $value) {
                $result[$index . '.' . $name] = $value;
            }
        }

        return $result;
    }

    /**
     * 用于特定索引的键名单独放一组
     *
     * @param array $keys
     * @param array $indexKeys
     */
    public static function splitIndexKeys(&$keys, &$indexKeys)
    {
        $indexKeys = [];
        $keys      = array_filter($keys, function ($key) use (&$indexKeys) {
            $keys = explode('.', $key);

            if ($result = is_numeric($index = array_shift($keys))) {
                is_array($indexKeys[$index]) || ($indexKeys[$index] = []);

                $indexKeys[$index][] = implode('.', $keys);
            }

            return !$result;
        });

        $indexKeys = array_map(function ($value) use (&$keys) {
            return array_unique(array_merge($keys, $value));
        }, $indexKeys);
    }

    /**
     * 重复创建多条数据
     *
     * @param array $data
     * @param int $number
     * @param array $rules
     * @param array $replace 替换指定数据或第n个数据，序号从0开始
     * @return array
     */
    public static function create(array $data, $number = 10, $rules = [], $replace = [])
    {
        $rules = array_filter($rules, function ($key) use (&$replace) {
            return !isset($replace[$key]) && !self::arrHas($replace, $key);
        }, \ARRAY_FILTER_USE_KEY);

        $results     = array_fill(0, $number, $data);
        $ruleKeys    = array_keys($rules);
        $replaceKeys = array_keys($replace);

        self::splitIndexKeys($ruleKeys, $ruleIndexKeys);
        self::splitIndexKeys($replaceKeys, $replaceIndexKeys);

        foreach ($results as $index => $data) {
            $keypre = isset($ruleIndexKeys[$index]) ? "{$index}." : '';

            foreach (($ruleIndexKeys[$index] ?? $ruleKeys) as $key) {
                $method = $rules[$keypre . $key] ?? $rules[$key];
                $params = null;

                if (is_array($method)) {
                    $params = $method['params'] ?? null;
                    $method = $method['method'] ?? '';
                }

                if (method_exists(static::class, $method)) {
                    if (!isset($params)) {
                        $params = $method == 'affix' ? ['[' . ($index + 1) . ']'] : [];
                    } else {
                        $params = is_array($params) ? $params : [$params];
                    }

                    self::arrSet($data, $key, static::$method(self::arrGet($data, $key), ...$params));
                } else {
                    self::arrSet($data, $key, call_user_func($method, self::arrGet($data, $key), $index));
                }
            }

            $keypre = isset($replaceIndexKeys[$index]) ? "{$index}." : '';

            foreach (($replaceIndexKeys[$index] ?? $replaceKeys) as $key) {
                self::arrSet($data, $key, $replace[$keypre . $key] ?? $replace[$key]);
            }

            $results[$index] = $data;
        }

        return $results;
    }

    /**
     * 附加前缀或后缀，可重复多条数据
     *
     * @param string $string
     * @param string|int|array $before
     * @param string|int|array $after
     * @return string|array
     */
    public static function affix($string, $before = '', $after = '')
    {
        if (is_string($before) && is_string($after)) {
            return $before . $string . $after;
        }

        foreach (compact('before', 'after') as $key => $value) {
            if (is_int($value)) {
                $$key = array_map(function ($n) {return "[{$n}]";}, range(1, $value));
            } elseif (is_string($value)) {
                $$key = [$value];
            }
        }

        $results = array_map(function ($b, $a) use ($string) {
            return $b . $string . $a;
        }, $before, $after);

        return count($results) > 1 ? $results : current($results);
    }

    /**
     * 打乱数组再合并成字符串返回
     *
     * @param string|array $content
     * @param string $separator
     * @return string
     */
    public static function contentRand($content, $separator = '')
    {
        if (!is_array($content)) {
            return $content;
        }

        shuffle($content);

        return implode($separator, $content);
    }

    /**
     * 随机取n个术语名称，再将他们随机用作父或子项
     *
     * @param array $names
     * @param int $depth
     * @return array
     */
    public static function termsRand(array $names, $depth = 1)
    {
        $keys = (array) array_rand($names, rand(1, count($names)));

        if ($depth == 1) {
            return array_map(function ($key) use (&$names) {
                return $names[$key];
            }, $keys);
        }

        $results = [];

        while (count($keys)) {
            if (($level = rand(0, $depth - 1)) == 0) {
                $results[] = $names[array_shift($keys)];
                continue;
            }

            $terms = &$results;
            $a     = 0;

            while ($a <= $level) {
                if (count($keys) == 1) {
                    break;
                }

                if (empty($terms)) {
                    $terms[] = $names[array_shift($keys)];
                }

                $parentKey = array_rand($terms);

                if (is_array($parent = $terms[$parentKey])) {
                    $terms = &$parent['child'];
                } else {
                    $terms[$parentKey] = ['name' => $parent, 'child' => []];
                    $terms             = &$terms[$parentKey]['child'];
                }

                ++$a;
            }

            $terms[] = $names[array_shift($keys)];
        }

        return $results;
    }

    /**
     * 判断数组项
     *
     * @param array $array
     * @param string $key
     * @return bool
     */
    public static function arrHas(array $array, $key)
    {
        if (!$array || !$key) {
            return false;
        }

        foreach (explode('.', $key) as $key) {
            if (array_key_exists($key, $array)) {
                $array = $array[$key];
            } else {
                return false;
            }
        }

        return true;
    }

    /**
     * 读取数组值
     *
     * @param array $array
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public static function arrGet(array $array, $key, $default = null)
    {
        foreach (explode('.', $key) as $key) {
            if (is_array($array) && array_key_exists($key, $array)) {
                $array = $array[$key];
            } else {
                return $default;
            }
        }

        return $array;
    }

    /**
     * 设置数组项
     *
     * @param array $array
     * @param string $key
     * @param mixed $value
     */
    public static function arrSet(array &$array, $key, $value = null)
    {
        if (is_null($key)) {
            $array = $value;
            return;
        }

        $keys = explode('.', $key);

        foreach ($keys as $i => $key) {
            if (count($keys) === 1) {
                break;
            }

            unset($keys[$i]);

            if (!isset($array[$key]) || !is_array($array[$key])) {
                $array[$key] = [];
            }

            $array = &$array[$key];
        }

        $array[array_shift($keys)] = $value;
    }
}