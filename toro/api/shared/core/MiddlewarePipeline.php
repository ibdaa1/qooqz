<?php
declare(strict_types=1);

namespace Shared\Core;

/**
 * Middleware pipeline used by the Kernel.
 *
 * Usage (as expected by Kernel.php):
 *   $pipeline = new MiddlewarePipeline($middlewareSpecs);
 *   $pipeline->pipe(fn() => $this->dispatch(...));
 *   $pipeline->run();
 *
 * Middleware specs can be:
 *   'ClassName'           → instantiated with no args
 *   'ClassName:arg1,arg2' → instantiated with ('arg1,arg2') as first constructor arg
 */
final class MiddlewarePipeline
{
    /** @var string[] */
    private array $specs;

    /** @var callable|null */
    private $finalHandler = null;

    public function __construct(array $specs = [])
    {
        $this->specs = $specs;
    }

    /**
     * Register the final handler (innermost callable in the chain).
     */
    public function pipe(callable $handler): void
    {
        $this->finalHandler = $handler;
    }

    /**
     * Execute the pipeline: run all middleware around the final handler.
     */
    public function run(): void
    {
        $handler = $this->finalHandler ?? static function (): void {};

        // Build the chain inside-out (last spec wraps the final handler first)
        $chain = $handler;
        foreach (array_reverse($this->specs) as $spec) {
            [$class, $args] = $this->parseSpec($spec);
            if (!is_a($class, MiddlewareBase::class, true)) {
                throw new \InvalidArgumentException(
                    "Middleware class '{$class}' must extend Shared\\Core\\MiddlewareBase"
                );
            }
            $next  = $chain;
            $chain = static function () use ($class, $args, $next): void {
                /** @var MiddlewareBase $mw */
                $mw = new $class(...$args);
                $mw->handle($next);
            };
        }

        $chain();
    }

    /**
     * Parse 'ClassName:constructorArg' into [$class, [$arg]].
     * Returns [$class, []] if no colon present.
     *
     * @return array{0: string, 1: array}
     */
    private function parseSpec(string $spec): array
    {
        if (!str_contains($spec, ':')) {
            return [$spec, []];
        }
        [$class, $arg] = explode(':', $spec, 2);
        return [$class, [$arg]];
    }
}
