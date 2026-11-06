# AGENTS.md — xqkeji 低代码 Composer 生成器（面向 AI 的速读地图）

本仓库是一个 **Composer 插件**（`type: composer-plugin`，`extra.class = xqkeji\composer\Plugin`），
为新齐低代码框架提供一组 `composer xqkeji:*` 脚手架命令，用来在**目标业务工程**里生成模块、控制器、
表单、表格、元素、模型等代码骨架。所有命令实现位于 `src/command/`，实际生成逻辑位于 `src/` 根
（`Form`、`Table`、`Element`、`Module`、`Controller`、`Model`、`Context`、`Lang` 等业务类）。

> 运行前提：命令需在**已安装本插件的业务工程**中执行（`composer xqkeji:xxx`）。本仓库自身不是自己的宿主，
> 在这里直接跑 `composer xqkeji:doc` 不会生效。插件依赖闭源扩展 `php-xqkeji`。

## 文档真相源（先读这两个）

- `docs/commands.json` — 全部命令的**结构化清单**（name / description / source / arguments / options / help），最适合程序与 AI 读取。
- `docs/commands.md` — 同一数据的可读版（命令一览表 + 每命令参数表 + help/示例）。

两者由 `composer xqkeji:doc` 从各命令活的 `configure()`（`setName/setDescription/addArgument/addOption/setHelp`）
反射生成。**改了命令参数或说明后重跑一次即可刷新，不要手工编辑 docs/。**

**内容分工（改命令时照此摆放）**：`setDescription` / `addOption` 说明 / `setHelp` 是**给人在终端 `-h` 一屏看完**的概览，
一条选项说明控制在一行、示例只留主流流程；**给 AI 与人的完整规则**（边界条件、互斥关系、幂等语义、非交互行为、为什么这么设计）
写在本文件的对应小节里。所以精简 help 不会丢信息——丢的那部分本来就该在本文件。新增命令细节时优先补本文件，不要往 help 里堆长段落。

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
- **`select_` 约定**：元素名转蛇形后以 `select_` 开头（`select_dept` 或 `SelectDept` 均可），自动创建**继承 `xqkeji\form\element\SelectModel` 的空子类**、不询问中文名，表单中以 `~SelectDept` 引用。表格侧对称：`xqkeji:table -e`/`-a`/`xqkeji:element`（表格模式）遇到 `select_` 列时免选类型、不询问中文名，生成**继承 `xqkeji\form\element\ListSelectModel` 的空子类**（无 `$name`/`$text`/`$attrs`，行为由基类提供），表格中以 `~SelectDept` 引用；表格元素类型列表也含 `ListSelectModel`（显式 `-y=ListSelectModel` 同样生成空子类）。
- 短选项支持 `-x值` / `-x=值` / `--opt=值` 多种写法，由 `src/command/NormalizesShortOptions.php` 统一规范化；`Form/Table/Element` 的 `execute` 还会直接扫描 `$_SERVER['argv']` 做分组（如 form 的 `-b/-g` tab/全局分组、element 的 `-c/-e/-r` 动作），因此这些命令的参数解析不完全走 Symfony。

## 生成的文件布局（在目标模块目录下）

| 命令 | 主要产物 |
| --- | --- |
| `xqkeji:module` | 新模块目录（本地 `app/{module}` 或注册为 composer 包）；写菜单/语言 |
| `xqkeji:controller` | `controller/{Class}.php`（默认虚拟控制器，`-f` 才落地文件）；初始化 `config/acl.php`、`menu.php`（图标按名字自动推荐，`-i` 覆盖）、`zh_cn.php` |
| `xqkeji:model` | `model/{Class}.php` |
| `xqkeji:action` | 控制器动作类；动作名写入语言文件 |
| `xqkeji:form` | `form/{Class}.php`（继承 `Form`、`TabForm`（`-b`）或 `SearchForm`（`-s` 搜索表单，输入类元素带 `xq-s-字段,操作` 搜索规格、与 `-b/-g` 互斥））+ 所需 `form/element/{El}.php`；表单中文名入 lang |
| `xqkeji:table` | `table/{Class}.php`（继承 `Table`/`TreegridTable`，`-D` 加 `$isDrag`，**未传 `-D` 但列含 ordernum 自动等效 `-D`**；对**已存在**表格执行 `-D` 只补写/改写 `protected $isDrag = true;`，幂等不新建）+ `table/element/{El}.php`；`--foot=动作列表` 时另生成本表专属 `table/element/Foot{Class}.php`（继承 `ListFoot`、只声明 `$buttons`，见下节）；非 `-N` 时初始化 acl/menu/lang（**普通表默认动作集 `add/edit/admin/delete/change/b_delete`**，`b_delete` 必带：`ListFoot` 默认按钮为 `@AddButton` + `@BDeleteButton`，acl 不放行则点击无效），**控制器默认虚拟**（不落地 `controller/{Class}.php`，`-f` 才生成实体文件，树表例外；`-D`+ordernum 列时例外：复制 `src/example/src/controller/order/Admin.php` 模板落地 `controller/{表名蛇形}/Admin.php`，`$order=['ordernum'=>'asc']`，动作集加 `b_order`）；**默认同时用表格列自动派生同名 `form/{Class}.php`（`-F` 关闭，见下）** |
| `xqkeji:element` | 单个 `form/element/{Class}.php` 或 `table/element/{Class}.php`（可创建/修改/删除，交互选类型与项目列表） |
| `xqkeji:remove` | 删除 composer 模块（可选清理本地目录） |
| `xqkeji:path` | 把本地包目录注册为 path 仓库（便于本地联调）；链接/复制交给 Composer 的 `options.symlink`（`--copy` 写 `false`）；包未安装→`composer require`（走完整安装事件，含模块 `post-package-install` 钩子），包已安装→`composer update` 转本地；**装完校验 `vendor/{包}` 是否真指向本地目录，没指向就自动跑换来源阶梯 `update → reinstall →（包没装才）require → 删 vendor 目录 + install → 空提交换 reference`**（全程不用 `composer remove`，见下）；本地包无 `version` 时默认只在**宿主** `require` 写内联别名 `dev-<分支> as <系列>.x-dev`，不改本地包 |

表单/表格类里最核心的字段：`$name`（蛇形配置名）、`$el`（元素/列引用数组）、表格另有 `$foot`（操作栏）与可选 `$isDrag`（拖拽排序）。

## 命令速查（一句话；精确参数见 docs/commands.json）

- `xqkeji:module {name} [-p 路径] [-t 中文名]` — 创建/注册模块。
- `xqkeji:controller {name} [-e 入口] [-a auth|login] [-A 动作列表] [-t 中文名] [-i 图标] [-f]` — 创建控制器 + 初始化 acl/menu/lang（菜单图标按名字自动推荐，见下节）。
- `xqkeji:use {name} [-m|-c|-f|-t]` — 切换当前模块/控制器，或设置 form/table 模式。
- `xqkeji:action {name} [-t 中文名]` — 创建控制器动作类；**预定义动作名统一小写**（`add`、`b_close`、`change_password`…，命令行大小写不敏感：`add`/`Add`/`ADD` 等价，匹配后规范为小写用于语言键与类名推导），生成的动作类文件仍为大驼峰（`add → Add.php`、`b_close → b/Close.php`），因为基类是大驼峰类名 `xqkeji\mvc\action\Add`、`xqkeji\mvc\action\b\Close`；自定义（非预定义）动作保持原始输入。`admin` 动作交互式询问默认排序 `$order`（逐个输入字段 + asc/desc，可多组）与默认查询条件 `$conditions`（逐个输入字段/操作符(= <> > >= < <= like regex，默认 =)/值，可多组；纯数字按数字、其余按字符串），有设置才写入对应 `protected $order = [...]` / `protected $conditions = [['字段', '操作符', 值], ...]` 属性，留空/非交互不写。
- `xqkeji:model {name}` — 创建模型类。
- `xqkeji:form {name} [-e 元素...] [-b Tab] [-g 全局] [-s 搜索] [-a] [-r]` — 创建表单；`-s` 生成继承 `SearchForm` 的搜索表单（输入类元素自动带 `xq-s-` 搜索规格，见下；与 `-b/-g` 互斥）；`-a` 向**已存在**表单交互式追加元素、`-r` 从**已存在**表单交互式删除元素；带 `-e` 且末元素名不含 `submit` 时交互询问自动追加末尾按钮（普通表单 `@SubmitReset`，搜索表单 `-s` 为 `@SearchSubmit`）；**`-e` 不带值 = 交互循环逐个添加元素**（Tab 表单除外）。
- `xqkeji:table {name} [-e 列...] [-T 树] [-D 拖拽] [-N 不建控制器] [-f 建控制器文件] [-i 图标] [-F 不建表单] [-a] [-r] [--foot 按钮动作] [--no-check-all] [--no-pager]` — 创建表格；控制器默认虚拟、并默认顺带自动建同名表单（见下）；菜单图标按**表格中文名**（建表时询问）/表名词根自动推荐，`-i` 覆盖（见「菜单图标」节）；`-a` 向**已存在**表格交互式追加列、`-r` 从**已存在**表格交互式删除列；对已存在表格单独执行 `-D` 只补写 `protected $isDrag = true;`（幂等，不新建）；带 `-e` 时首列自动规范为 `Id`、末列名不含 `delete` 时交互询问自动追加 `@EditDelete`；`-D` 时若无 ordernum 列且其他有效列（非 ordernum/Id/含 delete）≥2 个，交互询问自动补 `@Ordernum` 为最后数据列（`@EditDelete` 之前）；`-D`+ordernum 列同时具备时（**未传 `-D` 但列含 ordernum 会自动等效 `-D`**：表格类同样写入 `$isDrag = true`，无需用户再补设置一次）：acl.php/zh_cn.php 自动加入 `b_order`（批量排序）动作，并复制模板 `src/example/src/controller/order/Admin.php` 生成 `controller/{表名蛇形}/Admin.php`（`$order=['ordernum'=>'asc']`，已存在跳过）；**`-e` 不带值 = 交互循环逐个添加列**（结束后执行上述首尾规范化，树表除外）。
- `xqkeji:element {name} [-c|-e|-r] [-y 类型] [-l 项目] [-D 默认] [-m 模型] [-f 过滤器] [-t 验证规则]` — 创建/修改/删除单个元素。`-f/-t` 仅表单元素生效（写入 `protected $filters` / `protected $vt`），表格模式会提示并忽略，`select_`/`ListSelectModel` 空子类不写这两个属性。
- `xqkeji:remove {name} [-p 路径] [-f]` — 删除模块。
- `xqkeji:path {package} {path} [--copy|--no-update|--require|--local-alias|--no-alias]` — 注册本地 path 包；**包未安装自动 `composer require`（触发安装事件），已安装则 `composer update`，随后校验 vendor 是否真指向本地目录、没指向就自动跑换来源阶梯（`update` → `reinstall` → 删 vendor 目录 + `install` → 空提交换 reference）**，`--require` 可强制；版本别名默认写进**宿主** `require`（`--local-alias` 才改本地包，`--no-alias` 完全不做），见下节。
- `xqkeji:doc [-o 输出目录]` — 反射导出 `docs/commands.json` + `commands.md`。

## `xqkeji:table` 细则（终端 help 已精简为一屏概览，完整规则以此为准）

命名大小写转换、`@base` / `~本模块` 引用形式见上「命名与引用约定」；`-a`/`-r`、`-F` 自动派生表单、`-f` 虚拟控制器、`--foot`、`-i` 图标各有专节（下方）。这里收录终端 help 精简掉的分支与幂等语义。

- **`-e` 的三种写法**：`-e id,Username`（逗号分隔）、`-e id -e Username`（可多次，`multiple values allowed`）、只给 `-e` 不带值 → 交互循环逐个加列（每次问列名，留空结束，加入后问是否继续）。值可引号包裹并在逗号后留空格 `-e "User Name, Login Time"`；**不加引号时逗号后不能有空格**，否则被 shell 拆成多个参数。`--no-interaction` 下走交互循环会报错退出。
- **树表 `-T` 忽略 `-e`**：改用内置默认列（首 `@Id`、尾 `~EditDelete{表}`），因此交互循环与下面的首尾规范化都不作用于树表；`-T` 同时不支持 `--foot`（底栏由 tree 模板生成）。
- **首尾自动规范化**（带了 `-e` 才做，元素先统一解析成 `@/~` 引用再判断）：**首列**必须是主键 `Id`——列表已含 `Id` 但不在首位 → 自动移到首位；完全不含 → 按 `xqkeji:element` 流程创建/复用 `Id` 并插到首位（**不询问**）。**末列**元素名不含 `delete` → 交互式询问是否追加操作列 `@EditDelete`（默认追加；非交互直接追加并提示）。
- 新建元素会询问中文名，已存在的元素不询问；`select_` 前缀列免选类型、生成继承 `ListSelectModel` 的空子类，`-a` 追加时同样适用。
- **`-D` 只多做一件事**：表格类仍继承 `Table`，额外生成 `protected $isDrag = true;`。序号列（`ordernum`）是拖拽排序的前端依赖（新顺序提交到 `/b-order`）：
  - **自动等效 `-D`**：未传 `-D`，但最终列里含名称为 `ordernum` 的元素（`@Ordernum` / `~OrdernumXxx`）→ 同样写 `$isDrag = true`，且与下面 `b_order`/`Admin.php` 链路**同开同关**，无需再补一次设置。
  - **自动补 `@Ordernum`**：`-D` 且列中【没有任何】`ordernum` 元素、其他有效列（排除 ordernum / Id / 名含 delete 的操作列）≥ 2 个 → 交互式询问是否把 `@Ordernum`（base 元素）加为最后一个数据列：末列已是删除类操作列时插在其**前**（`@EditDelete` 之前），否则插到末尾（之后仍走 `@EditDelete` 询问）；非交互默认添加并提示；已有 `ordernum` 列则不询问、不挪位置。
  - **`b_order` 动作**：`-D` 与 `ordernum` 列**两者同时具备**才生效 —— `acl.php` 动作集追加 `b_order`（`add/edit/admin/delete/change/b_delete/b_order`），`zh_cn.php` 写 `{模块} {控制器} b_order title/success/failed` 与 `{模块} module {控制器} b_order auth`（中文名「批量排序{控制器}」）；`-N` 时不涉及 acl/lang。
  - **`Admin.php` 模板**：同上条件复制 `src/example/src/controller/order/Admin.php` → `controller/{表名全小写蛇形}/Admin.php`（继承 `xqkeji\mvc\action\Admin`、`protected $order = ['ordernum' => 'asc']`），占位符 `{MODULE_NAME}`/`{CONTROLLER_NAME}` 自动替换，**目标已存在则跳过不覆盖**；该动作类目录与虚拟控制器并存（`-f` 的实体文件仍按 `-f` 自己的规则生成）。
  - **对已存在表格执行 `-D`**（可只传表名，不需要 `-e`）：不新建表格，幂等补写 `$isDrag` —— 无该属性则在 `$el` 前插入 `protected $isDrag = true;`；`$isDrag = false` 改为 `true`；已是 `true` 跳过。树表执行 `-D` 会提示并跳过（`TreegridTable` 自带拖拽）。
  - `-D` 与 `-T` 互斥：带 `-T` 时忽略 `-D`。
- **`-N`**：只建表格类与元素，跳过控制器创建与 `acl.php`/`menu.php`/`zh_cn.php` 初始化；树表还同时跳过模型类与集合初始化。
- **`-f` 只影响普通表格**：树表始终复制 `controller/{表名}/` 下的动作类（`admin`/`add`/`move` 等），因为拖拽与懒加载动作依赖实体文件。
- **普通表默认动作集** `add/edit/admin/delete/change/b_delete`（与 `xqkeji:controller` 一致），`b_delete` 必带：base 的 `@Foot` 默认渲染「添加 + 删除」，前端 `xq-batch` 把选中行 POST 到 `…/{控制器}/b_delete`，acl 不放行则按钮点了无效。
- **`-a` 与 `--foot` 的区别**：`-a` 维护的是**列**（`$el` 里的列元素），`--foot` 维护的是**底部操作栏按钮**（`$foot`）；两者都只动当前模块自己的文件，绝不写回 base。

## `xqkeji:form` 细则（终端 help 已精简，完整规则以此为准）

- **前提**：先用 `xqkeji:use` 切到目标模块；表单类落 `form/{Class}.php`、元素落 `form/element/{Class}.php`，建完自动切到**表单模式**。表单名自动转大驼峰（`user_login` → `UserLogin`），`$name` 用蛇形。
- **`-e` 的三种写法**：`-e Username,Password`（逗号）、`-e Username -e Password`（可多次）、只给 `-e` 不带值 → 交互循环逐个加元素（每次问名称，留空结束，加入后问是否继续）。值可引号包裹并在逗号后留空格 `-e "User Name, Login Time"`；**不加引号时逗号后不能有空格**（会被 shell 拆成多个参数）。`--no-interaction` 下走交互循环会报错退出；**Tab 表单（`-b`/`-g`）不支持交互循环**。
- **末尾按钮自动追加**（带了 `-e` 才做）：元素先统一解析成 `@/~` 引用，若**最后一个元素名不含 `submit`**（大小写不敏感）→ 交互式询问是否追加末尾按钮（默认追加），非交互直接追加并提示。按钮按表单类型区分：**普通表单 `@SubmitReset`（提交/重置），搜索表单 `-s` 为 `@SearchSubmit`**；末元素已含 submit（如 `@SearchSubmit`）不询问；**Tab 表单不做此检查**（结构不同）。
- **`select_` 前缀元素**：蛇形 `select_dept` 与驼峰 `SelectDept` 等价（内部统一转蛇形后判断前缀），自动创建**继承 `xqkeji\form\element\SelectModel` 的空子类**（类名转大驼峰，类体为空、行为由基类提供、**不询问中文名**），表单中以 `~SelectDept` 引用；base 或当前模块已有同名元素时直接按 `@/~` 引用、不重复创建。
- **Tab 表单 `-b`**：继承 `TabForm`；`-b "基本信息"` 开一个分组，**其后的 `-e` 元素归入该组**，可多次；Tab 英文名自动生成 `{表单名小写下划线}_tab{序号}`（`user_tab1`、`user_tab2`），中文名加不加引号均可。`-g/--global` 使其后的 `-e` 落在 **Tab 之外的全局区**（`-b … -e … -b … -e … -g -e submit_reset`）。
- **搜索表单 `-s`**：`use xqkeji\form\SearchForm` + `extends SearchForm`，自带 `$attrs`（`method=get` + `d-flex` 行内排版）；目录、`$name` 蛇形、`@/~` 引用、`select_` 约定、中文名入 lang、自动切表单模式**均与普通表单相同**。与 `-b/-g` **互斥，同时指定报错退出**（基类只能有一个）。**与同名普通表单会因类文件同名冲突**（`form/{Class}.php` 已存在则报错），建议起名 `{控制器}Search`。元素规格与 `xq-s-`/`@search` 规则见下「搜索表单 `-s` 与 `xq-s-` 元素规格」。
- **`-a`/`-r`**：见下「`-a` 追加 / `-r` 删除元素」专节（`-a` 与创建互斥、只插入不新建表单；`-r` 不能与 `-e/-b/-g/-a/-s` 同用）。

## `xqkeji:element` 细则（终端 help 已精简，完整规则以此为准）

- **落点由当前模式决定**：先 `xqkeji:use -f`（表单模式 → `form/element/`）或 `-t`（表格模式 → `table/element/`）。元素名自动转大驼峰（`user_name` → `UserName`）。
- **动作**：不传 `-c/-e/-r` 时默认执行**创建**；`-e` 修改已存在元素（`-y=Select` 直接改类型，`-y` 不带值弹选择列表）；`-r` 删除会要求确认。
- **`-y` 不带值 = 强制弹出类型选择列表**；`-y=类型名` 直接指定。
- **选项类元素（Select/Check/Radio）**：`-l` 项目列表，格式 `值1|文本1,值2|文本2`，**也支持 `值=文本` 分隔符**；`-D` 默认值；`-m` 指定模型名（项目列表由模型动态加载，**需交互设置字段名**）。Select 自动 `class => 'form-select'`；Check/Radio 自动 `template => '@check'`。
- **`-f/--filters`（仅表单元素）** 写入 `protected $filters = [...]`：多个用逗号分隔（`trim,upper`），**带参数的过滤器用 `名称=>参数1|参数2`**（`replace=> |-`、`regex=>/^a$/`、`remove=>ab`）。完整可选值（24 项）：`absint`、`alnum`、`alpha`、`bool`、`email`、`float`、`int`、`lower`、`lowerFirst`、`regex`、`remove`、`replace`、`special`、`specialFull`、`string`、`striptags`、`trim`、`upper`、`upperFirst`、`upperWords`、`url`、`html`。只传 `-f` 不带值时交互输入，**直接回车默认 `string`**。
- **`-t/--vt`（仅表单元素）** 写入 `protected $vt = [[...], ...]`：**多条规则用分号分隔，规则参数用冒号分隔** —— `required;length:3,20` → `[ ['required'], ['length', '3,20'] ]`；前端规则可带 `$` 前缀（如 `$confirm`）。只传 `-t` 不带值时交互输入，**留空则不写该属性**。
- `-f`/`-t` 未传时生成的元素类**不含**这两个属性（保持原有行为）；**表格模式（`xqkeji:use -t`）下传 `-f`/`-t` 会提示并忽略**；表格侧 `select_` 前缀与显式 `-y=ListSelectModel` 生成继承 `xqkeji\form\element\ListSelectModel` 的空子类（无 `$name`/`$text`/`$attrs`、不询问中文名），**也不写这两个属性**；表格模式下 `-e` 修改 `select_` 元素同样覆盖重写为空子类。

## `xqkeji:action` 预定义动作全表与 `admin` 的 `$order`/`$conditions`

动作建在**当前控制器**下（先 `xqkeji:use {模块}` + `xqkeji:use {控制器} -c`）。`-t 中文名` 写进 `lang/zh_cn.php` 的 `title`/`success`/`failed`/`auth` 四条键。

- **预定义动作（统一小写；命令行大小写不敏感，命中后规范为小写用于语言键与类名推导）**：

  `add` 添加、`admin` 管理、`captcha` 验证码、`change` 修改、`delete` 删除、`display` 查看、`edit` 编辑、`emailcode` 邮箱验证码、`export` 导出、`getoption` 获取选项、`login` 登录、`logout` 退出登录、`publish` 发布、`reg` 注册、`reset` 重置、`submenu` 子菜单、`subnode` 子节点、`b_close` 批量禁用、`b_delete` 批量删除、`b_open` 批量启用、`b_order` 批量排序、`change_password` 修改密码、`update_config` 更新配置、`update_statics` 更新静态文件

- **预定义动作**继承 `xqkeji\mvc\action\` 下对应基类，生成**空类体**（行为完全由基类提供，**不要重写 `run()`**）；未收录的自定义动作名继承 `xqkeji\mvc\Action`，需自行实现 `run()`，动作名保持原始输入不规范化。
- **类文件路径**始终大驼峰，名含 `_` 或 `-` 时第一段变子目录：`add` → `Add.php`、`b_close` → `b/Close.php`、`change_password` → `change/Password.php`（因为基类名也是大驼峰 `xqkeji\mvc\action\Add`、`xqkeji\mvc\action\b\Close`）。
- **`admin` 动作额外交互**（其他动作没有这两问）：
  - **默认排序 `$order`**：逐个输入排序字段名（如 `ordernum`，留空跳过）与该字段排序方式（`asc`/`desc`，默认 `asc`），可继续加多个字段（同名重复设置会被覆盖）；有设置才写入 `protected $order = ['字段' => 'asc|desc', ...];`，未设置不写该属性；**非交互直接跳过**。
  - **默认查询条件 `$conditions`**：逐个输入三元组——字段名（如 `pos_id`，留空跳过）、操作符（`= / <> / > / >= / < / <= / like / regex`，默认 `=`）、值（**纯数字按数字写入，其余按字符串**），可继续加多条；有设置才写入 `protected $conditions = [['字段', '操作符', 值], ...];`（如 `[['pos_id', '=', 4], ['status', '=', 1]]`），未设置不写；**非交互直接跳过**。

## `xqkeji:controller` 入口与配置矩阵

- **默认动作集及中文名**（不带 `-A`/`-s` 时）：`admin` 管理、`add` 添加、`edit` 编辑、`delete` 删除、`change` 修改、`b_delete` 批量删除。
- **入口（`-e`）决定写哪些配置**：

  | 入口 | ACL | menu.php | zh_cn.php | 说明 |
  | --- | --- | --- | --- | --- |
  | `admin`（默认） | ✔ | ✔ | ✔ | 完整后台控制器：权限 + 菜单 + 语言 |
  | `member` / `teacher` 等自定义入口 | ✔ | ✘ | ✘ | 只更新 ACL，不处理菜单与语言 |
  | `guest` | ✔ | ✘ | ✘ | 直接把控制器和动作写入 ACL，**不区分 auth/login**（`-a` 无效），默认动作 `index` |

- **`-a/--auth` 只有 `auth`（默认，需授权）与 `login`（需登录）两个值**，`guest` 入口忽略它。
- **短参数区分大小写**：`-A` 与 `-s` 都是 `--actions`（可多次 `-A admin -A delete` 或逗号 `-A admin,delete`），**`-a` 是 `--auth`**。
- **`-t 中文名`** 用于菜单标题与语言键（`-t 学期` → 菜单「学期管理」、语言「添加学期」等）。
- **`-f/--file`** 才生成实体文件 `controller/{Class}.php`（继承 `xqkeji\mvc\Controller`）；**默认虚拟控制器**——只要 `acl.php` 里有该控制器与动作定义，框架按约定解析动作即可生效。
- **菜单图标**：`-i` 显式指定 > 中文名关键词 > 名词根 > `crc32` 兜底池；只在**确认新增菜单项后**执行，已存在的 url 命中即 return，手工改过的图标不会被覆盖。详见「菜单图标自动推荐」。

## `xqkeji:module` / `remove` / `model` / `use` / `doc` 要点

- **`xqkeji:module {name}`**：本地模块（如 `home`）建到当前项目 `app/{模块名}/`；composer 包模块（`xqkeji/xq-app-home`）**必须** `-p` 指定包根父目录，创建包目录后自动软链接进 vendor，并从包名提取模块名（`xq-app-home` → `home`）。模块名只能小写字母、数字、下划线。`-t 中文名` 写进 `menu.php` 与 `lang/zh_cn.php`。创建后自动设为当前模块。选项可放任意位置，`-t值` / `-t 值` / `-t=值` / `--title=值` 等价（由 `NormalizesShortOptions` 归一）。包模块做好后要让宿主按本地路径安装，用 `xqkeji:path`。
- **`xqkeji:remove {包名}`**：清掉四处痕迹——宿主 `composer.json` 的 `require` 与 path repository、`config/composer.php` 的模块映射、vendor 里的软链接（`composer remove` 只清第一处和 vendor 包本体）。`-p` 指定包所在父目录时**先核对那个目录里 `composer.json` 的 `name`**，匹配才删源码目录；不匹配只警告并要求**单独确认，这条安全确认 `--force` 跳不过**。`-f/--force` 只跳过常规确认。
- **`xqkeji:model {name}`**：建在**当前模块**的 `model/` 下（先 `xqkeji:use` 切模块），产物是 `namespace app\{模块}\model` 下继承 `xqkeji\mvc\model\Model` 的**空子类**（字段与方法按框架约定由基类提供）。名只能字母开头 + 字母/数字/下划线，`user_type` / `UserType` 等价（自动转大驼峰）；**同名文件已存在会报错，不覆盖**。
- **`xqkeji:use`**：上下文（当前模块 / 当前控制器 / 当前模式 form|table）持久化在 `runtime/composer/context.php`。不带参数 = **显示**当前上下文；默认 `-m` 切模块（可省略）；`-c` 切控制器（**要先有当前模块**）；`-f` 表单模式、`-t` 表格模式（决定 `xqkeji:element` 落到哪个目录）。控制器名支持 `user_type`（自动转 `UserType`）。`-f/-t/-c` 同时给出时**只执行一件**，优先级顺序为表单模式 → 表格模式 → 控制器。`xqkeji:form` 建完自动切表单模式、`xqkeji:table` 建完自动切表格模式。
- **`xqkeji:doc [-o 目录]`**：反射各命令**活的** `configure()`（name/description/source/arguments/options/help）产出 `commands.json` + `commands.md`，自动过滤 Console 全局选项（`--help/--quiet/--verbose/--version/--ansi/--no-ansi/--no-interaction`）；默认输出目录由本命令文件位置定位到**插件仓库根**的 `docs/`，与在哪个工程运行无关。**不要手工编辑 docs/**。

## `-a` 追加 / `-r` 删除元素（form/table）行为要点

- 先读取当前模块已有的 `form/{Class}.php` / `table/{Class}.php`，列出 `$el` 现有元素（编号）。
- 输入编号 = 在该元素**之后**插入；`0` 或回车 = 插到**第一个元素前面**；选中 Tab 分组会进入该 Tab 内部再选位置。
- 新元素走 `xqkeji:element` 的创建流程（终端交互弹类型/项目列表）；`select_` 前缀直接生成 SelectModel 子类；同名已存在则按 `@/~` 复用不重建。
- 用文本偏移方式写回 `$el`（`src/ElInsertTrait.php`），只插一行引用、保留文件其余内容与手工编辑。`-a` 只插入，不新建表单/表格、不涉及控制器/acl/menu/lang。
- 目标是 `SearchForm` 子类时：现有数组项按 `@X name='xq-s-…'` 展示；新元素若为输入类会同样询问【搜索字段/搜索操作】并插入数组项（见下节），无输入控件仍插纯引用。
- **`-r/--remove` 删除元素引用**（`Form::removeElementsFromForm` / `Table::removeElementsFromTable`，底层 `src/ElRemoveTrait.php`）：按编号列出 `$el`（Tab 分组显示为 `[Tab] 名称（n 个元素）`，其内部元素递归编号为 `父.子`，如 `2.1`；搜索表单数组项显示为 `@X name='xq-s-…'`），接受**逗号分隔的多个编号**一次删除多条（`3` 或 `2,5.1`；留空取消，非法编号报错不落盘）。删除是按【整行文本区间】移除引用行（`elDeleteRegions` + `elApplyDeletes`），文件其余属性、注释与手工编辑原样保留；选中 Tab 分组编号即删掉整个分组，此时其内部元素的区间会被自动丢弃以免重复删。
- `-r` 与创建/追加互斥（form 侧不能与 `-e/-b/-g/-a/-s` 同用，table 侧不能与 `-e/-T/-D/--foot/-a` 同用），只动当前模块的这一个表单/表格文件，**不碰 acl.php/menu.php/zh_cn.php**、不反向删除控制器，也不会同步删掉同名配套表单里的引用（需另跑一次 `xqkeji:form {表} -r`）。
- `-r` 的元素类文件清理是**可选、默认不删**：被删引用若为 `~本模块元素`，会在当前模块 `form/*.php` 与 `table/*.php` 全文复查 `~元素名` 是否还有引用（表格的 `$el` 与 `$foot` 都算，靠负向前瞻避免 `~User` 命中 `~UserName`）；仍被引用则打印提示并保留，确认全无引用才询问是否顺带删除 `form/element/{X}.php` / `table/element/{X}.php`。`@base` 元素永不删。

## 搜索表单 `-s` 与 `xq-s-` 元素规格

`xqkeji:form {name} -s` 生成继承 `xqkeji\form\SearchForm` 的搜索表单（GET 提交，生成类自带 `$attrs = ['method'=>'get','class'=>'d-flex …']`），与 `-b/-g` 互斥。搜索表单的输入元素必须声明搜索目标：

- `$el` 条目写成数组项：`[ '@SearchKey', 'name' => 'xq-s-username|fullname,like' ]`；`xq-s-` 前缀固定，`|` 表示多字段“或”搜索，`,op` 为搜索操作。无输入控件（类名链尾含 submit/reset/button/hidden）仍写纯字符串引用。
- **`@search` 模板**：搜索表单的输入元素统一用模板 `@search`（小写）。建 `-s` 表单时**新建**的元素类（含 select_ 子类）类体内写 `protected $template = '@search';`；**复用**的既有元素（`@X`/已有 `~X`，及 `-a` 追加）不改类文件，在 `$el` 数组项内联 `'template' => '@search'`；继承链已声明 `@search`（如 base SearchKey）则两者都省略。
- **操作符用词别名**（符号 `= < >` 等会污染 GET URL）：`eq`(=) `ne`(<>) `gt`(>) `gte`(>=) `lt`(<) `lte`(<=) `in` `nin` `regex` `like`。
- 规格来源优先级：**内联** `-e "元素=字段|字段,op"`（免询问）> **交互两问**（字段默认=元素名蛇形，**select_ 前缀元素去掉 `select_` 再补 `_id`**，如 `select_section` → `section_id`；操作默认：文本类元素 Text/Textarea/SearchKey 为 `like`，其余 `eq`）> **非交互默认值**（打印提示，等价直接回车）。内联规格可省略 `,op`、可带 `xq-s-` 前缀；非法则报错回退交互/默认。
- 搜索表单末尾自动追加的按钮与普通表单不同：`-s` 表单补 **`@SearchSubmit`**（base 的搜索按钮），普通表单补 `@SubmitReset`；末元素已含 `submit` 时不询问。
- 实现：`Form::buildSearchEntry`（判定+询问）、`Form::parseSearchSpec`（内联解析）、`Form::elementClassChain`（沿 `class X extends Y` 上溯判定），`ElInsertTrait::elInsertRef` 支持数组项多行插入。

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

## 表格底部操作栏按钮 `--foot`（每表一份，不改 base 的共享 foot）

框架底栏由 `xqkeji\form\element\ListFoot` **自己渲染**（`<tfoot>` → 全选 → `d-flex` 容器 → `me-auto` 按钮组 → 分页条），子类只声明三个属性：

```php
class FootEmail extends BaseListFoot
{
    protected $name = 'list_foot';                    // base 各 foot 都用这个名，不必每表唯一
    protected $buttons = ['@AddButton', '@BDeleteButton', /* 或内联 $Button 条目 */];
    protected $checkAll = [];                         // 省略=保留全选框；写成 [] 即关掉
    protected $pager = [];                            // 省略=保留分页条；写成 [] 即关掉
}
```

历史上 base 里是 `Foot → Toolbar → AddDelete` 三级共享类，加一个按钮只能改 `AddDelete`（全站表格跟着变，且写在 vendor 里 `composer update` 就丢），所以还有 `FootExport`/`FootOnlyDelete` 这类“按钮组合变体”。现在 `Toolbar`/`AddDelete` 已不存在，`--foot` 直接为这张表生成一份 `Foot{表}` 子类。

- `xqkeji:table {表} --foot=add,b_delete,export`（建表时）/ `xqkeji:table {表} --foot=export`（**已存在**表格）；`--foot` 不带值 = 交互循环录入动作名，`--no-interaction` 下必须给列表。
- 产物：本模块 `table/element/Foot{Class}.php`（只声明 `$buttons`，需要时才带 `$checkAll = [];` / `$pager = [];`）；表格类 `$foot` 改写为 `'~Foot{Class}'`（无 `$foot` 属性时在 `$el` 前补写）。此后这张表加/减按钮只动这一个文件。
- `$buttons` 条目两种写法：动作在 base 已有按钮元素时**直接引用**（`Table::FOOT_BASE_BUTTONS`：`add→@AddButton`、`b_delete→@BDeleteButton`、`export→@ExportButton`）；其余（`b_open`/`b_close`/`b_order`/自定义动作）**内联** `['$Button','name'=>动作,'attrs'=>['value'=>文案,'class'=>'btn btn-X me-1 行为类']]`。`--foot` 的条目两种写法都收：既可写动作名，也可直接写 base 按钮元素名（`AddButton`/`BDeleteButton`/`ExportButton`，大小写不敏感、可带 `@`，等价于对应动作）。base 的 `EditButton`/`ViewButton`/`DeleteButton` 是操作列的 `btn-sm` 小按钮（`~EditDelete` 用），写进 `--foot` 会被拒绝（`Table::FOOT_COLUMN_BUTTONS`）。
- **`name` 就是控制器动作**——`xq-res-admin-page` 的 `xq-batch` 会把选中行 POST 到 `…/{controller}/{name}`（`xq-url` 可覆盖），所以生成器同时把动作并入 `config/acl.php` 并补 `zh_cn.php` 的 title/success/failed/auth（`Controller::mergeControllerActions`：读原动作列表取并集写回，不覆盖已有；`Lang::set` 只在键缺失时赋值）。
- 内置词表 `Table::FOOT_BUTTONS`：`add 添加(primary/xq-add)`、`b_delete 删除(danger/xq-batch)`、`b_open 启用(success/xq-batch)`、`b_close 禁用(secondary/xq-batch)`、`b_order 排序(info/xq-batch)`、`export 导出(warning/xq-export)`。未收录的动作名会问【按钮文案】与【前端行为类】（`FOOT_BEHAVIORS`：`xq-batch/xq-add/xq-edit/xq-view/xq-delete/xq-copy/xq-export/空`），非交互默认 `动作名 + xq-batch`。动作名统一小写、`-` 归一为 `_`（与 `xqkeji:action` 一致）。
- `--no-check-all` / `--no-pager`（须与 `--foot` 同用）关掉全选框 / 分页条：新建的 foot 类直接写上 `$checkAll = [];` / `$pager = [];`；已有 foot 文件则由 `applyFootFlags` 补写、或把已有的非空声明改写为 `[]`（会打印原值），已是 `[]` 则不动。
- 已存在表格的追加是**文本插入**（`insertFootButtons`）：定位 `protected $buttons = [`，按括号深度（跳过引号内容）找到配对 `]` 与末条目，把缺失条目插在其后（缩进沿用已有条目；空数组会展开成多行）；同动作条目（`'@AddButton'` 反查或内联 `'name' => '动作'`）已存在则跳过，文件其余内容与手工编辑（含 tooltip 里的 `<br/>`、方括号）原样保留。
- 只写本模块的文件，**绝不写回 `'@X'`（base）文件**；遇到 1.1.53 之前生成的旧“三级内联”foot 文件（没有 `$buttons`）会按本次 `--foot` 给出的**完整**按钮列表把它整体重写为新结构（`rewriteLegacyFoot`，沿用文件原有的 `$name`；旧 `$el` 里的手工改动如自定义 tooltip 需自行搬回 `$buttons`），只带 `--foot` 不给列表时报错、不动文件。
- 边界：树表（`-T`）的 foot 由 `src/example` 的 tree 模板生成（`FootTree.php`，复制时 `{TABELE_NAME}` 换成表名、`{中文名}` 换成表格中文名，`Tree` 后缀换成表名大驼峰），`--foot` 提示并跳过；模板自带 `protected $pager = [];`（树表不分页，`$checkAll` 保留框架默认全选框），因此这次改动之前生成的树表 foot 文件需手工补这一行（`ensureTreeElements` 对已存在文件幂等跳过，且树表不走 `--no-pager` 分支）；`-N` 只生成 foot 文件、不写 acl/lang；从 `@Foot` 切到 `~Foot{表}` 后，base foot 里其它按钮不再出现在这张表（需要就一并写进 `--foot`）。

## 菜单图标自动推荐（`src/MenuIcon.php`，`-i` 覆盖）

`xqkeji:controller` 写 `menu.php` 时不再一律给 `bi bi-list`，而是按名字推荐 bootstrap-icons 图标（纯离线关键词表，不调任何外部接口）：

- 优先级：**`-i/--icon` 显式指定** > **中文名关键词**（`-t` 传入的标题，取命中的**最长**关键词，故「管理员」不会被「用户」抢先）> **控制器蛇形词根**（`user_login` → `user`，按词根出现顺序，先命中先用）> **`crc32(控制器名) % 兜底池`**（约 24 个中性图标；未命中关键词时也不再千篇一律，且同名恒同图标、可复现）。表与池都不可用才回退 `bi bi-list`。
- 关键词表在 `MenuIcon::ICONS`（`图标 => [中文词…, 英文词根…]`），扁平表由 `keywordMap()` 惰性构建；覆盖用户/部门/权限/订单/商品/库存/财务/统计/日志/消息/文件/图片/课程/成绩/班级/物流/审批… 约 60 个业务域。
- **表内图标名必须真实存在**：宿主 `vendor/xqkeji/xq-res-bootstrap-icons/src/font/bootstrap-icons.css` 共 1996 个 `.bi-*`，加新条目后按文件名逐个核对（如 `list-check`/`people`/`currency-dollar`/`ui-checks`/`box-seam` 存在，`ui-toggles`/`bankcoin`/`note-text` **不存在**）。
- `-i` 三种写法等价并自动补全：`person` / `bi-person` / `bi bi-person` 都归一为 `bi bi-person`（`MenuIcon::normalize`）。
- 图标推荐只在**确认要新增菜单项之后**执行并打印 `↻ 菜单图标: …（来源）`：`hasMenuUrl()` 命中已存在的 url 会先 return，所以你手工改过的图标永远不会被覆盖。
- 建表格同样生效：`xqkeji:table` 也有 `-i/--icon`（`TableCommand` → `createTable` 末位可选 `string $menuIcon` → `ensureNormalController`/`ensureTreeController` 末位同名参数 → `Controller::initControllerConfig` 第 7 参 `$icon`）。标题关键词取**建表时询问得到的表格中文名**（table 命令没有 `-t/--title`，别按 `-t` 写脚本）；`-N` 不写菜单，`-i` 自然无意义。

## `xqkeji:path` 的版本别名（默认落在宿主，不动本地包）

path 仓库里**没有 `version` 字段**的本地包，Composer 用 `VersionGuesser` 按当前分支报出 `dev-<分支>`（`.git` 都没有时固定回退 `dev-main`；有 `.git` 但读不到分支=游离 HEAD/worktree 时报的是最近 tag）。这个 `dev-*` 版本不满足其它包对该包的 `^x` 稳定约束（例如所有 `xq-res-*` 与 `xq-app-admin` 都要求 `xqkeji/composer: ^1.0`），而 path 仓库是 canonical、不会回退到 packagist，于是解析直接失败。`src/command/PathCommand.php` 的三种处理：

- **默认（`inlineAliasConstraint`）**：把别名写在**宿主项目自己的** `require` 里 —— `"包名": "dev-<分支> as <系列>.x-dev"`，本地包文件零改动。Composer 2.1+ 会把宿主根的内联别名作为一个等价版本加入解析池（`DependencyResolver/Pool` 由 `rootAliases` 生成 `AliasPackage`），因此其它包传递依赖的 `^x` 也能命中。
- **`--local-alias`（`ensureBranchAlias`，1.1.43 起的旧默认）**：把 `<系列>.x-dev` 写进**本地包** `composer.json` 的 `extra.branch-alias.dev-<分支>`，宿主 `require` 保持 `*`；换任何宿主都自动生效，代价是改动本地包文件（整份 `composer.json` 会按 `JSON_PRETTY_PRINT` 重排）。
- **`--no-alias`**：两边都不写，`require` 用 `*`（只适合本地包自带 `version`、或没人用稳定约束卡它）。与 `--local-alias` 互斥，同时给会报错退出。
- 免别名的两种情形：本地包已有 `version` 字段，或已声明**当前分支**的 `extra.branch-alias`（path 仓库报出的版本已可用）→ `require` 写 `*` 并打印说明。游离 HEAD/worktree 同样跳过别名（猜分支会让根约束解析失败），只提示。
- 分支名取自本地包 `.git/HEAD`；系列号（`major.minor`）优先级：① 该包已安装/已发布的最新稳定版（`registryLatestStable`，无需 git）② 本地 git 最新 tag（`detectLatestTag` 直接读 `.git/refs/tags` 与 `packed-refs`，不调 shell）③ 交互输入。每次运行按最新版本自动推进，宿主 `require` 里的旧别名会被覆盖并打印「由 … 改为 …」。
- **读不到分支时的差别**：内联别名（默认）模式下无 `.git` 直接按 `dev-main`（与 Composer 自己的回退一致），有 `.git` 但处于游离 HEAD / worktree 则**跳过别名**并提示（那时 Composer 报的是最近 tag 的版本，猜分支只会让根约束解析失败）；`--local-alias` 模式下读不到分支会**交互询问分支名（默认 `main`）**，因为别名键要写进本地包文件、必须由本命令确定分支。
- **系列号定不出来时同样跳过别名**（内联模式）：未安装/未发布过、本地无 git tag、且非交互无法询问 → `require` 用 `*`，打印「请手动把该包写成 `dev-<分支> as <系列>.x-dev`」的提示（`resolveAliasSeries` 返回 null）。免别名的另一条：本地包已声明**当前分支**的 `extra.branch-alias`，报出的版本已可用 → 也是 `*`。
- 其它行为不变：path 仓库**保持宿主 `repositories` 原有的容器形式**（`PathCommand::registerPathRepository`）——顺序数组就写数组项（先按 url 去掉同一目录的旧 path 项，斜杠/尾斜杠/大小写归一后比较），键值对象就按包名写键，完全没有 repositories 时才新建 `{包名: {...}}` 对象；**两种形式都把本次条目排在最前**（数组占第 0 项、对象里包名键排第一），原因见下条。Composer 只接受这两种结构，把整数键改名成 `repo-0` 会生成 `{"repo-0": {"packagist.org": false}}`（无 `type` 又不是布尔），宿主 `composer.json` 立刻被 schema 判非法、composer 自身拒绝启动，必须手工改回数组才能救回来。`minimum-stability: dev` + `prefer-stable: true` 兜底，`type: composer-plugin` 自动进 `allow-plugins`；包未安装→`composer require`（显式带上刚写入的约束，含内联别名）、已安装→`composer update`，`--require` 强制走 require，`--no-update` 只改 `composer.json` 并打印等价手动命令。
- **链接/复制的开关只认 `options.symlink`**（1.2.11 起写在仓库项的 `options` 里，不再写顶层）：`PathRepository` 只把 `repoConfig['options']` 里的 `symlink`/`relative` 拷进 `transport-options`（PathRepository.php:188），`PathDownloader::computeAllowedStrategies()` 据此在 STRATEGY_SYMLINK（Windows `mklink /J` 建 junction、类 Unix `symlink()`）与 STRATEGY_MIRROR（复制）之间切换。**顶层的 `symlink` 全仓无人读取** —— 1.2.10 及以前写的是顶层，所以 `--copy` 从来没生效过（默认建链接只是 Composer 本来就这么做），而 help 里“Windows 需开发者模式/管理员”也是错的：Windows 走 junction，普通权限即可。夹具实测：顶层 `symlink:false` → 日志仍 `Junctioning from`；`options.symlink:false` → `Mirroring from` 且 vendor 是真目录副本。`isIdenticalPathRepo` 因此只承认 `options.symlink`，老宿主里那种顶层条目下次运行会被改写成生效的形式（`stalePathRepoReason` 会打印一行说明）。
- **同名同版本的候选，Composer 取先注册的那条**（夹具 `%TEMP%/xq_order_probe` 实测：两个 path 源给同一个 `dev-main bfa1c01`，谁在 `repositories` 数组里靠前谁就拿到 `Junctioning from`）。宿主的 composer 镜像源常在数组前面、且同样提供 `dev-<分支>`（本地包已推送过，连 reference 都一样），path 条目**追加在末尾就永远输掉这个平局**——2026-11-01 crm 的 `composer require "…:dev-main as 1.0.x-dev"` 明明解析到 `dev-main f1d6c68` 却打印 `Extracting archive`（而非 `Junctioning from`）、vendor 是远端副本，就是这个原因。所以 `registerPathRepository` 改为把本次条目排在最前。抢同一个包的旧 path 条目也会被改写：`stalePathRepoReason` 除了“同一 url”，还会读每个 path 目录里的 `composer.json` 的 `name`，与本次包名同名的旧条目（比如包搬过目录、或历史上指向别处）一并换成新 url 并打印一行说明。重复运行时条目内容完全相同则不打印改动。
- **装完必须验证链接，没链上就跑一条“换来源阶梯”**（`relinkIfNeeded` + `relinkStep`，`--copy` 跳过）：为什么改 `composer.json` 不够 —— 安装动作的依据是 `vendor/composer/installed.json` 与 `composer.lock` 里记的那一条，而内联别名让 path 包报出的版本与宿主已装版本**完全相等**，于是各条命令都判定“已是最新”。阶梯按代价从低到高，每跑一步都用 `vendorPointsToLocal` 复查一次，链上立即返回：① `composer update {包}` —— lock 里还是远端 dist（zip）时这一步就建链；lock 已是 path 而 vendor 是普通目录时它只打印 `Nothing to install, update or remove`，**但它会按本次仓库配置重写 `composer.lock` 的 `transport-options`**（这是④能生效的前提）。② `composer reinstall {包}` —— 跳过解析真重装一次，可用 `applicationHasCommand` 探测（老 Composer 没有这条）。它读的是 `installed.json` 里那份 `transport-options`，上一次以 `--copy` 装过的包仍是 `symlink:false`，于是照旧 `Mirroring from`。③ 包根本不在 `installed.json` 里（`isPackageInstalled` 为假）才 `composer require "包:约束"`。④ **删掉 `vendor/{包}` 再 `composer install`**（`relinkByRemovingVendor`）—— 目录不在了 composer 才发现这个包没装，改按 **lock 里那份新的** `transport-options` 重装，实测 `Installing tmp/dep (dev-main 8bea9a5): Junctioning from {本地目录}`，同时把 `installed.json` 那份旧记录刷新。删除走 Composer 自己的 `Filesystem::remove`，并且只删“`realpath` 解析到自身”的普通目录（复制装的那种）；它若已是指向别处的 junction 就绝不碰（删 junction 有连带删源目录的风险），改为报错交回人工。⑤ 还不成才问是否在本地包做一次空提交（`bumpReference`，`git commit --allow-empty`，走 `ProcessExecutor`；非交互只打印这条命令不代做）换掉 `dist.reference` 再 `composer update`。子命令统一走 `runApplicationCommand`（每次 `resetComposer()` 让子命令重读 composer.json）。
- **为什么不再用 `composer remove` 换来源**（1.2.11 及以前是 `remove + require`，已废弃）：该包被别的包传递依赖时，`remove` 会先自己重解析一轮，两种结局都实测过（夹具 `%TEMP%/xq_crm_probe`、`%TEMP%/xq_alias_probe`、`%TEMP%/xq_ladder3`，形状=path 源排队首＋`tmp/holder` 用 `^1.0` 依赖 `tmp/dep`＋vendor 是普通目录）：① 宿主用的是内联别名，别名随 `require` 行一起被删 → 这轮解析以 `Removal failed, reverting ./composer.json` / `Problem 1 … not installable` 失败并**还原 composer.json**，于是紧接着的 `require` 面对的是一份一字未改的文件，只打印 `Nothing to modify in lock file`；② 别名够用（本地包 `extra.branch-alias`，即 `--local-alias`）→ 包卸完又立刻被依赖方装回来，照样 `Removal failed, … is still present`。两种情况 vendor 都纹丝不动，却先刷一屏红字 —— 这正是“到底链上没”反复报错的来源，所以整条 `remove` 从流程里去掉（连带删掉只为它存在的 `ensureAllowPlugins`）。
- **reference 变化是最后一道杠杆**：夹具 `%TEMP%/xq_crm_probe` 实测 —— vendor 是普通目录时 `composer update tmp/dep` 全程 `Nothing to install, update or remove`；在本地包 `git commit --allow-empty -m sync` 之后再 update，输出 `Upgrading tmp/dep (dev-main af46c18 => dev-main 813fde9): Junctioning from ../pkgDep` 并真的建链。全部阶梯都空转时（例如这个本地包根本不是 git 仓库）报错里的处置顺序：① `composer show {包} -a` 看是不是别的源在供同一个 `dev-<分支>`（日志表现为 `Extracting archive`）② 检查环境变量 `COMPOSER_MIRROR_PATH_REPOS=1`（它对所有 path 源强制复制，日志表现为 `Mirroring from`）③ 已装的是本地包，只是宿主这次想要副本：`--copy` 只改 `options.symlink`，而 composer 对已经链接好的 vendor 目录同样判定“无需改动”，所以**链接→副本这一向本命令不会自动转换**（实测：`--copy` 跑完 `options.symlink=false`、lock 的 `transport-options` 也变 false，vendor 仍是 junction），要副本得手动 `composer reinstall {包}` 或删掉 `vendor/{包}` 再 `composer install`。
- 验证方式是 `realpath(vendor/{包名})` 与本地绝对路径比较（Windows 上 Composer 建的是 **junction**，PHP `is_link()` 认不出来，但 `realpath` 会跟到源目录；复制出来的普通目录只解析到自身）。**比较前必须 `clearstatcache(true)`**：同一个进程里已经查过这个路径，PHP 的 realpath 缓存会一直返回旧结果 —— 夹具 `%TEMP%/xq_e2e` 就因此出现过“日志已打印 `Junctioning from`、脚本末尾再看 vendor 确实是本地目录，命令却报“仍未链接”并以退出码 1 结束”。vendor-dir 取自 `getConfig()->get('vendor-dir')`（`vendorPath()`），不是硬编码 `vendor`。仍不一致才报错，并打印“当前实际指向哪里”与上面三条处置。

## 常见工作流示例

```bash
composer xqkeji:use -- edu                 # 1) 切到 edu 模块
composer xqkeji:form Article -e title,content   # 2) 建表单（自动切表单模式）
composer xqkeji:element cover -c -y=Image       # 3) 单独加一个元素
composer xqkeji:form Article -a                   #    或：向已有表单交互式追加元素
composer xqkeji:form Article -r                   #    或：从已有表单按编号删除元素引用（可 2,5.1 多选）
composer xqkeji:form SearchUser -s -e "SearchKey=username|fullname,like" -e SearchSubmit  # 建搜索表单（内联规格免询问）
composer xqkeji:table Article -e id,title,status,edit_delete  # 4) 建表格（自动建控制器+菜单，并自动派生同名表单）
composer xqkeji:table Article -e id,title,status,edit_delete -F  #    加 -F 则不自动建表单
composer xqkeji:table Article -a                  #    向已有表格追加一列
composer xqkeji:table Article -r                  #    从已有表格按编号删除列引用（~本模块元素无引用时可顺带删元素文件）
composer xqkeji:table Article -e id,title,edit_delete --foot=add,b_delete,export   # 建表同时定制本表底部按钮
composer xqkeji:table Article --foot=export       #    给已有表格加导出按钮（只改 table/element/FootArticle.php）
composer xqkeji:table Article --foot=add,b_delete --no-pager  # 本表底栏不要分页条（生成 ~FootArticle 时写 protected $pager = [];）
```
