<?php
/**
 * 参数校验工具
 * 
 * 支持规则：
 * - required      必填
 * - string        字符串
 * - int           整数
 * - numeric       数字
 * - email         邮箱
 * - url           URL
 * - mobile        手机号（中国）
 * - array         数组
 * - json          JSON 字符串
 * - min:N         最小长度/值
 * - max:N         最大长度/值
 * - between:N,M   介于 N 和 M 之间
 * - in:a,b,c      在指定值列表中
 * - pattern:/.../ 正则匹配
 * - optional      可选（null 或空字符串跳过后续校验）
 */

namespace Wxwork\Core;

class Validator
{
    /** @var array 校验错误 */
    private array $errors = [];

    /**
     * 校验数据
     * @param array $data  待校验的数据
     * @param array $rules 规则定义
     * @return array ['valid' => bool, 'errors' => ['field' => 'msg', ...]]
     * 
     * 示例：
     * $result = Validator::validate($_POST, [
     *     'userid' => 'required|string|max:64',
     *     'name'   => 'required|string|max:200',
     *     'mobile' => 'required|mobile',
     *     'email'  => 'optional|email',
     * ]);
     */
    public static function validate(array $data, array $rules): array
    {
        $instance = new self();
        $instance->errors = [];

        foreach ($rules as $field => $ruleStr) {
            $ruleList = explode('|', $ruleStr);
            $value = $data[$field] ?? null;

            foreach ($ruleList as $rule) {
                $rule = trim($rule);
                if (empty($rule)) continue;

                // 解析规则参数
                $ruleName = $rule;
                $params = [];

                if (str_contains($rule, ':')) {
                    $parts = explode(':', $rule, 2);
                    $ruleName = $parts[0];
                    $params = explode(',', $parts[1]);
                }

                $methodName = 'rule' . ucfirst(str_replace('_', '', $ruleName));

                // 如果是 optional 且值为空，跳过后续校验
                if ($ruleName === 'optional') {
                    if ($value === null || $value === '') {
                        break; // 跳出当前字段的规则循环
                    }
                    continue;
                }

                if (method_exists($instance, $methodName)) {
                    $result = $instance->$methodName($field, $value, $params);
                    if ($result !== true) {
                        $instance->errors[$field] = $result;
                        break; // 一个校验失败即停止该字段后续校验
                    }
                }
            }
        }

        return [
            'valid'  => empty($instance->errors),
            'errors' => $instance->errors,
        ];
    }

    /**
     * 快速校验，失败直接抛异常
     * @return array 校验通过的数据
     * @throws ValidationException
     */
    public static function validateOrFail(array $data, array $rules): array
    {
        $result = self::validate($data, $rules);
        if (!$result['valid']) {
            throw new ValidationException('参数校验失败', $result['errors']);
        }
        // 返回通过校验的字段值
        $validated = [];
        foreach ($rules as $field => $ruleStr) {
            if (array_key_exists($field, $data)) {
                $validated[$field] = $data[$field];
            }
        }
        return $validated;
    }

    // ===================== 规则方法 =====================

    protected function ruleRequired(string $field, mixed $value, array $params): true|string
    {
        if ($value === null || $value === '' || (is_array($value) && empty($value))) {
            return "{$field} 是必填项";
        }
        return true;
    }

    protected function ruleOptional(string $field, mixed $value, array $params): true|string
    {
        // optional 在 validate 中特殊处理，这里不执行
        return true;
    }

    protected function ruleString(string $field, mixed $value, array $params): true|string
    {
        if ($value !== null && !is_string($value)) {
            return "{$field} 必须是字符串";
        }
        return true;
    }

    protected function ruleInt(string $field, mixed $value, array $params): true|string
    {
        if ($value !== null && $value !== '' && !is_numeric($value)) {
            return "{$field} 必须是整数";
        }
        if ((string)(int)$value !== (string)$value) {
            return "{$field} 必须是整数";
        }
        return true;
    }

    protected function ruleNumeric(string $field, mixed $value, array $params): true|string
    {
        if ($value !== null && $value !== '' && !is_numeric($value)) {
            return "{$field} 必须是数字";
        }
        return true;
    }

    protected function ruleEmail(string $field, mixed $value, array $params): true|string
    {
        if ($value !== null && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return "{$field} 不是有效的邮箱地址";
        }
        return true;
    }

    protected function ruleUrl(string $field, mixed $value, array $params): true|string
    {
        if ($value !== null && $value !== '' && !filter_var($value, FILTER_VALIDATE_URL)) {
            return "{$field} 不是有效的 URL";
        }
        return true;
    }

    protected function ruleMobile(string $field, mixed $value, array $params): true|string
    {
        if ($value !== null && $value !== '' && !preg_match('/^1[3-9]\d{9}$/', (string)$value)) {
            return "{$field} 不是有效的手机号";
        }
        return true;
    }

    protected function ruleArray(string $field, mixed $value, array $params): true|string
    {
        if ($value !== null && !is_array($value)) {
            return "{$field} 必须是数组";
        }
        return true;
    }

    protected function ruleJson(string $field, mixed $value, array $params): true|string
    {
        if ($value !== null && $value !== '') {
            if (is_string($value)) {
                json_decode($value);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    return "{$field} 不是有效的 JSON";
                }
            } elseif (!is_array($value)) {
                return "{$field} 不是有效的 JSON";
            }
        }
        return true;
    }

    protected function ruleMin(string $field, mixed $value, array $params): true|string
    {
        $min = (int)($params[0] ?? 0);
        if ($value !== null && $value !== '') {
            if (is_string($value) && mb_strlen($value) < $min) {
                return "{$field} 长度不能少于 {$min}";
            }
            if (is_numeric($value) && (int)$value < $min) {
                return "{$field} 不能小于 {$min}";
            }
            if (is_array($value) && count($value) < $min) {
                return "{$field} 元素数量不能少于 {$min}";
            }
        }
        return true;
    }

    protected function ruleMax(string $field, mixed $value, array $params): true|string
    {
        $max = (int)($params[0] ?? PHP_INT_MAX);
        if ($value !== null && $value !== '') {
            if (is_string($value) && mb_strlen($value) > $max) {
                return "{$field} 长度不能超过 {$max}";
            }
            if (is_numeric($value) && (int)$value > $max) {
                return "{$field} 不能大于 {$max}";
            }
            if (is_array($value) && count($value) > $max) {
                return "{$field} 元素数量不能超过 {$max}";
            }
        }
        return true;
    }

    protected function ruleBetween(string $field, mixed $value, array $params): true|string
    {
        $min = (int)($params[0] ?? 0);
        $max = (int)($params[1] ?? PHP_INT_MAX);
        if ($value !== null && $value !== '') {
            $num = is_numeric($value) ? (int)$value : (is_string($value) ? mb_strlen($value) : 0);
            if ($num < $min || $num > $max) {
                return "{$field} 必须在 {$min} 到 {$max} 之间";
            }
        }
        return true;
    }

    protected function ruleIn(string $field, mixed $value, array $params): true|string
    {
        if ($value !== null && $value !== '') {
            if (!in_array((string)$value, $params, true)) {
                return "{$field} 必须是以下值之一: " . implode(', ', $params);
            }
        }
        return true;
    }

    protected function rulePattern(string $field, mixed $value, array $params): true|string
    {
        $pattern = $params[0] ?? '';
        if ($value !== null && $value !== '' && !empty($pattern)) {
            if (!preg_match($pattern, (string)$value)) {
                return "{$field} 格式不正确";
            }
        }
        return true;
    }
}
