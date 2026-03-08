<?php
/**
 * TORO — Kernel.php
 * /public_html/toro/api/shared/core/Kernel.php
 *
 * Namespace: Shared\Core
 *
 * Responsibilities:
 *   1. Parse method + URI
 *   2. Match route from registered route files
 *   3. Build & run middleware pipeline
 *   4. Dispatch to controller action
 *   5. Send JSON response
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
    private array $globalMiddleware = [
        // Global middleware intentionally kept minimal.
        // Per-route throttling is applied via ThrottleMiddleware::class . ':limit,window'
    ];

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

    // ── Load all route files ──────────────────────────────────────────────────

    private function loadRoutes(): void
    {
        $router    = $this; // passed into every route file
        $routesDir = BASE_PATH . '/v1/routes';

        // Files that must be loaded LAST (they use controllers defined by module files)
        // and files that must never be auto-loaded (debug utilities)
        $loadLast = ['admin.php', 'public.php'];
        $skip     = ['v1.php'];

        // Auto-discover all module route files (alphabetical order)
        $discovered = glob($routesDir . '/*.php') ?: [];
        foreach ($discovered as $file) {
            $basename = basename($file);
            if (in_array($basename, $loadLast, true) || in_array($basename, $skip, true)) {
                continue;
            }
            require_once $file;
        }

        // Admin routes (/v1/admin/*) — always loaded last; auth enforced per-route
        if (file_exists($routesDir . '/admin.php')) {
            require_once $routesDir . '/admin.php';
        }

        // Public routes (/v1/public/*) — always loaded last; no auth required
        if (file_exists($routesDir . '/public.php')) {
            require_once $routesDir . '/public.php';
        }
    }

    // ── Main Handle ───────────────────────────────────────────────────────────

    public function handle(): void
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $uri    = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

        // Strip base path prefix
        $base = '/toro/api';
        if (str_starts_with($uri, $base)) {
            $uri = substr($uri, strlen($base));
        }
        $uri = '/' . trim($uri, '/') ?: '/';

        // Handle OPTIONS preflight
        if ($method === 'OPTIONS') {
            http_response_code(204);
            exit;
        }

        try {
            [$routeDef, $params] = $this->matchRoute($method, $uri);

            // Build middleware pipeline
            $allMiddleware = array_merge($this->globalMiddleware, $routeDef['middleware'] ?? []);
            $pipeline      = new MiddlewarePipeline($allMiddleware);

            // Final handler
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
            Logger::error('Unhandled exception', [
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
                'trace'   => $e->getTraceAsString(),
            ]);

            $debug = ($_ENV['APP_DEBUG'] ?? 'false') === 'true';
            Response::json([
                'success' => false,
                'message' => $debug ? $e->getMessage() : 'Internal Server Error',
            ], 500);
        }
    }

    // ── Route Matching (supports {param} placeholders) ────────────────────────

    /**
     * @return array{0: array, 1: array<string,string>}
     * @throws NotFoundException
     */
    private function matchRoute(string $method, string $uri): array
    {
        $routesForMethod = $this->routes[$method] ?? [];

        // 1. Exact match
        if (isset($routesForMethod[$uri])) {
            return [$routesForMethod[$uri], []];
        }

        // 2. Pattern match with {param}
        foreach ($routesForMethod as $pattern => $routeDef) {
            $regex  = preg_replace('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', '(?P<$1>[^/]+)', $pattern);
            $regex  = '#^' . $regex . '$#';
            if (preg_match($regex, $uri, $matches)) {
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                return [$routeDef, $params];
            }
        }

        throw new NotFoundException("Route not found: {$method} {$uri}");
    }

    // ── Dispatch ──────────────────────────────────────────────────────────────

    private function dispatch(callable|string $handler, array $params): void
    {
        if (is_callable($handler)) {
            call_user_func($handler, $params);
            return;
        }

        // "ControllerClass@method"
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
}