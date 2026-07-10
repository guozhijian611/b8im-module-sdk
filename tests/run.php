<?php

declare(strict_types=1);

use B8im\ModuleSdk\Exception\InvalidStateTransition;
use B8im\ModuleSdk\Exception\ManifestValidationException;
use B8im\ModuleSdk\Lifecycle\LifecycleContext;
use B8im\ModuleSdk\Lifecycle\LifecycleOperation;
use B8im\ModuleSdk\Lifecycle\LifecycleResult;
use B8im\ModuleSdk\Lifecycle\ModuleLifecycleInterface;
use B8im\ModuleSdk\Manifest\ManifestLoader;
use B8im\ModuleSdk\State\ModuleStateMachine;
use B8im\ModuleSdk\State\SystemModuleStatus;
use B8im\ModuleSdk\State\TenantModuleStatus;

require dirname(__DIR__) . '/vendor/autoload.php';

$tests = [];

/**
 * @param callable(): void $callback
 */
function test(string $name, callable $callback): void
{
    global $tests;
    $tests[$name] = $callback;
}

function assertTrue(bool $condition, string $message = 'Assertion failed.'): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

/**
 * @param class-string<Throwable> $class
 * @param callable(): void $callback
 */
function expectException(string $class, callable $callback): Throwable
{
    try {
        $callback();
    } catch (Throwable $exception) {
        if (!$exception instanceof $class) {
            throw new RuntimeException(sprintf(
                'Expected %s, got %s: %s',
                $class,
                $exception::class,
                $exception->getMessage(),
            ));
        }

        return $exception;
    }

    throw new RuntimeException(sprintf('Expected %s, but no exception was thrown.', $class));
}

$root = dirname(__DIR__);
$loader = new ManifestLoader();
$fixturePath = $root . '/examples/announcement/module.json';
$fixture = json_decode(
    (string) file_get_contents($fixturePath),
    true,
    512,
    JSON_THROW_ON_ERROR,
);

/**
 * @param array<string, mixed> $data
 */
function manifestJson(array $data): string
{
    return json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

test('valid announcement manifest', static function () use ($loader, $fixturePath): void {
    $manifest = $loader->load($fixturePath);
    assertTrue($manifest->moduleKey() === 'announcement');
    assertTrue($manifest->isBuiltin());
    assertTrue($manifest->category() === 'operations');
    assertTrue($manifest->moduleType() === 'foundation');
    assertTrue($manifest->dependsOn() === []);
    assertTrue($manifest->conflictsWith() === []);
    assertTrue(in_array('server', $manifest->platforms(), true));
});

test('announcement declares complete admin and tenant CRUD registration', static function () use ($loader, $fixturePath): void {
    $manifest = $loader->load($fixturePath);
    $permissions = array_column($manifest->permissions(), 'slug');
    $routes = $manifest->routes();
    $menus = $manifest->menus();
    $methods = [
        'index' => 'GET',
        'read' => 'GET',
        'save' => 'POST',
        'update' => 'PUT',
        'destroy' => 'DELETE',
    ];

    foreach (['admin', 'tenant'] as $scope) {
        foreach ($methods as $action => $method) {
            $slug = "saimulti:{$scope}:announcement:{$action}";
            assertTrue(in_array($slug, $permissions, true), "Missing permission {$slug}.");

            $matchingRoutes = array_filter(
                $routes,
                static fn (array $route): bool => $route['platform'] === 'server'
                    && ($route['permission'] ?? null) === $slug
                    && in_array($method, $route['methods'] ?? [], true),
            );
            assertTrue(count($matchingRoutes) === 1, "Missing unique {$method} route for {$slug}.");

            if ($action !== 'index') {
                $matchingButtons = array_filter(
                    $menus,
                    static fn (array $menu): bool => $menu['platform'] === $scope
                        && $menu['type'] === 'button'
                        && ($menu['permission'] ?? null) === $slug,
                );
                assertTrue(count($matchingButtons) === 1, "Missing unique menu button for {$slug}.");
            }
        }
    }
});

test('announcement declares the canonical public web endpoints', static function () use ($loader, $fixturePath): void {
    $routes = $loader->load($fixturePath)->routes();
    $paths = array_column($routes, 'path');

    assertTrue(in_array('/saimulti/web/announcement/index', $paths, true));
    assertTrue(in_array('/saimulti/web/announcement/read', $paths, true));
    assertTrue(!in_array('/api/announcement/index', $paths, true));
});

test('announcement lifecycle handlers and migrations are installable inputs', static function () use ($loader, $fixturePath): void {
    $manifest = $loader->load($fixturePath);

    foreach ($manifest->hooks() as $operation => $hook) {
        [$class, $method] = explode('::', $hook['handler'], 2);
        assertTrue(class_exists($class), "Hook class {$class} is not autoloadable.");
        assertTrue(
            is_subclass_of($class, ModuleLifecycleInterface::class),
            "Hook class {$class} must implement ModuleLifecycleInterface.",
        );
        assertTrue(method_exists($class, $method), "Hook method {$operation} is missing.");
        assertTrue($method === $operation, "Hook method {$method} must match operation {$operation}.");
    }

    $handlerClass = explode('::', $manifest->hooks()['install']['handler'], 2)[0];
    $lifecycle = new $handlerClass();
    $result = $lifecycle->install(new LifecycleContext(LifecycleOperation::INSTALL, $manifest));
    assertTrue($result->isSuccessful());

    foreach ($manifest->migrations() as $migration) {
        $migrationPath = dirname($fixturePath) . '/' . $migration['path'];
        assertTrue(is_file($migrationPath), "Migration file {$migrationPath} does not exist.");

        $baseName = basename($migrationPath, '.php');
        $className = str_replace(' ', '', ucwords(str_replace('_', ' ', preg_replace('/^\d{14}_/', '', $baseName))));
        $migrationSource = (string) file_get_contents($migrationPath);
        assertTrue(
            preg_match('/final\s+class\s+' . preg_quote($className, '/') . '\s+extends\s+AbstractMigration/', $migrationSource) === 1,
            "Phinx migration class {$className} is missing.",
        );
    }
});

test('invalid module key is rejected', static function () use ($loader, $fixture): void {
    $invalid = $fixture;
    $invalid['module_key'] = 'Announcement-Legacy';

    expectException(
        ManifestValidationException::class,
        static fn () => $loader->fromJson(manifestJson($invalid), 'invalid-key.json'),
    );
});

test('dependency and conflict overlap is rejected', static function () use ($loader, $fixture): void {
    $invalid = $fixture;
    $relation = ['module_key' => 'customer_service', 'constraint' => '^1.0'];
    $invalid['depends_on'] = [$relation];
    $invalid['conflicts_with'] = [$relation];

    $exception = expectException(
        ManifestValidationException::class,
        static fn () => $loader->fromJson(manifestJson($invalid), 'dependency-conflict.json'),
    );
    assertTrue(
        str_contains(implode(' ', $exception->errors()), 'both depends_on and conflicts_with'),
        'Expected dependency/conflict semantic error.',
    );
});

test('missing required field is rejected', static function () use ($loader, $fixture): void {
    $invalid = $fixture;
    unset($invalid['hooks']);

    expectException(
        ManifestValidationException::class,
        static fn () => $loader->fromJson(manifestJson($invalid), 'missing-hooks.json'),
    );
});

test('legacy manifest aliases are rejected', static function () use ($loader, $fixture): void {
    $invalid = $fixture;
    $invalid['depends'] = [];
    $invalid['conflicts'] = [];

    expectException(
        ManifestValidationException::class,
        static fn () => $loader->fromJson(manifestJson($invalid), 'legacy-alias.json'),
    );
});

test('schema and PHP status enums stay aligned', static function () use ($root): void {
    $schema = json_decode(
        (string) file_get_contents($root . '/schema/module.schema.json'),
        true,
        512,
        JSON_THROW_ON_ERROR,
    );

    $systemStatuses = array_map(
        static fn (SystemModuleStatus $status): string => $status->value,
        SystemModuleStatus::cases(),
    );
    $tenantStatuses = array_map(
        static fn (TenantModuleStatus $status): string => $status->value,
        TenantModuleStatus::cases(),
    );

    assertTrue($schema['$defs']['system_status']['enum'] === $systemStatuses);
    assertTrue($schema['$defs']['tenant_status']['enum'] === $tenantStatuses);
});

test('system lifecycle state transitions are enforced', static function (): void {
    ModuleStateMachine::assertSystemTransition(
        SystemModuleStatus::DISCOVERED,
        SystemModuleStatus::INSTALLED,
    );

    expectException(
        InvalidStateTransition::class,
        static fn () => ModuleStateMachine::assertSystemTransition(
            SystemModuleStatus::DISCOVERED,
            SystemModuleStatus::ENABLED,
        ),
    );
});

test('tenant authorization and enablement states stay separate', static function (): void {
    ModuleStateMachine::assertTenantTransition(
        TenantModuleStatus::UNAUTHORIZED,
        TenantModuleStatus::AUTHORIZED,
    );
    ModuleStateMachine::assertTenantTransition(
        TenantModuleStatus::AUTHORIZED,
        TenantModuleStatus::ENABLED,
    );

    expectException(
        InvalidStateTransition::class,
        static fn () => ModuleStateMachine::assertTenantTransition(
            TenantModuleStatus::UNAUTHORIZED,
            TenantModuleStatus::ENABLED,
        ),
    );
});

test('lifecycle context and immutable result contracts are usable', static function () use ($loader, $fixturePath): void {
    $manifest = $loader->load($fixturePath);
    $context = new LifecycleContext(
        LifecycleOperation::ENABLE,
        $manifest,
        organization: 10001,
    );
    $context->assertOperation(LifecycleOperation::ENABLE);
    assertTrue($context->isTenantScoped());

    $result = LifecycleResult::success('enabled', ['organization' => $context->organization()]);
    assertTrue($result->isSuccessful());
    assertTrue($result->metadata()['organization'] === 10001);

    expectException(
        InvalidArgumentException::class,
        static fn () => new LifecycleContext(
            LifecycleOperation::ENABLE,
            $manifest,
            organization: 0,
        ),
    );

    expectException(
        InvalidArgumentException::class,
        static fn () => new LifecycleContext(
            LifecycleOperation::UPGRADE,
            $manifest,
            organization: 10001,
            fromVersion: '0.0.1',
        ),
    );
});

$failed = 0;
foreach ($tests as $name => $callback) {
    try {
        $callback();
        fwrite(STDOUT, "[PASS] {$name}\n");
    } catch (Throwable $exception) {
        ++$failed;
        fwrite(STDERR, sprintf(
            "[FAIL] %s\n       %s: %s\n",
            $name,
            $exception::class,
            $exception->getMessage(),
        ));
    }
}

fwrite(STDOUT, sprintf("\n%d tests, %d failed.\n", count($tests), $failed));
exit($failed === 0 ? 0 : 1);
