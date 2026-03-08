<?php
declare(strict_types=1);

namespace Shared\Domain\Exceptions;

final class ValidationException extends DomainException
{
    protected string $errorCode = 'validation_failed';
    protected int    $statusCode = 422;

    /**
     * @param string $message Human-readable summary
     * @param array  $errors  Field-level error map
     */
    public function __construct(string $message, array $errors = [])
    {
        parent::__construct($message);
        $this->context = $errors;
    }

    public function getErrors(): array
    {
        return $this->context;
    }
}
