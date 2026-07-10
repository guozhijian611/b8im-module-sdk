<?php

declare(strict_types=1);

namespace B8im\ModuleSdk\Manifest;

use Composer\Semver\VersionParser;
use UnexpectedValueException;

final class ManifestSemanticValidator
{
    private VersionParser $versionParser;

    public function __construct(?VersionParser $versionParser = null)
    {
        $this->versionParser = $versionParser ?? new VersionParser();
    }

    /**
     * @param array<string, mixed> $manifest
     *
     * @return list<string>
     */
    public function validate(array $manifest): array
    {
        $errors = [];

        $this->validateVersion($manifest['version'], 'version', $errors);
        $this->validateVersion($manifest['min_system_version'], 'min_system_version', $errors);

        $dependencies = $this->validateRelations(
            $manifest['depends_on'],
            'depends_on',
            $manifest['module_key'],
            $errors,
        );
        $conflicts = $this->validateRelations(
            $manifest['conflicts_with'],
            'conflicts_with',
            $manifest['module_key'],
            $errors,
        );

        foreach (array_intersect(array_keys($dependencies), array_keys($conflicts)) as $moduleKey) {
            $errors[] = sprintf(
                'Module "%s" cannot be declared in both depends_on and conflicts_with.',
                $moduleKey,
            );
        }

        $platforms = array_fill_keys($manifest['platforms'], true);
        $permissionSlugs = $this->uniqueValues($manifest['permissions'], 'slug', 'permissions', $errors);
        $this->uniqueValues($manifest['menus'], 'id', 'menus', $errors);
        $this->uniqueValues($manifest['routes'], 'id', 'routes', $errors);
        $this->uniqueValues($manifest['config'], 'key', 'config', $errors);
        $this->uniqueValues($manifest['migrations'], 'id', 'migrations', $errors);

        foreach ($manifest['menus'] as $index => $menu) {
            $this->validateDeclaredPlatform($menu['platform'], $platforms, "menus[$index]", $errors);
            $this->validatePermissionReference($menu['permission'] ?? null, $permissionSlugs, "menus[$index]", $errors);
        }

        foreach ($manifest['routes'] as $index => $route) {
            $this->validateDeclaredPlatform($route['platform'], $platforms, "routes[$index]", $errors);
            $this->validatePermissionReference($route['permission'] ?? null, $permissionSlugs, "routes[$index]", $errors);

            if (isset($route['capability'])) {
                $platformCapabilities = $manifest['capabilities'][$route['platform']] ?? [];
                if (!in_array($route['capability'], $platformCapabilities, true)) {
                    $errors[] = sprintf(
                        'routes[%d] references undeclared %s capability "%s".',
                        $index,
                        $route['platform'],
                        $route['capability'],
                    );
                }
            }
        }

        foreach ($manifest['migrations'] as $index => $migration) {
            $this->validateDeclaredPlatform($migration['platform'], $platforms, "migrations[$index]", $errors);
            $this->validateVersion($migration['version'], "migrations[$index].version", $errors);
        }

        foreach ($manifest['capabilities'] as $platform => $_capabilities) {
            $this->validateDeclaredPlatform($platform, $platforms, "capabilities.$platform", $errors);
        }

        foreach (['install', 'upgrade', 'uninstall'] as $systemHook) {
            if ($manifest['hooks'][$systemHook]['scope'] !== 'system') {
                $errors[] = sprintf('hooks.%s.scope must be "system".', $systemHook);
            }
        }

        foreach (['enable', 'disable'] as $enablementHook) {
            if (!in_array($manifest['hooks'][$enablementHook]['scope'], ['system', 'tenant', 'both'], true)) {
                $errors[] = sprintf('hooks.%s.scope is invalid.', $enablementHook);
            }
        }

        return $errors;
    }

    /**
     * @param list<array{module_key: string, constraint: string}> $relations
     * @param list<string> $errors
     *
     * @return array<string, string>
     */
    private function validateRelations(
        array $relations,
        string $field,
        string $ownModuleKey,
        array &$errors,
    ): array {
        $indexed = [];

        foreach ($relations as $index => $relation) {
            $moduleKey = $relation['module_key'];
            $constraint = $relation['constraint'];

            if ($moduleKey === $ownModuleKey) {
                $errors[] = sprintf('%s[%d] cannot reference the module itself.', $field, $index);
            }

            if (array_key_exists($moduleKey, $indexed)) {
                $errors[] = sprintf('%s contains duplicate module_key "%s".', $field, $moduleKey);
            }

            try {
                $this->versionParser->parseConstraints($constraint);
            } catch (UnexpectedValueException) {
                $errors[] = sprintf('%s[%d].constraint "%s" is invalid.', $field, $index, $constraint);
            }

            $indexed[$moduleKey] = $constraint;
        }

        return $indexed;
    }

    /**
     * @param list<array<string, mixed>> $items
     * @param list<string> $errors
     *
     * @return array<string, true>
     */
    private function uniqueValues(array $items, string $key, string $field, array &$errors): array
    {
        $seen = [];

        foreach ($items as $item) {
            $value = $item[$key];
            if (isset($seen[$value])) {
                $errors[] = sprintf('%s contains duplicate %s "%s".', $field, $key, $value);
            }
            $seen[$value] = true;
        }

        return $seen;
    }

    /**
     * @param array<string, true> $declaredPlatforms
     * @param list<string> $errors
     */
    private function validateDeclaredPlatform(
        string $platform,
        array $declaredPlatforms,
        string $field,
        array &$errors,
    ): void {
        if (!isset($declaredPlatforms[$platform])) {
            $errors[] = sprintf('%s uses platform "%s", but it is not listed in platforms.', $field, $platform);
        }
    }

    /**
     * @param array<string, true> $permissionSlugs
     * @param list<string> $errors
     */
    private function validatePermissionReference(
        ?string $permission,
        array $permissionSlugs,
        string $field,
        array &$errors,
    ): void {
        if ($permission !== null && !isset($permissionSlugs[$permission])) {
            $errors[] = sprintf('%s references undeclared permission "%s".', $field, $permission);
        }
    }

    /**
     * @param list<string> $errors
     */
    private function validateVersion(string $version, string $field, array &$errors): void
    {
        try {
            $this->versionParser->normalize($version);
        } catch (UnexpectedValueException) {
            $errors[] = sprintf('%s "%s" is not a valid semantic version.', $field, $version);
        }
    }
}
