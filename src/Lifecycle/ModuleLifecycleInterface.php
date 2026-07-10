<?php

declare(strict_types=1);

namespace B8im\ModuleSdk\Lifecycle;

interface ModuleLifecycleInterface
{
    public function install(LifecycleContext $context): LifecycleResult;

    public function upgrade(LifecycleContext $context): LifecycleResult;

    public function enable(LifecycleContext $context): LifecycleResult;

    public function disable(LifecycleContext $context): LifecycleResult;

    public function uninstall(LifecycleContext $context): LifecycleResult;
}
