<?php

declare(strict_types=1);

namespace B8im\ModuleSdk\State;

use B8im\ModuleSdk\Exception\InvalidStateTransition;

final class ModuleStateMachine
{
    /**
     * @var array<string, list<SystemModuleStatus>>
     */
    private const SYSTEM_TRANSITIONS = [
        'DISCOVERED' => [SystemModuleStatus::INSTALLED, SystemModuleStatus::FAILED],
        'INSTALLED' => [
            SystemModuleStatus::ENABLED,
            SystemModuleStatus::DISABLED,
            SystemModuleStatus::UPGRADING,
            SystemModuleStatus::UNINSTALLED,
            SystemModuleStatus::FAILED,
        ],
        'ENABLED' => [
            SystemModuleStatus::DISABLED,
            SystemModuleStatus::UPGRADING,
            SystemModuleStatus::FAILED,
        ],
        'DISABLED' => [
            SystemModuleStatus::ENABLED,
            SystemModuleStatus::UPGRADING,
            SystemModuleStatus::UNINSTALLED,
            SystemModuleStatus::FAILED,
        ],
        'UPGRADING' => [
            SystemModuleStatus::INSTALLED,
            SystemModuleStatus::ENABLED,
            SystemModuleStatus::DISABLED,
            SystemModuleStatus::FAILED,
        ],
        'FAILED' => [
            SystemModuleStatus::DISCOVERED,
            SystemModuleStatus::INSTALLED,
            SystemModuleStatus::DISABLED,
            SystemModuleStatus::UNINSTALLED,
        ],
        'UNINSTALLED' => [SystemModuleStatus::DISCOVERED],
    ];

    /**
     * @var array<string, list<TenantModuleStatus>>
     */
    private const TENANT_TRANSITIONS = [
        'UNAUTHORIZED' => [TenantModuleStatus::AUTHORIZED],
        'AUTHORIZED' => [
            TenantModuleStatus::ENABLED,
            TenantModuleStatus::DISABLED,
            TenantModuleStatus::EXPIRED,
            TenantModuleStatus::UNAUTHORIZED,
        ],
        'ENABLED' => [
            TenantModuleStatus::DISABLED,
            TenantModuleStatus::EXPIRED,
            TenantModuleStatus::UNAUTHORIZED,
        ],
        'DISABLED' => [
            TenantModuleStatus::ENABLED,
            TenantModuleStatus::EXPIRED,
            TenantModuleStatus::UNAUTHORIZED,
        ],
        'EXPIRED' => [TenantModuleStatus::AUTHORIZED, TenantModuleStatus::UNAUTHORIZED],
    ];

    public static function canTransitionSystem(
        SystemModuleStatus $from,
        SystemModuleStatus $to,
    ): bool {
        return in_array($to, self::SYSTEM_TRANSITIONS[$from->value], true);
    }

    public static function assertSystemTransition(
        SystemModuleStatus $from,
        SystemModuleStatus $to,
    ): void {
        if (!self::canTransitionSystem($from, $to)) {
            throw new InvalidStateTransition(sprintf(
                'Invalid system module transition: %s -> %s.',
                $from->value,
                $to->value,
            ));
        }
    }

    public static function canTransitionTenant(
        TenantModuleStatus $from,
        TenantModuleStatus $to,
    ): bool {
        return in_array($to, self::TENANT_TRANSITIONS[$from->value], true);
    }

    public static function assertTenantTransition(
        TenantModuleStatus $from,
        TenantModuleStatus $to,
    ): void {
        if (!self::canTransitionTenant($from, $to)) {
            throw new InvalidStateTransition(sprintf(
                'Invalid tenant module transition: %s -> %s.',
                $from->value,
                $to->value,
            ));
        }
    }

    private function __construct()
    {
    }
}
