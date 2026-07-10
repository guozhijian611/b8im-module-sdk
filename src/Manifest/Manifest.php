<?php

declare(strict_types=1);

namespace B8im\ModuleSdk\Manifest;

/**
 * Immutable, already validated module manifest.
 */
final class Manifest
{
    /**
     * @param array<string, mixed> $data
     *
     * @internal Construct manifests through ManifestLoader.
     */
    public function __construct(private readonly array $data)
    {
    }

    public function moduleKey(): string
    {
        return $this->data['module_key'];
    }

    public function name(): string
    {
        return $this->data['name'];
    }

    public function version(): string
    {
        return $this->data['version'];
    }

    public function description(): string
    {
        return $this->data['description'];
    }

    public function category(): string
    {
        return $this->data['category'];
    }

    public function moduleType(): string
    {
        return $this->data['module_type'];
    }

    public function isBuiltin(): bool
    {
        return $this->data['is_builtin'];
    }

    public function licenseRequired(): bool
    {
        return $this->data['license_required'];
    }

    public function minSystemVersion(): string
    {
        return $this->data['min_system_version'];
    }

    /**
     * @return list<array{module_key: string, constraint: string}>
     */
    public function dependsOn(): array
    {
        return $this->data['depends_on'];
    }

    /**
     * @return list<array{module_key: string, constraint: string}>
     */
    public function conflictsWith(): array
    {
        return $this->data['conflicts_with'];
    }

    /**
     * @return list<string>
     */
    public function platforms(): array
    {
        return $this->data['platforms'];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function permissions(): array
    {
        return $this->data['permissions'];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function menus(): array
    {
        return $this->data['menus'];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function routes(): array
    {
        return $this->data['routes'];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function config(): array
    {
        return $this->data['config'];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function migrations(): array
    {
        return $this->data['migrations'];
    }

    /**
     * @return array<string, list<string>>
     */
    public function capabilities(): array
    {
        return $this->data['capabilities'];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function hooks(): array
    {
        return $this->data['hooks'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->data;
    }
}
