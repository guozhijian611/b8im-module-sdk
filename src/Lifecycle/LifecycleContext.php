<?php

declare(strict_types=1);

namespace B8im\ModuleSdk\Lifecycle;

use B8im\ModuleSdk\Manifest\Manifest;
use InvalidArgumentException;

final class LifecycleContext
{
    /**
     * @param array<string, mixed> $options
     */
    public function __construct(
        private readonly LifecycleOperation $operation,
        private readonly Manifest $manifest,
        private readonly ?int $organization = null,
        private readonly ?string $fromVersion = null,
        private readonly bool $preserveData = true,
        private readonly array $options = [],
    ) {
        if ($organization !== null && $organization <= 0) {
            throw new InvalidArgumentException('organization must be a positive integer.');
        }

        if (in_array($operation, [
            LifecycleOperation::INSTALL,
            LifecycleOperation::UPGRADE,
            LifecycleOperation::UNINSTALL,
        ], true) && $organization !== null) {
            throw new InvalidArgumentException(sprintf(
                '%s is a system lifecycle operation and cannot carry organization.',
                $operation->value,
            ));
        }

        if ($operation === LifecycleOperation::UPGRADE && $fromVersion === null) {
            throw new InvalidArgumentException('Upgrade context requires fromVersion.');
        }

        if ($operation !== LifecycleOperation::UPGRADE && $fromVersion !== null) {
            throw new InvalidArgumentException('fromVersion is only valid for upgrade.');
        }

        if (!$preserveData && $operation !== LifecycleOperation::UNINSTALL) {
            throw new InvalidArgumentException('Data cleanup is only valid for uninstall.');
        }
    }

    public function operation(): LifecycleOperation
    {
        return $this->operation;
    }

    public function manifest(): Manifest
    {
        return $this->manifest;
    }

    public function organization(): ?int
    {
        return $this->organization;
    }

    public function fromVersion(): ?string
    {
        return $this->fromVersion;
    }

    public function targetVersion(): string
    {
        return $this->manifest->version();
    }

    public function preserveData(): bool
    {
        return $this->preserveData;
    }

    /**
     * @return array<string, mixed>
     */
    public function options(): array
    {
        return $this->options;
    }

    public function isTenantScoped(): bool
    {
        return $this->organization !== null;
    }

    public function assertOperation(LifecycleOperation $expected): void
    {
        if ($this->operation !== $expected) {
            throw new InvalidArgumentException(sprintf(
                'Lifecycle context operation is %s; expected %s.',
                $this->operation->value,
                $expected->value,
            ));
        }
    }
}
