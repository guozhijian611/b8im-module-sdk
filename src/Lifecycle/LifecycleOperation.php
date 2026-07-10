<?php

declare(strict_types=1);

namespace B8im\ModuleSdk\Lifecycle;

enum LifecycleOperation: string
{
    case INSTALL = 'install';
    case UPGRADE = 'upgrade';
    case ENABLE = 'enable';
    case DISABLE = 'disable';
    case UNINSTALL = 'uninstall';
}
