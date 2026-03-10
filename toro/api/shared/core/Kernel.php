<?php
/**
 * TORO — Kernel.php (Production Ready with auto-loaded routes)
 * /public_html/toro/api/shared/core/Kernel.php
 *
 * @package Shared\Core
 */

declare(strict_types=1);

namespace Shared\Core;

use Shared\Helpers\Response;
use Shared\Domain\Exceptions\NotFoundException;
use Shared\Domain\Exceptions\ValidationException;
use Shared\Domain\Exceptions\AuthorizationException;

class Kernel
{
    /** @var array<string, array<string, array{handler: callable|string, middleware: string[]}>> */
    private array $routes = [];

    /** @var string[] Default middleware for all routes */
    private array $globalMiddleware = [];

    public function __construct()
    {
        $this->loadRoutes();
    }

    // ── Route Registration ────────────────────────────────────────────────────

    public function addRoute(
        string $method,
        string $path,
        string|callable $handler,
        array $middleware = []
    ): void {
        $method = strtoupper($method);
        $this->routes[$method][$path] = [
            'handler'    => $handler,
            'middleware' => $middleware,
        ];
    }

    // ── Auto‑load all route files ─────────────────────────────────────────────

    private function loadRoutes(): void
    {
        $routeDirectory = BASE_PATH . '/v1/routes/';

        if (!is_dir($routeDirectory)) {
            return; // أو يمكنك تسجيل خطأ
        }

        // نمرر المتغير $router إلى ملفات المسارات (اختياري)
        $router = $this;   // <-- هذا السطر ضروري لو ملفاتك تستخدم $router

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($routeDirectory, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            // يتم تضمين الملف – المتغيرات $this و $router متاحة داخله
            require_once $file->getPathname();
        }
    }

    // ── Main Request Handler ──────────────────────────────────────────────────

    public function handle(): void
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $uri    = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

        $base = '/toro/api';
        if (str_starts_with($uri, $base)) {
            $uri = substr($uri, strlen($base));
        }
        $uri = '/' . trim($uri, '/') ?: '/';

        if ($method === 'OPTIONS') {
            http_response_code(204);
            exit;
        }

        try {
            [$routeDef, $params] = $this->matchRoute($method, $uri);

            $allMiddleware = array_merge($this->globalMiddleware, $routeDef['middleware'] ?? []);
            $pipeline      = new MiddlewarePipeline($allMiddleware);

            $pipeline->pipe(function () use ($routeDef, $params) {
                $this->dispatch($routeDef['handler'], $params);
            });

            $pipeline->run();

        } catch (NotFoundException $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 404);

        } catch (AuthorizationException $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 403);

        } catch (ValidationException $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage(),
                'errors'  => $e->getErrors(),
            ], 422);

        } catch (\Throwable $e) {
            $this->logError('Unhandled exception', [
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
            ]);

            $debug = ($_ENV['APP_DEBUG'] ?? 'false') === 'true';
            Response::json([
                'success' => false,
                'message' => $debug ? $e->getMessage() : 'Internal Server Error',
            ], 500);
        }
    }

    // ── Route Matching ────────────────────────────────────────────────────────

    private function matchRoute(string $method, string $uri): array
    {
        $routesForMethod = $this->routes[$method] ?? [];

        if (isset($routesForMethod[$uri])) {
            return [$routesForMethod[$uri], []];
        }

        foreach ($routesForMethod as $pattern => $routeDef) {
            $regex = preg_replace('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', '(?P<$1>[^/]+)', $pattern);
            $regex = '#^' . $regex . '$#';
            if (preg_match($regex, $uri, $matches)) {
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                return [$routeDef, $params];
            }
        }

        throw new NotFoundException("Route not found: {$method} {$uri}");
    }

    // ── Dispatch ──────────────────────────────────────────────────────────────

    private function dispatch(string|callable $handler, array $params): void
    {
        if (is_callable($handler)) {
            call_user_func($handler, $params);
            return;
        }

        if (is_string($handler) && str_contains($handler, '@')) {
            [$class, $method] = explode('@', $handler, 2);
            if (!class_exists($class)) {
                throw new \RuntimeException("Controller class not found: {$class}");
            }
            $controller = new $class();
            if (!method_exists($controller, $method)) {
                throw new \RuntimeException("Method {$method} not found in {$class}");
            }
            $controller->{$method}($params);
            return;
        }

        throw new \RuntimeException('Invalid route handler definition');
    }

    private function logError(string $message, array $context = []): void
    {
        error_log(sprintf(
            '[Kernel Error] %s %s',
            $message,
            json_encode($context)
        ));
    }
}
