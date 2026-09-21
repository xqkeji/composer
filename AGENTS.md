# AGENTS.md — xqkeji 低代码 Composer 生成器（面向 AI 的速读地图）

本仓库是一个 **Composer 插件**（`type: composer-plugin`，`extra.class = xqkeji\composer\Plugin`），
为新齐低代码框架提供一组 `composer xqkeji:*` 脚手架命令，用来在**目标业务工程**里生成模块、控制器、
表单、表格、元素、模型等代码骨架。所有命令实现位于 `src/command/`，实际生成逻辑位于 `src/` 根
（`Form`、`Table`、`Element`、`Module`、`Controller`、`Model`、`Context`、`Lang` 等业务类）。

> 运行前提：命令需在**已安装本插件的业务工程**中执行（`composer xqkeji:xxx`）。本仓库自身不是自己的宿主，
> 在这里直接跑 `composer xqkeji:doc` 不会生效。插件依赖闭源扩展 `php-xqkeji`。

## 文档真相源（先读这两个）

- `docs/commands.json` — 全部命令的**结构化清单**（name / description / source / arguments / options / help），最适合程序与 AI 读取。
- `docs/commands.md` — 同一数据的可读版（命令一览表 + 每命令参数表 + 完整 help/示例）。

两者由 `composer xqkeji:doc` 从各命令活的 `configure()`（`setName/setDescription/addArgument/addOption/setHelp`）
反射生成。**改了命令参数或说明后重跑一次即可刷新，不要手工编辑 docs/。** 想了解任何命令的精确参数，先查 `docs/commands.json`，
本文件的命令表只是一句话概览。

## 核心心智模型

生成命令围绕一条链路和一个“当前上下文”展开：

```
module（模块）  →  controller（控制器）  →  form / table（表单 / 表格）  →  element（元素 / 列）
                          ↑ model（模型，配合控制器）
```

- **当前上下文**由 `xqkeji:use` 设置并持久化在 `runtime/composer/context.php`（当前模块 / 当前控制器 / 当前模式 form|table）。
  多数生成命令依赖它，**先 `use` 切到目标模块再操作**。
- `xqkeji:form` 建完自动切到 **表单模式**、`xqkeji:table` 建完自动切到 **表格模式**；`xqkeji:element` 依据“当前模式”决定生成到 `form/element/` 还是 `table/element/`。

### 命名与引用约定（读懂生成代码的关键）

- 名称大小写自适应：输入 `user` / `user_login` → 类名大驼峰 `User` / `UserLogin`；配置文件里的名字用小写下划线（蛇形）。各命令里反复出现的 `toCamelCase` / `toSnakeCase` 就是这套转换。
- `$el` 数组中的元素引用前缀：`@ElementName` = 复用 **base 模块**已有的元素；`~ElementName` = 复用/新建 **当前模块**的元素（`Form`/`Table::processElement` 先查 base 再查当前模块，都查不到才新建）。
- **`select_` 约定**：元素名转蛇形后以 `select_` 开头（`select_dept` 或 `SelectDept` 均可），自动创建**继承 `xqkeji\form\element\SelectModel` 的空子类**、不询问中文名，表单中以 `~SelectDept` 引用。
- 短选项支持 `-x值` / `-x=值` / `--opt=值` 多种写法，由 `src/command/NormalizesShortOptions.php` 统一规范化；`Form/Table/Element` 的 `execute` 还会直接扫描 `$_SERVER['argv']` 做分组（如 form 的 `-b/-g` tab/全局分组、element 的 `-c/-e/-r` 动作），因此这些命令的参数解析不完全走 Symfony。

## 生成的文件布局（在目标模块目录下）

| 命令 | 主要产物 |
| --- | --- |
| `xqkeji:module` | 新模块目录（本地 `app/{module}` 或注册为 composer 包）；写菜单/语言 |
| `xqkeji:controller` | `controller/{Class}.php`（默认虚拟控制器，`-f` 才落地文件）；初始化 `config/acl.php`、`menu.php`、`zh_cn.php` |
| `xqkeji:model` | `model/{Class}.php` |
| `xqkeji:action` | 控制器动作类；动作名写入语言文件 |
| `xqkeji:form` | `form/{Class}.php`（继承 `Form` 或 `TabForm`）+ 所需 `form/element/{El}.php`；表单中文名入 lang |
| `xqkeji:table` | `table/{Class}.php`（继承 `Table`/`TreegridTable`，`-D` 加 `$isDrag`）+ `table/element/{El}.php`；非 `-N` 时初始化 acl/menu/lang，**控制器默认虚拟**（不落地 `controller/{Class}.php`，`-f` 才生成实体文件，树表例外）；**默认同时用表格列自动派生同名 `form/{Class}.php`（`-F` 关闭，见下）** |
| `xqkeji:element` | 单个 `form/element/{Class}.php` 或 `table/element/{Class}.php`（可创建/修改/删除，交互选类型与项目列表） |
| `xqkeji:remove` | 删除 composer 模块（可选清理本地目录） |
| `xqkeji:path` | 把本地包目录注册为 path 仓库（symlink，便于本地联调） |

表单/表格类里最核心的字段：`$name`（蛇形配置名）、`$el`（元素/列引用数组）、表格另有 `$foot`（操作栏）与可选 `$isDrag`（拖拽排序）。

## 命令速查（一句话；精确参数见 docs/commands.json）

- `xqkeji:module {name} [-p 路径] [-t 中文名]` — 创建/注册模块。
- `xqkeji:controller {name} [-e 入口] [-a auth|login] [-A 动作列表] [-t 中文名] [-f]` — 创建控制器 + 初始化 acl/menu/lang。
- `xqkeji:use {name} [-m|-c|-f|-T]` — 切换当前模块/控制器，或设置 form/table 模式。
- `xqkeji:action {name} [-t 中文名]` — 创建控制器动作类。
- `xqkeji:model {name}` — 创建模型类。
- `xqkeji:form {name} [-e 元素...] [-b Tab] [-g 全局] [-a]` — 创建表单；`-a` 向**已存在**表单交互式追加元素。
- `xqkeji:table {name} [-e 列...] [-T 树] [-D 拖拽] [-N 不建控制器] [-f 建控制器文件] [-F 不建表单] [-a]` — 创建表格；控制器默认虚拟、并默认顺带自动建同名表单（见下）；`-a` 向**已存在**表格交互式追加列。
- `xqkeji:element {name} [-c|-e|-r] [-y 类型] [-l 项目] [-D 默认] [-m 模型]` — 创建/修改/删除单个元素。
- `xqkeji:remove {name} [-p 路径] [-f]` — 删除模块。
- `xqkeji:path {package} {path} [--copy|--no-update|--no-alias]` — 注册本地 path 包。
- `xqkeji:doc [-o 输出目录]` — 反射导出 `docs/commands.json` + `commands.md`。

## `-a` 追加元素（form/table）行为要点

- 先读取当前模块已有的 `form/{Class}.php` / `table/{Class}.php`，列出 `$el` 现有元素（编号）。
- 输入编号 = 在该元素**之后**插入；`0` 或回车 = 插到**第一个元素前面**；选中 Tab 分组会进入该 Tab 内部再选位置。
- 新元素走 `xqkeji:element` 的创建流程（终端交互弹类型/项目列表）；`select_` 前缀直接生成 SelectModel 子类；同名已存在则按 `@/~` 复用不重建。
- 用文本偏移方式写回 `$el`（`src/ElInsertTrait.php`），只插一行引用、保留文件其余内容与手工编辑。`-a` 只插入，不新建表单/表格、不涉及控制器/acl/menu/lang。

## 建表格时控制器默认虚拟（`-f` 才落地实体文件）

`xqkeji:table` 建普通表格时，控制器默认走【虚拟控制器】：不生成 `controller/{Class}.php`，只初始化 `acl.php`/`menu.php`/`zh_cn.php`——只要 acl 里有该控制器与动作定义，框架即按约定解析动作、控制器即“存在”。与 `xqkeji:controller` 的“默认虚拟、`-f/--file` 才落地文件”一致。

- 需要实体文件时加 `-f`/`--controller-file` → 生成继承 `xqkeji\mvc\Controller` 的 `controller/{Class}.php`。
- 仅作用于普通表格；**树表例外**：树表仍会复制 `controller/{表名}/` 下的动作类（admin/add/move…），因其拖拽/懒加载动作依赖实体文件。
- `-N`（完全不建控制器、不写 acl/menu/lang）与 `-f` 语义互斥：带 `-N` 时不进入控制器初始化分支。

## 建表格时自动派生同名表单（`-F` 关闭）

`xqkeji:table {name} -e 列...` 建完表格后，**默认**会用同一批列再创建一个同名表单 `form/{Class}.php`（`Table::createTable` → `Table::deriveFormElements` → `Form::createForm`）：

- 从表格列中剔除“仅表格”元素（蛇形匹配）：`id`、`create_time`、`create_date`、`update_time`、`update_date`、`edit_delete`、`delete`、`view_delete`；剩余列按原序作为表单元素，末尾追加 `submit_reset`（base 模块的提交/重置按钮，引用 `@SubmitReset`）。
- 例：`-e id,name,select_dept,select_term,status,ordernum,create_time,edit_delete` → 表单元素 `name, select_dept, select_term, status, ordernum, submit_reset`。
- 表单与表格**同名并共享控制器/中文名**（lang 键 `{模块} module {名蛇形}` 一致），元素中文名在建表时已写入 lang，所以建表单过程一般不再重复询问；`select_` 前缀列照常生成 SelectModel 子类。
- 边界：`-e` 未传列、或列全部是“仅表格”元素 → 无可用表单列，**跳过**建表单；`-F/--no-form` 强制不建；`-N` 只影响控制器不影响自动建表单（要都跳过用 `-N -F`）。自动建表单后模式仍回到 table。

## 常见工作流示例

```bash
composer xqkeji:use -- edu                 # 1) 切到 edu 模块
composer xqkeji:form Article -e title,content   # 2) 建表单（自动切表单模式）
composer xqkeji:element cover -c -y=Image       # 3) 单独加一个元素
composer xqkeji:form Article -a                   #    或：向已有表单交互式追加元素
composer xqkeji:table Article -e id,title,status,edit_delete  # 4) 建表格（自动建控制器+菜单，并自动派生同名表单）
composer xqkeji:table Article -e id,title,status,edit_delete -F  #    加 -F 则不自动建表单
composer xqkeji:table Article -a                  #    向已有表格追加一列
```
