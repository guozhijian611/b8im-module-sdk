<?php

declare(strict_types=1);

namespace B8im\ModuleSdk\State;

enum TenantModuleStatus: string
{
    case UNAUTHORIZED = 'UNAUTHORIZED';
    case AUTHORIZED = 'AUTHORIZED';
    case ENABLED = 'ENABLED';
    case DISABLED = 'DISABLED';
    case EXPIRED = 'EXPIRED';
}
