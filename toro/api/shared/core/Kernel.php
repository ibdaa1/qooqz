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

    /** @var string[]|null Cached version prefixes (e.g. ['/v2', '/v1']), populated lazily */
    private ?array $versionPrefixCache = null;

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

        // 1. Try the URI as given (exact then pattern).
        $result = $this->tryMatchAgainst($routesForMethod, $uri);
        if ($result !== null) {
            return $result;
        }

        // 2. If the URI carries no version prefix, auto-try every registered
        //    version prefix (sorted descending so the latest wins, e.g. v2 > v1).
        if (!preg_match('#^/v\d+(/|$)#', $uri)) {
            foreach ($this->detectVersionPrefixes() as $prefix) {
                $result = $this->tryMatchAgainst($routesForMethod, $prefix . $uri);
                if ($result !== null) {
                    return $result;
                }
            }
        }

        throw new NotFoundException("Route not found: {$method} {$uri}");
    }

    /**
     * Try to match $uri against the given route map (exact then pattern).
     * Returns [routeDef, params] on success, null on no match.
     *
     * @param array<string, array{handler: callable|string, middleware: string[]}> $routesForMethod
     */
    private function tryMatchAgainst(array $routesForMethod, string $uri): ?array
    {
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

        return null;
    }

    /**
     * Detect all version prefixes present in the registered routes
     * (e.g. ['/v2', '/v1']), sorted descending so the latest is tried first.
     * Result is cached for the lifetime of the request.
     *
     * @return string[]
     */
    private function detectVersionPrefixes(): array
    {
        if ($this->versionPrefixCache !== null) {
            return $this->versionPrefixCache;
        }

        $seen = [];
        foreach ($this->routes as $methodRoutes) {
            foreach (array_keys($methodRoutes) as $path) {
                if (preg_match('#^(/v(\d+))(/|$)#', $path, $m)) {
                    $seen[$m[1]] = (int) $m[2];
                }
            }
        }

        // Sort by numeric version number descending (/v2 before /v1, /v10 before /v2)
        arsort($seen);
        $this->versionPrefixCache = array_keys($seen);
        return $this->versionPrefixCache;
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
