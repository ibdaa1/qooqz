<?php
/**
 * TORO — Kernel.php (Production Ready)
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
    /**
     * Registered routes.
     *
     * @var array<string, array<string, array{handler: callable|string, middleware: string[]}>>
     */
    private array $routes = [];

    /**
     * Global middleware applied to every route.
     *
     * @var string[]
     */
    private array $globalMiddleware = [];

    /**
     * Constructor – automatically discovers and loads all route files from /v1/routes/.
     */
    public function __construct()
    {
        $this->loadRoutes();
    }

    // -------------------------------------------------------------------------
    // Route Registration
    // -------------------------------------------------------------------------

    /**
     * Add a new route.
     *
     * @param string               $method     HTTP method (GET, POST, etc.)
     * @param string               $path       URI pattern, supports {param} placeholders
     * @param string|callable      $handler    Controller action "Class@method" or Closure
     * @param array<string>        $middleware List of middleware class names (with optional parameters)
     */
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

    // -------------------------------------------------------------------------
    // Auto‑load Route Files
    // -------------------------------------------------------------------------

    /**
     * Recursively include every PHP file inside BASE_PATH . '/v1/routes/'.
     * The variable $router (or $this) is available inside each file.
     */
    private function loadRoutes(): void
    {
        $routeDirectory = BASE_PATH . '/v1/routes/';

        if (!is_dir($routeDirectory)) {
            // No routes directory – nothing to load
            return;
        }

        // Make $router available to route files (many legacy files expect it)
        $router = $this;

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($routeDirectory, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            if ($file->isFile() && $file->getExtension() === 'php') {
                // Include the route file – both $this and $router are in scope
                require_once $file->getPathname();
            }
        }
    }

    // -------------------------------------------------------------------------
    // Request Handling
    // -------------------------------------------------------------------------

    /**
     * Handle the current HTTP request and send a JSON response.
     */
    public function handle(): void
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $uri    = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

        // Strip base path if the app runs in a subdirectory
        $base = '/toro/api';
        if (str_starts_with($uri, $base)) {
            $uri = substr($uri, strlen($base));
        }
        $uri = '/' . ltrim($uri, '/') ?: '/';

        // Preflight OPTIONS request
        if ($method === 'OPTIONS') {
            http_response_code(204);
            exit;
        }

        try {
            [$routeDef, $params] = $this->matchRoute($method, $uri);

            // Merge global and route‑specific middleware
            $allMiddleware = array_merge($this->globalMiddleware, $routeDef['middleware'] ?? []);

            // Build and run the middleware pipeline
            $pipeline = new MiddlewarePipeline($allMiddleware);
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
                'trace'   => $e->getTraceAsString(),
            ]);

            $debug = ($_ENV['APP_DEBUG'] ?? 'false') === 'true';
            Response::json([
                'success' => false,
                'message' => $debug ? $e->getMessage() : 'Internal Server Error',
            ], 500);
        }
    }

    // -------------------------------------------------------------------------
    // Route Matching (with {param} support)
    // -------------------------------------------------------------------------

    /**
     * Find a route matching the method and URI.
     *
     * @param string $method
     * @param string $uri
     *
     * @return array{0: array{handler: string|callable, middleware: string[]}, 1: array<string,string>}
     *
     * @throws NotFoundException
     */
    private function matchRoute(string $method, string $uri): array
    {
        $routesForMethod = $this->routes[$method] ?? [];

        // Exact match
        if (isset($routesForMethod[$uri])) {
            return [$routesForMethod[$uri], []];
        }

        // Pattern match with placeholders
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

    // -------------------------------------------------------------------------
    // Dispatch to Controller
    // -------------------------------------------------------------------------

    /**
     * Execute the route handler.
     *
     * @param string|callable      $handler
     * @param array<string,string> $params
     *
     * @throws \RuntimeException
     */
    private function dispatch(string|callable $handler, array $params): void
    {
        if (is_callable($handler)) {
            $handler($params);
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

    // -------------------------------------------------------------------------
    // Error Logging
    // -------------------------------------------------------------------------

    /**
     * Log an error – override with a PSR‑3 logger in production.
     *
     * @param string $message
     * @param array  $context
     */
    private function logError(string $message, array $context = []): void
    {
        // Use your preferred logger (Monolog, etc.) here.
        // For now, fall back to error_log.
        error_log(sprintf(
            '[Kernel Error] %s %s',
            $message,
            json_encode($context)
        ));
    }
}
