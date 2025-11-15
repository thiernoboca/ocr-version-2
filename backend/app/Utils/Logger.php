<?php

namespace App\Utils;

class Logger
{
    private static string $logPath;

    public static function init(): void
    {
        self::$logPath = $_ENV['LOG_PATH'] ?? __DIR__ . '/../../storage/logs/app.log';
        $logDir = dirname(self::$logPath);

        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
    }

    public static function log(string $level, string $message, array $context = []): void
    {
        self::init();

        $timestamp = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? json_encode($context, JSON_UNESCAPED_UNICODE) : '';
        $logEntry = "[{$timestamp}] [{$level}] {$message} {$contextStr}\n";

        file_put_contents(self::$logPath, $logEntry, FILE_APPEND);
    }

    public static function info(string $message, array $context = []): void
    {
        self::log('INFO', $message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        self::log('ERROR', $message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        self::log('WARNING', $message, $context);
    }

    public static function debug(string $message, array $context = []): void
    {
        if ($_ENV['LOG_LEVEL'] === 'debug') {
            self::log('DEBUG', $message, $context);
        }
    }

    /**
     * Journaliser un audit (accès, modifications, suppressions)
     */
    public static function audit(string $action, string $resource, ?int $userId = null, array $details = []): void
    {
        if ($_ENV['AUDIT_LOG_ENABLED'] !== 'true') {
            return;
        }

        $auditPath = dirname(self::$logPath) . '/audit.log';
        $timestamp = date('Y-m-d H:i:s');
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

        $auditEntry = [
            'timestamp' => $timestamp,
            'action' => $action,
            'resource' => $resource,
            'user_id' => $userId,
            'ip' => $ip,
            'user_agent' => $userAgent,
            'details' => $details
        ];

        file_put_contents(
            $auditPath,
            json_encode($auditEntry, JSON_UNESCAPED_UNICODE) . "\n",
            FILE_APPEND
        );
    }
}
