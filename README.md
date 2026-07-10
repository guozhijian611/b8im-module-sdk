# b8im-module-sdk

B8IM 模块 SDK 定义统一的 `module.json` 清单、PHP 加载与校验器、生命周期合约以及系统/租户状态机。SDK 只提供可共享的约定；数据库记录、SaiAdmin Multi 菜单/权限注册、迁移执行和服务重启由接入方安装器负责。

## 环境与安装

需要 PHP 8.1 或更高版本。

```bash
composer install
composer validate --strict
composer test
composer lint
```

SDK 作为本地 path 包接入时，在主工程的 `composer.json` 中声明 `../b8im-module-sdk`，开发期可使用 symlink，发布时必须改用明确版本的 private Composer package 或 release tag。

## module.json

规范文件位于 [`schema/module.schema.json`](schema/module.schema.json)，完整示例位于 [`examples/announcement/module.json`](examples/announcement/module.json)。`announcement` 是 `is_builtin=true` 的内置基础公告模块，不是独立收费模块。示例包含 Admin/Tenant 完整 CRUD 权限、按钮与路由声明、可自动加载的生命周期类，以及可回滚的 Phinx 迁移。

清单必须显式声明：

- 身份与版本：`module_key`、`name`、`version`、`description`、`category`、`module_type`、`is_builtin`、`license_required`、`min_system_version`。
- 组合关系：`depends_on`、`conflicts_with`、`platforms`。
- 注册信息：`permissions`、`menus`、`routes`、`config`、`migrations`、`capabilities`。
- 生命周期：`hooks.install/upgrade/enable/disable/uninstall`。

`category` 表示业务域（如 `im`、`rtc`、`operations`），`module_type` 表示交付类型（`foundation`、`business`、`decoration` 或 `governance`），两者不得混用。`module_key` 只接受小写 snake_case。依赖和冲突使用 `{module_key, constraint}` 对象，版本约束遵循 Composer SemVer 语法。不支持旧的 `depends`、`conflicts`、`parts` 或其他历史别名。

## PHP 使用

```php
use B8im\ModuleSdk\Manifest\ManifestLoader;

$manifest = (new ManifestLoader())->load('/path/to/module.json');

echo $manifest->moduleKey();
echo $manifest->version();
```

`ManifestLoader` 依次执行 JSON 解析、JSON Schema 校验和语义校验。语义校验会拒绝自依赖、重复依赖、同一模块同时出现在 `depends_on` 与 `conflicts_with` 中、非法 SemVer 约束、未声明平台、未声明权限和未声明 capability 引用。加载成功后返回无 setter 的不可变 `Manifest`。

## 状态与生命周期

静态 `module.json` 不保存运行时状态。系统状态由 `SystemModuleStatus` 表示，租户授权/启停状态由 `TenantModuleStatus` 表示，并使用 `ModuleStateMachine` 校验迁移。这两层状态不得合并：系统已安装不等于租户已授权，租户已授权也不等于租户已启用。

`ModuleLifecycleInterface` 统一安装、升级、启用、禁用和卸载方法；`LifecycleContext` 携带 manifest、正整数 `organization`、升级源版本和卸载保留数据选项；`LifecycleResult` 返回不可变结果。详细流程见 [`docs/module-development.md`](docs/module-development.md)。
