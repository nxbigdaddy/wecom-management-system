<?php
/**
 * 日志系统
 * 
 * 支持按日期分文件、多级别、彩色控制台输出
 */

namespace Wxwork\Core;

class Logger
{
    const LEVEL_DEBUG = 0;
    const LEVEL_INFO  = 1;
    const LEVEL_WARN  = 2;
    const LEVEL_ERROR = 3;

    /** @var string 日志目录 */
    private string $logDir;

    /** @var int 最低记录级别 */
    private int $minLevel;

    /** @var int 日志保留天数 */
    private int $maxDays;

    /** 级别名称映射 */
    private static array $levelNames = [
        self::LEVEL_DEBUG => 'DEBUG',
        self::LEVEL_INFO  => 'INFO',
        self::LEVEL_WARN  => 'WARN',
        self::LEVEL_ERROR => 'ERROR',
    ];

    /** 级别颜色（CLI） */
    private static array $levelColors = [
        self::LEVEL_DEBUG => "\033[37m", // 灰
        self::LEVEL_INFO  => "\033[32m", // 绿
        self::LEVEL_WARN  => "\033[33m", // 黄
        self::LEVEL_ERROR => "\033[31m", // 红
    ];

    /**
     * @param string $logDir  日志目录
     * @param string $minLevel 最低级别: debug|info|warn|error
     * @param int    $maxDays 保留天数
     */
    public function __construct(string $logDir, string $minLevel = 'info', int $maxDays = 30)
    {
        $this->logDir = rtrim($logDir, '/\\');
        $this->maxDays = $maxDays;

        // 创建日志目录
        if (!is_dir($this->logDir)) {
            @mkdir($this->logDir, 0755, true);
        }

        $this->minLevel = match (strtolower($minLevel)) {
            'debug' => self::LEVEL_DEBUG,
            'info'  => self::LEVEL_INFO,
            'warn'  => self::LEVEL_WARN,
            'error' => self::LEVEL_ERROR,
            default => self::LEVEL_INFO,
        };
    }

    /**
     * 设置日志目录
     */
    public function setLogDir(string $dir): self
    {
        $this->logDir = rtrim($dir, '/\\');
        if (!is_dir($this->logDir)) {
            @mkdir($this->logDir, 0755, true);
        }
        return $this;
    }

    /**
     * 记录日志
     * @param int    $level   级别
     * @param string $message 消息
     * @param array  $context 上下文数据
     */
    public function log(int $level, string $message, array $context = []): void
    {
        if ($level < $this->minLevel) {
            return;
        }

        $levelName = self::$levelNames[$level] ?? 'UNKNOWN';
        $timestamp = date('Y-m-d H:i:s');
        $microtime = sprintf('%06d', (int)(microtime(true) * 1000000) % 1000000);

        // 格式化上下文
        $contextStr = !empty($context) ? ' ' . json_encode($context, JSON_UNESCAPED_UNICODE) : '';

        // 构建日志行
        $logLine = sprintf(
            "[%s.%s] [%s] %s%s" . PHP_EOL,
            $timestamp,
            $microtime,
            $levelName,
            $message,
            $contextStr
        );

        // CLI 颜色输出
        if (PHP_SAPI === 'cli') {
            $color = self::$levelColors[$level] ?? "\033[0m";
            echo $color . $logLine . "\033[0m";
        }

        // 写入文件
        $this->write($logLine);

        // 额外写错误日志
        if ($level >= self::LEVEL_ERROR) {
            $this->writeToFile('error', $logLine);
        }
    }

    /**
     * 写入通用日志文件（按日期）
     */
    private function write(string $line): void
    {
        $filename = $this->logDir . '/wxwork-' . date('Y-m-d') . '.log';
        $this->writeToFileRaw($filename, $line);
    }

    /**
     * 写入指定类别文件
     */
    private function writeToFile(string $type, string $line): void
    {
        $filename = $this->logDir . '/' . $type . '-' . date('Y-m-d') . '.log';
        $this->writeToFileRaw($filename, $line);
    }

    /**
     * 原始文件写入
     */
    private function writeToFileRaw(string $filename, string $line): void
    {
        @file_put_contents($filename, $line, FILE_APPEND | LOCK_EX);

        // 每天有 1% 概率触发清理过期日志
        if (mt_rand(1, 100) === 1) {
            $this->cleanOldLogs();
        }
    }

    /**
     * 清理过期日志
     */
    private function cleanOldLogs(): void
    {
        $cutoff = time() - ($this->maxDays * 86400);
        $files = glob($this->logDir . '/*.log');
        if ($files === false) return;
        foreach ($files as $file) {
            if (filemtime($file) < $cutoff) {
                @unlink($file);
            }
        }
    }

    // ===================== 便捷方法 =====================

    public function debug(string $message, array $context = []): void
    {
        $this->log(self::LEVEL_DEBUG, $message, $context);
    }

    public function info(string $message, array $context = []): void
    {
        $this->log(self::LEVEL_INFO, $message, $context);
    }

    public function warn(string $message, array $context = []): void
    {
        $this->log(self::LEVEL_WARN, $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->log(self::LEVEL_ERROR, $message, $context);
    }
}
