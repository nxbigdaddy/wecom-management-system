<?php
/**
 * 异常类定义
 * 
 * Wxwork 系统所有异常均继承自 WxworkException
 */

namespace Wxwork\Core;

// ===================== 基础异常 =====================

/**
 * 企业微信系统基础异常
 */
class WxworkException extends \Exception
{
    /** @var array 额外上下文信息 */
    protected array $context = [];

    public function __construct(string $message = '', int $code = 0, ?\Throwable $previous = null, array $context = [])
    {
        parent::__construct($message, $code, $previous);
        $this->context = $context;
    }

    public function getContext(): array
    {
        return $this->context;
    }
}

// ===================== Token 异常 =====================

/**
 * access_token 相关异常
 */
class TokenException extends WxworkException
{
}

// ===================== API 调用异常 =====================

/**
 * 企业微信 API 调用异常
 */
class ApiException extends WxworkException
{
    /** @var array|null API 响应数据 */
    private ?array $responseData;

    public function __construct(string $message = '', int $code = 0, ?\Throwable $previous = null, ?array $responseData = null)
    {
        parent::__construct($message, $code, $previous);
        $this->responseData = $responseData;
    }

    /**
     * 获取企业微信 errcode
     */
    public function getErrcode(): int
    {
        return $this->code;
    }

    /**
     * 获取 API 响应数据
     */
    public function getResponseData(): ?array
    {
        return $this->responseData;
    }
}

// ===================== 加解密异常 =====================

/**
 * 消息加解密异常
 */
class CryptException extends WxworkException
{
}

// ===================== 参数校验异常 =====================

/**
 * 参数校验异常
 */
class ValidationException extends WxworkException
{
    /** @var array 错误详情 [field => message, ...] */
    private array $validationErrors;

    public function __construct(string $message = '', array $errors = [], ?\Throwable $previous = null)
    {
        parent::__construct($message, 422, $previous);
        $this->validationErrors = $errors;
    }

    /**
     * 获取校验错误详情
     */
    public function getErrors(): array
    {
        return $this->validationErrors;
    }

    /**
     * 获取第一个错误
     */
    public function getFirstError(): string
    {
        if (empty($this->validationErrors)) {
            return $this->message;
        }
        return reset($this->validationErrors);
    }
}
