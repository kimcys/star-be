<?php
declare(strict_types=1);

/**
 * Tiny structured logger. Writes one JSON object per line to the PHP
 * error log (still just error_log() under the hood - no log
 * aggregator to wire up for this app) instead of ad-hoc free-text
 * messages, so entries are easy to grep, filter by level, or feed
 * into any tool that expects JSON lines later.
 */
final class Logger
{
    /**
     * @param array<string, scalar|null> $context
     */
    public static function error(string $message, array $context = []): void
    {
        self::write('error', $message, $context);
    }

    /**
     * @param array<string, scalar|null> $context
     */
    private static function write(string $level, string $message, array $context): void
    {
        error_log((string) json_encode([
            'timestamp' => date('c'),
            'level' => $level,
            'message' => $message,
            'context' => $context,
        ], JSON_UNESCAPED_SLASHES));
    }
}
