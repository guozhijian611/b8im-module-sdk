<?php

declare(strict_types=1);

namespace B8im\ModuleSdk\Exception;

use InvalidArgumentException;

final class ManifestValidationException extends InvalidArgumentException
{
    /**
     * @param list<string> $errors
     */
    public function __construct(
        string $message,
        private readonly array $errors = [],
    ) {
        parent::__construct($message);
    }

    /**
     * @return list<string>
     */
    public function errors(): array
    {
        return $this->errors;
    }
}
