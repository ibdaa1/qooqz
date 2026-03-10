<?php
declare(strict_types=1);

namespace Shared\Core;

/**
 * Base class for all middleware in the V1 pipeline.
 * Each middleware receives the next callable and must call it to continue.
 */
abstract class MiddlewareBase
{
    /**
     * Process the request and call $next() to pass control to the next layer.
     */
    abstract public function handle(callable $next): void;
}
