<?php

declare(strict_types=1);

namespace B8im\ModuleSdk\State;

enum SystemModuleStatus: string
{
    case DISCOVERED = 'DISCOVERED';
    case INSTALLED = 'INSTALLED';
    case ENABLED = 'ENABLED';
    case DISABLED = 'DISABLED';
    case UPGRADING = 'UPGRADING';
    case FAILED = 'FAILED';
    case UNINSTALLED = 'UNINSTALLED';
}
