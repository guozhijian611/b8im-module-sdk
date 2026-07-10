<?php

declare(strict_types=1);

namespace B8im\ModuleSdk\Example\Announcement;

use B8im\ModuleSdk\Lifecycle\LifecycleContext;
use B8im\ModuleSdk\Lifecycle\LifecycleOperation;
use B8im\ModuleSdk\Lifecycle\LifecycleResult;
use B8im\ModuleSdk\Lifecycle\ModuleLifecycleInterface;

final class Lifecycle implements ModuleLifecycleInterface
{
    public function install(LifecycleContext $context): LifecycleResult
    {
        $context->assertOperation(LifecycleOperation::INSTALL);

        return LifecycleResult::success('公告模块已安装', [
            'module_key' => $context->manifest()->moduleKey(),
            'version' => $context->targetVersion(),
        ]);
    }

    public function upgrade(LifecycleContext $context): LifecycleResult
    {
        $context->assertOperation(LifecycleOperation::UPGRADE);

        return LifecycleResult::success('公告模块已升级', [
            'module_key' => $context->manifest()->moduleKey(),
            'from_version' => $context->fromVersion(),
            'version' => $context->targetVersion(),
        ]);
    }

    public function enable(LifecycleContext $context): LifecycleResult
    {
        $context->assertOperation(LifecycleOperation::ENABLE);

        return LifecycleResult::success('公告模块已启用', [
            'module_key' => $context->manifest()->moduleKey(),
            'organization' => $context->organization(),
        ]);
    }

    public function disable(LifecycleContext $context): LifecycleResult
    {
        $context->assertOperation(LifecycleOperation::DISABLE);

        return LifecycleResult::success('公告模块已禁用', [
            'module_key' => $context->manifest()->moduleKey(),
            'organization' => $context->organization(),
        ]);
    }

    public function uninstall(LifecycleContext $context): LifecycleResult
    {
        $context->assertOperation(LifecycleOperation::UNINSTALL);

        return LifecycleResult::success('公告模块已卸载', [
            'module_key' => $context->manifest()->moduleKey(),
            'preserve_data' => $context->preserveData(),
        ]);
    }
}
