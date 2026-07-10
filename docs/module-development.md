# B8IM 模块开发与生命周期

## 1. 责任边界

SDK 定义 manifest、校验、状态迁移和生命周期 PHP 合约，不直接修改 SaiAdmin Multi 数据库、动态执行前端 JavaScript 或自行推导租户身份。

服务端安装器使用 manifest 完成：

1. 将模块登记到 `sm_module`。
2. 按 SaiAdmin Multi 现有机制注册菜单和权限 slug。
3. 注册配置项、Server/IM capability 和受控路由。
4. 使用可追踪、可回滚的 migration 执行结构变更。
5. 租户运行时以 `organization` 隔离，租户端请求以服务端验证后的 `App-Id` 上下文为准。

前端菜单或按钮隐藏只是展示约束，Server 和 IM 必须重新校验系统模块状态、`sm_tenant_module_license`、用户权限和当前平台 capability。

## 2. 静态清单与运行时状态

`module.json` 是版本化的交付清单，不得写入安装进度、租户授权或失败原因。运行时状态存储在控制面数据库中。

系统模块状态：

| 状态 | 含义 |
| --- | --- |
| `DISCOVERED` | 已发现并校验交付包，尚未安装 |
| `INSTALLED` | 安装与注册完成，尚未对外启用 |
| `ENABLED` | 系统允许模块提供能力 |
| `DISABLED` | 系统禁用，不可在线调用 |
| `UPGRADING` | 正在升级，请求应失败关闭 |
| `FAILED` | 生命周期失败，需记录审计与错误 |
| `UNINSTALLED` | 功能和注册信息已移除 |

租户模块状态：

| 状态 | 含义 |
| --- | --- |
| `UNAUTHORIZED` | 无有效模块授权 |
| `AUTHORIZED` | 已授权，但租户尚未启用 |
| `ENABLED` | 授权有效且租户已启用 |
| `DISABLED` | 授权仍在，但租户已禁用 |
| `EXPIRED` | 授权已到期，请求必须立即失败关闭 |

`ModuleStateMachine` 提供系统与租户状态迁移校验。调用方必须先锁定或带版本条件更新状态，避免并发生命周期覆盖。

## 3. 安装

安装流程：

```text
解析 module.json
-> JSON Schema 校验
-> 依赖/冲突/版本/平台语义校验
-> DISCOVERED
-> 校验已安装依赖和冲突
-> 执行 install hook 和 migration
-> 注册菜单、权限、配置、路由与 capability
-> INSTALLED
```

安装失败时进入 `FAILED`，保留可审计错误。安装器不得将部分成功静默视为安装成功。

## 4. 升级

升级必须携带 `fromVersion`，目标版本是当前 manifest 的 `version`。调用方必须在进入 `UPGRADING` 前记录原状态，成功后恢复为对应的 `INSTALLED` / `ENABLED` / `DISABLED`。

升级顺序：

1. 校验新 manifest、最低系统版本、依赖和冲突。
2. 把当前模块状态原子更新为 `UPGRADING`。
3. 执行版本化 migration 和 `upgrade` hook。
4. 同步菜单、权限、配置、路由和 capability 的新声明。
5. 提交后失效模块与租户授权缓存，重启受影响的 Server/IM 进程。

失败后进入 `FAILED`，由迁移和生命周期执行器的明确回滚流程处理，不得依靠旧字段、旧路由或双写分支继续运行。

## 5. 系统和租户启停

`enable` / `disable` hook 允许系统或租户上下文：

- `LifecycleContext::organization() === null`：系统级启用/禁用。
- `LifecycleContext::organization() !== null`：指定租户的启用/禁用，值必须是正整数。

租户启用前必须同时满足：

```text
系统模块已安装且 ENABLED
AND sm_tenant_module_license 存在有效 organization + module_key 授权
AND 授权未过期、未禁用
= 允许租户进入 ENABLED
```

租户禁用只关闭功能和入口，不删除授权、配置或业务数据。禁用后菜单、页面、API 和 IM 处理器必须同时不可用。

## 6. 卸载与数据保留

卸载是系统级操作，不允许携带 `organization`。推荐顺序：

1. 禁用系统模块，阻止新请求。
2. 撤销或停用所有租户的模块启用状态，并失效缓存。
3. 执行 `uninstall` hook。
4. 移除菜单、权限关系、配置注册、路由和服务 capability。
5. 重启受影响的 Server/IM 进程，验证 API 和 IM 链路拒绝访问。
6. 状态进入 `UNINSTALLED`。

默认 `LifecycleContext::preserveData() === true`：卸载功能和注册信息，保留模块业务表和数据，以便重装或审计。只有操作人明确选择彻底清理时，才可传入 `preserveData=false` 并执行可追踪的破坏性回滚/清理迁移。执行前必须展示影响范围并记录审计，不得在普通禁用或默认卸载中删除业务数据。

## 7. 生命周期实现约定

模块生命周期类实现 `ModuleLifecycleInterface`，每个方法首先用 `LifecycleContext::assertOperation()` 校验调用类型，然后返回 `LifecycleResult`。

```php
public function uninstall(LifecycleContext $context): LifecycleResult
{
    $context->assertOperation(LifecycleOperation::UNINSTALL);

    if ($context->preserveData()) {
        // 只移除注册信息，不删除业务表。
    }

    return LifecycleResult::success('模块已卸载');
}
```

hook 声明的 `transactional=true` 表示接入方应在本地数据库事务中执行该 hook。文件、缓存、消息队列或进程重启不能伪装成同一数据库事务；这些副作用必须使用幂等、可重试且可审计的执行步骤。
