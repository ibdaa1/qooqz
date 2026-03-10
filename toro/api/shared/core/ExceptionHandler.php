<?php
declare(strict_types=1);
// htdocs/api/shared/core/ExceptionHandler.php

namespace Shared\Core;

use Shared\Domain\Exceptions\DomainException;

final class ExceptionHandler
{
    private static bool $registered = false;

    private function __construct() {}
    private function __clone() {}

    /* =========================
     * Bootstrap
     * ========================= */
    public static function register(): void
    {
        if (self::$registered) {
            return;
        }

        set_exception_handler([self::class, 'handleThrowable']);

        set_error_handler(function (
            int $severity,
            string $message,
            string $file,
            int $line
        ): bool {
            throw new \ErrorException($message, 0, $severity, $file, $line);
        });

        register_shutdown_function([self::class, 'handleShutdown']);

        self::$registered = true;
    }

    /* =========================
     * Throwable handler
     * ========================= */
    public static function handleThrowable(\Throwable $e): void
    {
        self::report($e);
        self::render($e);
        exit;
    }

    /* =========================
     * Fatal errors
     * ========================= */
    public static function handleShutdown(): void
    {
        $error = error_get_last();

        if ($error && in_array($error['type'], [
            E_ERROR,
            E_PARSE,
            E_CORE_ERROR,
            E_COMPILE_ERROR,
        ], true)) {
            $e = new \ErrorException(
                $error['message'],
                0,
                $error['type'],
                $error['file'],
                $error['line']
            );

            self::report($e);
            self::render($e);
            exit;
        }
    }

    /* =========================
     * Report
     * ========================= */
    private static function report(\Throwable $e): void
    {
        if (class_exists(Logger::class)) {
            Logger::error(
                sprintf(
                    '%s: %s in %s:%d',
                    get_class($e),
                    $e->getMessage(),
                    $e->getFile(),
                    $e->getLine()
                )
            );
        } else {
            error_log(sprintf(
                '[ExceptionHandler] %s: %s in %s:%d',
                get_class($e),
                $e->getMessage(),
                $e->getFile(),
                $e->getLine()
            ));
        }
    }

    /* =========================
     * Render API response
     * ========================= */
    private static function render(\Throwable $e): void
    {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }

        if ($e instanceof DomainException) {
            http_response_code($e->getStatusCode());
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage(),
                'errors'  => $e->getContext(),
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        $debug = (bool)(getenv('APP_DEBUG') === 'true');
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => $debug ? $e->getMessage() : 'Internal Server Error',
        ], JSON_UNESCAPED_UNICODE);
    }
}
