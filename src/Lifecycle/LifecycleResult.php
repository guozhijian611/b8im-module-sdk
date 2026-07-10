<?php

declare(strict_types=1);

namespace B8im\ModuleSdk\Lifecycle;

final class LifecycleResult
{
    /**
     * @param array<string, mixed> $metadata
     */
    private function __construct(
        private readonly bool $successful,
        private readonly string $message,
        private readonly array $metadata,
    ) {
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public static function success(string $message = '', array $metadata = []): self
    {
        return new self(true, $message, $metadata);
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public static function failure(string $message, array $metadata = []): self
    {
        return new self(false, $message, $metadata);
    }

    public function isSuccessful(): bool
    {
        return $this->successful;
    }

    public function message(): string
    {
        return $this->message;
    }

    /**
     * @return array<string, mixed>
     */
    public function metadata(): array
    {
        return $this->metadata;
    }
}
