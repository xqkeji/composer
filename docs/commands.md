# xqkeji 生成器命令清单

> 本文件由 `composer xqkeji:doc` 从各命令 configure() 反射自动生成，请勿手工编辑；改命令后重跑刷新。
> 共 11 个命令。机器可读版见同目录 `commands.json`。

## 命令一览

| 命令 | 说明 | 源文件 |
| --- | --- | --- |
| `xqkeji:module` | 创建模块 | src/command/ModuleCommand.php |
| `xqkeji:controller` | 创建控制器 | src/command/ControllerCommand.php |
| `xqkeji:use` | 切换当前模块、控制器或模式 | src/command/UseCommand.php |
| `xqkeji:action` | 创建动作类 | src/command/ActionCommand.php |
| `xqkeji:remove` | 删除 composer 模块 | src/command/RemoveCommand.php |
| `xqkeji:model` | 创建模型类 | src/command/ModelCommand.php |
| `xqkeji:form` | 创建表单类 | src/command/FormCommand.php |
| `xqkeji:table` | 创建表格类 | src/command/TableCommand.php |
| `xqkeji:element` | 创建、修改或删除表单/表格元素 | src/command/ElementCommand.php |
| `xqkeji:path` | 将 composer 包注册为本地路径包（path 仓库 + symlink） | src/command/PathCommand.php |
| `xqkeji:doc` | 导出全部 xqkeji:* 命令清单为 docs/commands.json 与 docs/commands.md（供 AI/文档快速读懂，改命令后重跑刷新） | src/command/DocCommand.php |

## xqkeji:module

创建模块

- 源文件：`src/command/ModuleCommand.php`

### 位置参数

| 名称 | 必填 | 数组 | 说明 |
| --- | --- | --- | --- |
| `name` | 否 | 否 | 模块名称或包名 (如: home 或 xqkeji/xq-app-home) |

### 选项

| 选项 | 短名 | 取值 | 数组 | 说明 |
| --- | --- | --- | --- | --- |
| `--path` | -p | 需要值 | 否 | 本地路径（创建 composer 包模块时必须指定） |
| `--title` | -t | 需要值 | 否 | 模块中文名称（写入菜单与语言文件） |

### 帮助 / 示例

```
创建新模块

用法示例：

  # 创建本地模块到 app/ 目录
  composer xqkeji:module home

  # 创建 composer 包模块到指定目录（--path / -p 三种写法等价）
  composer xqkeji:module xqkeji/xq-app-home --path=F:/docker/code/php/
  composer xqkeji:module xqkeji/xq-app-home -p=F:/docker/code/php/
  composer xqkeji:module xqkeji/xq-app-home -p F:/docker/code/php/

  # 指定模块中文名：菜单与语言文件写入「教学管理」
  composer xqkeji:module xqkeji/xq-app-edu -p F:/docker/code/php/ -t 教学
  composer xqkeji:module xqkeji/xq-app-edu -p=F:/docker/code/php/ -t=教学

  # 选项可以放在任意位置
  composer xqkeji:module --path=F:/docker/code/php/ xqkeji/xq-app-home

选项：

  -p, --path=PATH    本地路径（创建 composer 包模块时必须指定）
  -t, --title=TITLE  模块中文名称，写入 menu.php 与 lang/zh_cn.php

参数写法（四种等价）：

  composer xqkeji:module xq-app-edu -t=教学
  composer xqkeji:module xq-app-edu -t 教学
  composer xqkeji:module xq-app-edu -t教学
  composer xqkeji:module xq-app-edu --title=教学

说明：

  - 本地模块（如 home）：创建到当前项目的 app/ 目录
  - composer 包模块（如 xqkeji/xq-app-home）：必须指定 --path 参数
  - composer 包模块会在指定路径创建包目录，并自动软链接到 vendor
  - 模块名只能包含小写字母、数字和下划线
  - composer 包模块会从包名提取模块名（xq-app-home → home）
  - 创建后自动设置为当前模块
```

## xqkeji:controller

创建控制器

- 源文件：`src/command/ControllerCommand.php`

### 位置参数

| 名称 | 必填 | 数组 | 说明 |
| --- | --- | --- | --- |
| `name` | 否 | 否 | 控制器名称 |

### 选项

| 选项 | 短名 | 取值 | 数组 | 说明 |
| --- | --- | --- | --- | --- |
| `--entry` | -e | 需要值 | 否 | 权限入口（默认 admin） |
| `--auth` | -a | 需要值 | 否 | 权限类型：auth（需要授权）或 login（需要登录），仅非 guest 入口有效（默认 auth） |
| `--actions` | -A|s | 需要值 | 是 | 动作列表，可多次指定或用逗号分隔（默认 admin,add,edit,delete,change,b_delete） |
| `--title` | -t | 需要值 | 否 | 控制器中文名称（写入菜单与语言文件） |
| `--file` | -f | 不需要值 | 否 | 创建控制器实体文件（默认不创建，使用虚拟控制器） |

### 帮助 / 示例

```
创建控制器并配置权限、菜单和语言

用法：
  composer xqkeji:controller <控制器名> [选项]

选项：
  -e, --entry=ENTRY      权限入口：admin（默认）、member、guest 或自定义
  -a, --auth=AUTH        权限类型：auth（默认，需授权）或 login（需登录），guest 入口忽略
  -A, -s, --actions=ACTIONS  动作列表，可多次指定或用逗号分隔
  -t, --title=TITLE      控制器中文名称，用于菜单标题与语言文件
  -f, --file             生成控制器实体文件（默认不生成，使用虚拟控制器）

默认动作及中文名：
  admin 管理    add 添加    edit 编辑    delete 删除    change 修改    b_delete 批量删除

用法示例：

  # admin 入口，默认动作
  composer xqkeji:controller term

  # 指定中文名：菜单「学期管理」，语言键生成「添加学期」等
  composer xqkeji:controller term -t 学期

  # 自定义入口与权限类型
  composer xqkeji:controller course -e admin -a login
  composer xqkeji:controller profile -e member -a login -t 个人中心

  # 指定动作列表（三种写法等价）
  composer xqkeji:controller loger -A admin -A delete
  composer xqkeji:controller loger -A admin,delete
  composer xqkeji:controller loger -s admin,delete

  # guest 入口（默认 index 动作，不写菜单）
  composer xqkeji:controller home -e guest

  # 同时生成控制器实体文件
  composer xqkeji:controller term -t 学期 -f

参数写法（四种等价）：

  composer xqkeji:controller term -t=学期        # 短参数 + 等号
  composer xqkeji:controller term -t 学期        # 短参数 + 空格
  composer xqkeji:controller term -t学期         # 短参数连写
  composer xqkeji:controller term --title=学期   # 长参数 + 等号

注意：

  - 短参数区分大小写：-A/-s 是 --actions，-a 是 --auth

权限规则：

  - guest 入口：直接把控制器和动作写入 ACL，不区分 auth/login
  - admin 入口：更新 ACL、menu 和 lang 配置
  - 其他入口（member、teacher 等）：只更新 ACL，不处理 menu 和 lang
```

## xqkeji:use

切换当前模块、控制器或模式

- 源文件：`src/command/UseCommand.php`

### 位置参数

| 名称 | 必填 | 数组 | 说明 |
| --- | --- | --- | --- |
| `name` | 否 | 否 | 模块或控制器名称 |

### 选项

| 选项 | 短名 | 取值 | 数组 | 说明 |
| --- | --- | --- | --- | --- |
| `--module` | -m | 不需要值 | 否 | 切换模块（默认） |
| `--controller` | -c | 不需要值 | 否 | 切换控制器 |
| `--form` | -f | 不需要值 | 否 | 设置为表单模式 |
| `--table` | -T | 不需要值 | 否 | 设置为表格模式 |

### 帮助 / 示例

```
切换当前工作上下文（模块、控制器或模式）

用法示例：

  # 切换到模块
  composer xqkeji:use home
  composer xqkeji:use home -m

  # 切换到控制器（支持小写加下划线，自动转换为大驼峰）
  composer xqkeji:use user_type -c
  composer xqkeji:use UserType -c

  # 设置为表单模式（xqkeji:element 创建表单元素）
  composer xqkeji:use -f

  # 设置为表格模式（xqkeji:element 创建表格元素）
  composer xqkeji:use -T

  # 显示当前上下文
  composer xqkeji:use

说明：

  - 不带参数时显示当前上下文
  - 默认切换模块（-m 可省略）
  - 使用 -c 切换控制器
  - 使用 -f 设置为表单模式，-T 设置为表格模式
  - 控制器名称支持小写加下划线（如 user_type），会自动转换为大驼峰命名（UserType）
  - 切换控制器前必须先切换到模块
```

## xqkeji:action

创建动作类

- 源文件：`src/command/ActionCommand.php`

### 位置参数

| 名称 | 必填 | 数组 | 说明 |
| --- | --- | --- | --- |
| `name` | 是 | 否 | 动作名（如 Add、b_close、change_password） |

### 选项

| 选项 | 短名 | 取值 | 数组 | 说明 |
| --- | --- | --- | --- | --- |
| `--title` | -t | 需要值 | 否 | 动作中文名称（写入语言文件，如：批量删除） |

### 帮助 / 示例

```
创建动作类文件

用法示例：

  # 先切换到目标模块和控制器
  composer xqkeji:use home
  composer xqkeji:use user_type -c

  # 在当前控制器下创建预定义动作（自动继承对应基类）
  composer xqkeji:action Add
  composer xqkeji:action Delete
  composer xqkeji:action b_close

  # 指定中文名称，写入 lang/zh_cn.php（四种写法等价）
  composer xqkeji:action b_close -t 批量关闭
  composer xqkeji:action b_close -t=批量关闭
  composer xqkeji:action b_close -t批量关闭
  composer xqkeji:action b_close --title=批量关闭

选项：

  -t, --title=TITLE  动作中文名称，写入语言文件的 title/success/failed/auth 键

预定义动作列表及中文名：

  Add 添加, Admin 管理, Captcha 验证码, Change 修改, Delete 删除, Display 查看,
  Edit 编辑, Emailcode 邮箱验证码, Export 导出, Getoption 获取选项, Login 登录,
  Logout 退出登录, Publish 发布, Reg 注册, Reset 重置, Submenu 子菜单,
  Subnode 子节点, b_close 批量禁用, b_delete 批量删除, b_open 批量启用,
  b_order 批量排序, change_password 修改密码, update_config 更新配置,
  update_statics 更新静态文件

说明：

  - 动作创建在当前上下文指定的控制器下进行，请先使用 xqkeji:use -c 切换控制器
  - 预定义动作继承 xqkeji\mvc\action\ 下对应的动作基类，并重载 run() 方法调用 parent::run()
  - 其他动作名继承 xqkeji\mvc\Action 基类，需自行实现 run() 方法
  - 动作名含 _ 或 - 时，第一部分变为子目录名，其余转为大驼峰作为类名
    例：b_close → b/Close.php，change_password → change/Password.php
```

## xqkeji:remove

删除 composer 模块

- 源文件：`src/command/RemoveCommand.php`

### 位置参数

| 名称 | 必填 | 数组 | 说明 |
| --- | --- | --- | --- |
| `name` | 是 | 否 | 模块名称或包名 |

### 选项

| 选项 | 短名 | 取值 | 数组 | 说明 |
| --- | --- | --- | --- | --- |
| `--path` | -p | 需要值 | 否 | 本地包目录路径（删除后是否清理） |
| `--force` | -f | 不需要值 | 否 | 强制删除，不询问确认 |

### 帮助 / 示例

```
删除 composer 模块

用法示例：

  # 删除 composer 模块（会清理所有相关配置）
  composer xqkeji:remove xqkeji/xq-app-home

  # 删除并清理本地源代码包目录（-p 指定包所在父目录）
  composer xqkeji:remove xqkeji/xq-app-home --path=F:/docker/code/php

  # 强制删除，不询问确认
  composer xqkeji:remove xqkeji/xq-app-home --force

说明：

  - 该命令会完整清理 composer 模块的所有痕迹
  - 清理项目 composer.json 中的 path repository 配置
  - 清理项目 composer.json 中的 require 依赖
  - 清理 config/composer.php 中的模块映射
  - 删除 vendor 中的软链接
  - 如果指定 --path，会验证本地包目录的 composer.json 包名，匹配后删除源代码包目录（危险操作）
  - 包名不匹配时会警告并要求单独确认，--force 无法跳过此安全确认

与 composer remove 的区别：

  - composer remove 只会移除 require 依赖和 vendor 中的包
  - xqkeji:remove 会额外清理 path repository、config/composer.php 和本地目录
```

## xqkeji:model

创建模型类

- 源文件：`src/command/ModelCommand.php`

### 位置参数

| 名称 | 必填 | 数组 | 说明 |
| --- | --- | --- | --- |
| `name` | 否 | 否 | 模型名称 |

### 帮助 / 示例

```
创建模型类

用法示例：

  # 创建模型类
  composer xqkeji:model user
  composer xqkeji:model user_type
  composer xqkeji:model UserType

说明：

  - 模型名称支持大小写，自动转为大驼峰（如 user_type → UserType）
  - 模型类创建在当前模块的 model/ 目录下
  - 继承自 xqkeji\mvc\model\Model 类
  - 需要先使用 xqkeji:use 切换到目标模块
```

## xqkeji:form

创建表单类

- 源文件：`src/command/FormCommand.php`

### 位置参数

| 名称 | 必填 | 数组 | 说明 |
| --- | --- | --- | --- |
| `name` | 否 | 否 | 表单名称 |

### 选项

| 选项 | 短名 | 取值 | 数组 | 说明 |
| --- | --- | --- | --- | --- |
| `--element` | -e | 需要值 | 是 | 表单元素列表（可多次使用，或用逗号分隔：Username,Password） |
| `--tab` | -b | 可选值 | 是 | Tab配置（可多次使用） |
| `--global` | -g | 可选值 | 是 | 全局表单元素（在Tab之外） |
| `--add` | -a | 不需要值 | 否 | 向【已存在】的表单交互式追加元素：先列出现有元素，选择插入位置（某元素之后/最前面），新元素按 xqkeji:element 流程创建（select_ 前缀自动生成 SelectModel 子类） |

### 帮助 / 示例

```
创建表单类和表单元素

用法示例：

  # 创建普通表单（无元素）
  composer xqkeji:form User

  # 创建普通表单（带元素列表）
  composer xqkeji:form User -e Username -e Password -e Email
  composer xqkeji:form User -e Username,Password,Email

  # 元素为 select 类型：蛇形或驼峰写法均可（如 select_dept 或 SelectDept），自动生成 SelectModel 空子类、不询问中文名
  composer xqkeji:form Article -e title,select_dept,select_status
  composer xqkeji:form Article -e title,SelectDept,SelectStatus

  # 创建Tab表单（Tab英文名称自动生成）
  composer xqkeji:form User -b "基本信息" -e Username -e Password -b "授权信息" -e Auth -e Csrf

  # 创建Tab表单（带全局元素）
  composer xqkeji:form User -b "基本信息" -e username -e password -b "授权信息" -e auth -e csrf -g -e submit_reset

  # 创建Tab表单（中文名称不加引号）
  composer xqkeji:form User -b 基本信息 -e username -e password -b 授权信息 -e auth -e csrf

  # 向【已存在】的表单交互式追加元素（先列出现有元素，再选插入位置）
  composer xqkeji:form User -a
  composer xqkeji:form User --add

说明：

  - 表单名支持大小写，自动转为大驼峰（如 user → User、user_login → UserLogin）
  - 表单元素名支持小写加下划线或大驼峰，命令行时可以用小写加_或-的格式
  - 表单类创建在当前模块的 form/ 目录下
  - 表单元素创建在当前模块的 form/element/ 目录下
  - 如果元素在 base 模块已存在，使用 @ElementName 引入
  - 如果元素在当前模块已存在或新创建，使用 ~ElementName 引入
  - 创建新元素时会交互式询问中文名称，已存在的元素不会询问
  - select 元素支持两种写法：蛇形 select_dept 或驼峰 SelectDept（内部统一转蛇形后判断），只要元素名转蛇形后以 select_ 开头即视为 select 元素
  - select 元素（如 select_dept / SelectDept）：自动在当前模块 form/element/ 下创建继承 xqkeji\form\element\SelectModel 的空元素类，类名转大驼峰（select_dept → SelectDept），类体为空、由 SelectModel 提供行为，且不询问中文名；表单中以 ~SelectDept 引用
  - 若同名元素已存在于 base 或当前模块，则直接按 @SelectDept / ~SelectDept 引用，不再重复创建
  - 表单元素通过 -e/--element 指定（可多次使用，也可用逗号分隔：-e Username,Password），元素名自动转为大驼峰
  - -e 值可用引号包裹，引号内逗号分隔支持带空格：-e "User Name, Email"（无引号时逗号后请勿加空格，否则会被 shell 拆成多个参数）
  - 使用 -b/--tab 创建Tab切换效果的表单（继承 TabForm）
  - Tab英文名称自动生成：{表单名小写下划线}_tab{序号}（如 user_tab1、user_tab2）
  - Tab中文名称可加引号也可不加引号
  - 使用 -g/--global 添加Tab之外的全局元素
  - 需要先使用 xqkeji:use 切换到目标模块
  - 使用 -a/--add 向【已存在】的表单追加元素：先显示当前元素列表，输入编号选择在某个元素后插入（0/回车 = 插到第一个元素前面；选中 Tab 分组时会进入该 Tab 内部再选位置），随后按提示输入元素名并按 xqkeji:element 的流程创建（可交互选择类型、Select/Check/Radio 可交互输入项目列表）
  - -a 追加时：元素名转蛇形后以 select_ 开头（如 select_dept）自动生成继承 SelectModel 的空子类且不询问类型；若同名元素已存在于 base 或当前模块则直接按 @/~ 引用，不重复创建；-a 与创建新表单互斥（带 -a 时只插入、不新建表单）
```

## xqkeji:table

创建表格类

- 源文件：`src/command/TableCommand.php`

### 位置参数

| 名称 | 必填 | 数组 | 说明 |
| --- | --- | --- | --- |
| `name` | 否 | 否 | 表格名称 |

### 选项

| 选项 | 短名 | 取值 | 数组 | 说明 |
| --- | --- | --- | --- | --- |
| `--element` | -e | 需要值 | 是 | 表格元素列表（可多次使用，或用逗号分隔：id,Username） |
| `--tree` | -T | 不需要值 | 否 | 创建树形表格（继承 TreegridTable） |
| `--drag` | -D | 不需要值 | 否 | 创建可拖动排序的普通表格（继承 Table，并生成 protected $isDrag = true;；与 -T 互斥，树表忽略该参数） |
| `--no-controller` | -N | 不需要值 | 否 | 仅创建表格（表格类+元素），不创建控制器、不更新 acl.php/menu.php/zh_cn.php；树表同时不创建模型类与集合 |
| `--controller-file` | -f | 不需要值 | 否 | 生成控制器实体文件 controller/{Class}.php（默认不生成，使用虚拟控制器：只初始化 acl/menu/lang，只要 acl.php 有定义即生效）；仅对普通表格有效，树表始终复制动作类文件 |
| `--no-form` | -F | 不需要值 | 否 | 创建表格时【不】自动创建同名配套表单（默认会用表格列去掉 id/时间戳/操作列后追加 submit_reset，自动生成同名表单） |
| `--add` | -a | 不需要值 | 否 | 向【已存在】的表格交互式追加列元素：先列出现有列，选择插入位置（某列之后/最前面），新元素按 xqkeji:element 流程创建 |

### 帮助 / 示例

```
创建表格类和表格元素

用法示例：

  # 创建普通表格（继承 Table）
  composer xqkeji:table User

  # 创建普通表格（带元素列表）
  composer xqkeji:table User -e id -e Username -e SwitchCheck -e LoginTime -e EditDelete
  composer xqkeji:table User -e id,Username,SwitchCheck,LoginTime,EditDelete

  # 创建树形表格（继承 TreegridTable）
  composer xqkeji:table User -T -e id -e Username -e SwitchCheck -e LoginTime -e EditDelete

  # 创建可拖动排序的普通表格（继承 Table，并生成 protected $isDrag = true;）
  composer xqkeji:table User -D -e id,Username,SwitchCheck,LoginTime,EditDelete

  # 创建表格（创建新元素时交互式输入中文名称）
  composer xqkeji:table User -e id,username,switch_check,login_time,edit_delete

  # 仅创建表格（不创建控制器、不更新 acl/menu/lang；树表同时不建模型类与集合）
  composer xqkeji:table User -N
  composer xqkeji:table User -N -e id -e Username
  composer xqkeji:table User -T -N

  # 建表格时自动同时创建同名表单（去掉 id/时间戳/操作列，末尾加 submit_reset）
  composer xqkeji:table course -e id,name,select_dept,select_term,status,ordernum,create_time,edit_delete
  #   → 自动建表单 Course，表单元素为 name, select_dept, select_term, status, ordernum, submit_reset

  # 建表格时【不】自动创建配套表单
  composer xqkeji:table course -e id,name,status,edit_delete -F
  composer xqkeji:table course -e id,name,status,edit_delete --no-form

  # 默认使用虚拟控制器（不生成 controller/{Class}.php，仅初始化 acl/menu/lang）
  composer xqkeji:table course -e id,name,status,edit_delete

  # 需要控制器实体文件时才加 -f/--controller-file（生成 controller/Course.php）
  composer xqkeji:table course -e id,name,status,edit_delete -f
  composer xqkeji:table course -e id,name,status,edit_delete --controller-file

  # 向【已存在】的表格交互式追加列元素（先列出现有列，再选插入位置）
  composer xqkeji:table User -a
  composer xqkeji:table User --add

说明：

  - 表格名支持大小写，自动转为大驼峰（如 user → User、user_list → UserList）
  - 表格元素名支持小写加下划线或大驼峰，命令行时可以用小写加_或-的格式，自动转为大驼峰
  - 表格元素通过 -e/--element 指定（可多次使用，也可用逗号分隔：-e id,Username），创建树表时忽略该参数改用内置默认元素
  - -e 值可用引号包裹，引号内逗号分隔支持带空格：-e "User Name, Login Time"（无引号时逗号后请勿加空格，否则会被 shell 拆成多个参数）
  - 表格类创建在当前模块的 table/ 目录下
  - 表格元素创建在当前模块的 table/element/ 目录下
  - 如果元素在 base 模块已存在，使用 @ElementName 引入
  - 如果元素在当前模块已存在或新创建，使用 ~ElementName 引入
  - 创建新元素时会交互式询问中文名称，已存在的元素不会询问
  - 使用 -T/--tree 创建树形表格（继承 TreegridTable），不使用则继承 Table
  - 使用 -D/--drag 创建可拖动排序的普通表格：仍继承 Table，仅在表格类中额外生成 protected \$isDrag = true;
  - -D 与 -T 互斥：树形表格自带拖拽排序，指定 -T 时忽略 -D
  - 创建树形表格时会自动检查并复制对应的树状控制器动作类（controller/{表格名}/）
  - 使用 -N/--no-controller 仅创建表格（表格类 + 元素），跳过控制器创建与 acl.php/menu.php/zh_cn.php 初始化；树表同时跳过模型类与集合初始化
  - 需要先使用 xqkeji:use 切换到目标模块
  - 使用 -a/--add 向【已存在】的表格追加列元素：先显示当前列列表，输入编号选择在某列后插入（0/回车 = 插到第一列前面），随后按提示输入元素名并按 xqkeji:element 的流程创建（表格模式，可交互选择类型）；若同名元素已存在于 base 或当前模块则直接按 @/~ 引用，不重复创建；-a 与创建新表格互斥（带 -a 时只插入、不新建表格、不涉及控制器/acl/menu/lang）
  - 【默认】建表格时会用 -e 传入的表格列自动派生并创建一个同名表单（form/{大驼峰}.php 及所需 form/element/）：从表格列中剔除“仅表格”元素 id、create_time、create_date、update_time、update_date、edit_delete、delete、view_delete（按蛇形名匹配），剩余列作为表单元素，末尾追加 submit_reset（base 模块的提交/重置按钮，引用为 @SubmitReset）；表单与表格同名并共享控制器与中文名，元素中文名在建表时已写入 lang，建表单过程一般不再重复询问；select_ 前缀列照常生成 SelectModel 子类
  - 使用 -F/--no-form 关闭上述自动建表单行为，仅创建表格；当 -e 未传列、或表格列全部是“仅表格”元素时，本就不会生成表单（无可用表单列）；-N（仅建表不建控制器）不影响该自动建表单逻辑，如需两者都跳过可同时使用 -N -F
  - 控制器默认使用【虚拟控制器】：普通表格不会生成 controller/{Class}.php 实体文件，仅初始化 acl.php/menu.php/zh_cn.php；只要 acl.php 里有该控制器与动作的定义，框架即按约定解析动作、控制器即“存在”。需要实体文件时加 -f/--controller-file（等价 xqkeji:controller 的 -f/--file 语义）；该参数仅对普通表格生效，树状表格仍会复制 controller/{表名}/ 下的动作类文件（admin/add/move 等）
```

## xqkeji:element

创建、修改或删除表单/表格元素

- 源文件：`src/command/ElementCommand.php`

### 位置参数

| 名称 | 必填 | 数组 | 说明 |
| --- | --- | --- | --- |
| `name` | 否 | 否 | 元素名称 |

### 选项

| 选项 | 短名 | 取值 | 数组 | 说明 |
| --- | --- | --- | --- | --- |
| `--create` | -c | 不需要值 | 否 | 创建元素 |
| `--edit` | -e | 不需要值 | 否 | 修改元素 |
| `--remove` | -r | 不需要值 | 否 | 删除元素 |
| `--type` | -y | 可选值 | 否 | 指定元素类型（与 -c/-e 配合），不带值则弹出选择列表 |
| `--list` | -l | 可选值 | 否 | 项目列表（Select/Check/Radio），格式：值1\|文本1,值2\|文本2 |
| `--default` | -D | 可选值 | 否 | 默认值 |
| `--model` | -m | 可选值 | 否 | 模型名（Select/Check/Radio），从模型动态加载项目列表 |

### 帮助 / 示例

```
创建、修改或删除表单/表格元素（根据当前模式决定）

用法示例：

  # 创建元素（默认操作，使用默认类型）
  composer xqkeji:element Username

  # 创建元素（指定类型）
  composer xqkeji:element Username -c -y=Text
  composer xqkeji:element Username -c -y Text

  # 创建元素（-y 不带值，弹出类型选择列表）
  composer xqkeji:element Username -c -y

  # 创建 Select 元素（带项目列表和默认值，| 分隔符）
  composer xqkeji:element Status -c -y=Select -l "1|启用,0|禁用" -D=1

  # 创建 Select 元素（= 分隔符）
  composer xqkeji:element Status -c -y=Select -l "1=启用,0=禁用" -D=1

  # 创建 Check 元素（带项目列表）
  composer xqkeji:element Roles -c -y=Check -l "1|管理员,2|编辑,3|用户"

  # 创建 Radio 元素（带项目列表和默认值）
  composer xqkeji:element Gender -c -y=Radio -l "1|男,2|女" -D=1

  # 创建 Select 元素（从模型动态加载项目列表）
  composer xqkeji:element Status -c -y=Select -m=category_type

  # 修改元素（指定类型）
  composer xqkeji:element Username -e -y=Select

  # 修改元素（-y 不带值，弹出类型选择列表）
  composer xqkeji:element Username -e -y

  # 删除元素
  composer xqkeji:element Username -r

说明：

  - 元素名称支持大小写，自动转为大驼峰（如 username → Username、user_name → UserName）
  - 表单元素创建在当前模块的 form/element/ 目录下
  - 表格元素创建在当前模块的 table/element/ 目录下
  - 创建/修改前需要先使用 xqkeji:use -f（表单模式）或 -T（表格模式）设置当前模式
  - 不指定 -c/-e/-r 时，默认为创建操作
  - 使用 -y 不带值会强制弹出类型选择列表（交互模式）
  - 使用 -y=类型名 直接指定类型
  - 使用 -l 指定项目列表（Select/Check/Radio 类型），格式：值1|文本1,值2|文本2 或 值1=文本1,值2=文本2
  - 使用 -D 指定默认值
  - 使用 -m 指定模型名（Select/Check/Radio），从模型动态加载项目列表（需交互设置字段名）
  - Select 类型自动使用 class => 'form-select'
  - Check/Radio 类型自动使用 template => '@check'
  - 删除操作会要求确认
```

## xqkeji:path

将 composer 包注册为本地路径包（path 仓库 + symlink）

- 源文件：`src/command/PathCommand.php`

### 位置参数

| 名称 | 必填 | 数组 | 说明 |
| --- | --- | --- | --- |
| `package` | 是 | 否 | 包名（如 xqkeji/composer） |
| `path` | 是 | 否 | 本地路径（包根目录，需包含 composer.json） |

### 选项

| 选项 | 短名 | 取值 | 数组 | 说明 |
| --- | --- | --- | --- | --- |
| `--copy` | - | 不需要值 | 否 | 使用复制而非符号链接（symlink=false），适合无法创建符号链接的环境 |
| `--no-update` | - | 不需要值 | 否 | 仅修改 composer.json，不自动运行 composer update/require |
| `--require` | - | 不需要值 | 否 | 强制使用 composer require 安装（默认自动判定：包未安装→require 走完整安装事件流，包已存在→update 转本地路径包） |
| `--no-alias` | - | 不需要值 | 否 | 不自动为本地 dev 分支包写入 branch-alias（默认会自动，使 path 仓库版本满足稳定约束） |

### 帮助 / 示例

```
将一个 composer 包注册为本地路径包（本地开发/联调用）

用法示例：

  # 将本地包注册为 path 仓库（默认符号链接，自动 composer update）
  composer xqkeji:path xqkeji/composer ../composer

  # 指定绝对路径
  composer xqkeji:path xqkeji/composer F:/docker/code/php/composer

  # 使用复制而非符号链接（Windows 无开发者模式/无管理员权限时）
  composer xqkeji:path xqkeji/composer ../composer --copy

  # 仅修改 composer.json，不自动更新（之后手动运行 composer update/require 包名）
  composer xqkeji:path xqkeji/composer ../composer --no-update

  # 包尚未安装：自动改用 composer require 安装（触发与 require 相同的 post-package-install 等事件/插件激活）
  composer xqkeji:path xqkeji/xq-app-content ../xq-app-content

  # 强制走 composer require（即便包已存在也重新按 require 流程处理）
  composer xqkeji:path xqkeji/composer ../composer --require

说明：

  - 在当前项目 composer.json 中追加 path 仓库（url=本地路径，symlink=true 默认）
  - 同时在 require 中追加 "包名": "*"（若已存在则更新）
  - 自动确保 minimum-stability: dev 与 prefer-stable: true（dev 分支可解析）
  - 若本地包 type 为 composer-plugin，自动在 allow-plugins 中放行该包
  - 已存在同名 path 仓库则覆盖更新（幂等）
  - 自动选择安装方式：包【未安装】→ 运行 composer require 包名（安装新包，触发 composer require 的完整事件流，含模块包的 post-package-install 钩子与插件激活）；包【已安装】→ 运行 composer update 包名（把已存在的包转为本地路径包，触发 update 事件）；--require 可强制按 require 流程
  - 默认自动运行上述 update/require；--no-update 可跳过
  - 符号链接(symlink)下本地源码改动即时生效；Windows 需开启开发者模式或以管理员运行，否则请用 --copy
  - 若本地包 composer.json 无 version 字段（典型 dev 分支），默认自动在其 extra.branch-alias.dev-<分支> 写入 <系列>.x-dev（如 dev-main→1.2.x-dev），使 path 仓库版本满足依赖的 ^x 稳定约束，避免“canonical repo 无法解析”；--no-alias 可跳过
  - branch-alias 系列号优先级：① 该包已发布/已安装的最新稳定版本（与 composer 最新版本直接对应，无需 git）② 本地 git 最新 tag ③ 交互输入；分支名取自本地 git HEAD（无 git 时提示，默认 main）。每次运行会按最新版本自动推进系列号（如 1.2.x-dev→1.3.x-dev）
```

## xqkeji:doc

导出全部 xqkeji:* 命令清单为 docs/commands.json 与 docs/commands.md（供 AI/文档快速读懂，改命令后重跑刷新）

- 源文件：`src/command/DocCommand.php`

### 选项

| 选项 | 短名 | 取值 | 数组 | 说明 |
| --- | --- | --- | --- | --- |
| `--output-dir` | -o | 可选值 | 否 | 输出目录（默认为插件仓库根下的 docs/） |

### 帮助 / 示例

```
反射导出全部命令的结构化清单，方便 AI 与文档快速读懂本生成器。

用法示例：

  # 生成 docs/commands.json + docs/commands.md（默认输出到插件仓库根的 docs/）
  composer xqkeji:doc

  # 指定输出目录
  composer xqkeji:doc -o ../docs
  composer xqkeji:doc --output-dir=/path/to/dir

说明：

  - 清单数据直接来自各命令 configure() 中的 setName/setDescription/addArgument/addOption/setHelp，
    新增选项或修改说明后重新运行本命令即可刷新，文档不会与代码脱节。
  - commands.json：结构化（name/description/source/arguments/options/help），供程序与 AI 读取。
  - commands.md：同一数据的可读版，含参数表与每个命令的完整 help。
  - 会自动过滤 Console 内置全局选项（--help/--quiet/--verbose/--version/--ansi/--no-ansi/--no-interaction）。
  - 默认输出到插件仓库自身根目录（本命令文件所在 src 的上一级）的 docs/，与在哪个工程里运行无关。
```

