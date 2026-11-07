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
创建新模块（本地 app/ 模块或 composer 包模块），建完自动设为当前模块

用法示例：

  # 本地模块 → 当前项目的 app/{模块名}/
  composer xqkeji:module home

  # composer 包模块：包名写法必须带 -p 指定包根目录，创建后自动软链接进 vendor
  composer xqkeji:module xqkeji/xq-app-home -p F:/docker/code/php/

  # -t 中文名写入 menu.php 与 lang/zh_cn.php
  composer xqkeji:module xqkeji/xq-app-edu -p F:/docker/code/php/ -t 教学

要点：

  - 模块名只能是小写字母、数字、下划线；包名会提取模块名（xqkeji/xq-app-home → home）
  - 短选项写法 -t值 / -t 值 / -t=值 / --title=值 等价，选项可放在任意位置
  - 包模块做好后要让它被宿主以本地路径安装，用 xqkeji:path（见该命令帮助）
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
| `--icon` | -i | 需要值 | 否 | 菜单图标类样式（默认按中文名/控制器名关键词自动推荐，如 bi-person） |
| `--file` | -f | 不需要值 | 否 | 创建控制器实体文件（默认不创建，使用虚拟控制器） |

### 帮助 / 示例

```
创建控制器并初始化 acl.php / menu.php / zh_cn.php（默认虚拟控制器，不落地实体文件）

用法示例：

  # admin 入口 + 默认动作集；-t 中文名进菜单与语言文件
  composer xqkeji:controller term -t 学期

  # 菜单图标按名字自动推荐（学生 → bi-mortarboard）；-i 覆盖推荐
  composer xqkeji:controller student -t 学生
  composer xqkeji:controller term -t 学期 -i calendar3

  # 其它入口 / 只放行的动作 / guest 前台入口
  composer xqkeji:controller profile -e member -a login -t 个人中心
  composer xqkeji:controller loger -A admin,delete
  composer xqkeji:controller home -e guest

  # 需要控制器实体文件 controller/{Class}.php 时才加 -f
  composer xqkeji:controller term -t 学期 -f

要点：

  - 默认动作集：admin 管理、add 添加、edit 编辑、delete 删除、change 修改、b_delete 批量删除
  - 入口决定写哪些配置：admin 写 acl+menu+lang；member 等其它入口只写 acl；guest 只写 acl、默认 index 动作、不写菜单
  - -a 只有 auth / login 两个值，对 guest 入口无效；短参数区分大小写：-A 与 -s 都是 --actions，-a 是 --auth
  - 虚拟控制器：acl.php 里有该控制器与动作定义即按约定生效；图标推荐优先级与词表见仓库 AGENTS.md
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
| `--table` | -t | 不需要值 | 否 | 设置为表格模式 |

### 帮助 / 示例

```
切换当前工作上下文（模块 / 控制器 / 模式），多数生成命令依赖它

用法示例：

  composer xqkeji:use                 # 不带参数 = 显示当前上下文
  composer xqkeji:use home            # 切模块（-m 是默认，可省略）
  composer xqkeji:use user_type -c    # 切控制器（user_type / UserType 等价，自动转大驼峰）
  composer xqkeji:use -f              # 表单模式；-t 为表格模式（决定 xqkeji:element 落到哪个目录）

要点：

  - 上下文持久化在 runtime/composer/context.php：当前模块、当前控制器、当前模式（form|table）
  - 切控制器前要先有当前模块；-f/-t/-c 同时给出时只执行一件，顺序是表单模式 → 表格模式 → 控制器
  - xqkeji:form 建完自动切到表单模式，xqkeji:table 建完自动切到表格模式
```

## xqkeji:action

创建动作类

- 源文件：`src/command/ActionCommand.php`

### 位置参数

| 名称 | 必填 | 数组 | 说明 |
| --- | --- | --- | --- |
| `name` | 是 | 否 | 动作名（统一小写，如 add、b_close、change_password） |

### 选项

| 选项 | 短名 | 取值 | 数组 | 说明 |
| --- | --- | --- | --- | --- |
| `--title` | -t | 需要值 | 否 | 动作中文名称（写入语言文件，如：批量删除） |

### 帮助 / 示例

```
创建控制器动作类（动作名与中文名写入语言文件，动作键并入 acl）

用法示例：

  # 动作建在【当前控制器】下，先切模块与控制器
  composer xqkeji:use home
  composer xqkeji:use user_type -c

  composer xqkeji:action add
  composer xqkeji:action b_close -t 批量关闭      # -t 写 title/success/failed/auth 四条语言键
  composer xqkeji:action admin                    # 会交互式问默认排序 $order 与默认查询条件 $conditions

要点：

  - 预定义动作名统一小写、大小写不敏感（add / Add / ADD 等价）：命中后继承
    xqkeji\mvc\action\ 下对应基类并生成【空类体】，行为全由基类提供（不要重写 run()）
  - 未收录的自定义动作名继承 xqkeji\mvc\Action，需自行实现 run()，动作名保持原样不规范化
  - 类文件始终是大驼峰：add → Add.php；名含 _ 或 - 时第一段作子目录（b_close → b/Close.php、change_password → change/Password.php）
  - 预定义动作全表、$order/$conditions 的交互与非交互行为见仓库 AGENTS.md
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
删除 composer 包模块（清掉它在宿主与本地留下的全部痕迹）

用法示例：

  composer xqkeji:remove xqkeji/xq-app-home
  composer xqkeji:remove xqkeji/xq-app-home -p F:/docker/code/php   # 顺带删本地包源码目录（危险操作）
  composer xqkeji:remove xqkeji/xq-app-home --force                 # 常规确认不询问

要点：

  - 清理四处：宿主 composer.json 的 require 与 path repository、config/composer.php 的
    模块映射、vendor 里的软链接（composer remove 只清 require 和 vendor 包本体）
  - 带 -p 时先核对那个目录里 composer.json 的包名，匹配才删目录；不匹配只警告并要求单独确认，这条安全确认 --force 也跳不过
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
创建模型类（当前模块的 model/{Class}.php，继承 xqkeji\mvc\model\Model）

用法示例：

  composer xqkeji:use -- edu
  composer xqkeji:model user_type     # user_type / UserType 等价，自动转大驼峰

要点：

  - 建在【当前模块】的 model/ 目录下，先 xqkeji:use 切到目标模块
  - 产物是 namespace app\{模块}\model 下继承 Model 的【空子类】，字段与方法按框架约定由基类提供
  - 模型名只能字母开头 + 字母/数字/下划线；同名文件已存在会报错，不覆盖
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
| `--element` | -e | 可选值 | 是 | 元素：可多次或逗号分隔（-e Username,Password）；只给 -e 不带值则交互循环逐个添加 |
| `--tab` | -b | 可选值 | 是 | 开启一个 Tab 分组（-b "基本信息"），其后的 -e 元素归入该组 |
| `--global` | -g | 可选值 | 是 | 其后 -e 的元素放在 Tab 之外的全局区（仅 Tab 表单用） |
| `--add` | -a | 不需要值 | 否 | 向【已存在】的表单交互式追加元素：选插入位置后按 xqkeji:element 流程建元素 |
| `--search` | -s | 不需要值 | 否 | 创建搜索表单（继承 SearchForm，GET 提交）：逐个元素问【搜索字段,操作】并生成 xq-s- 数组项；与 -b/-g 互斥 |
| `--remove` | -r | 不需要值 | 否 | 从【已存在】的表单按编号删除元素引用（可逗号分隔如 2,5.1），只动 $el 那一行文本 |

### 帮助 / 示例

```
创建表单类（表单 + 元素），建完自动切到表单模式

用法示例：

  # 表单建在【当前模块】的 form/ 下，先切模块
  composer xqkeji:use -- edu

  # 建表单：-e 可多次使用也可逗号分隔；只给 -e 不带值则交互循环逐个加元素
  composer xqkeji:form User -e Username,Password,Email
  composer xqkeji:form User -e

  # select_ 前缀元素免选类型，生成继承 SelectModel 的空子类（蛇形 select_dept / 驼峰 SelectDept 均可）
  composer xqkeji:form Article -e title,select_dept,SelectStatus

  # Tab 表单（继承 TabForm）：-b 开一个分组，其后的 -e 归它；-g 之后为 Tab 之外的全局元素
  composer xqkeji:form User -b "基本信息" -e username -e password -b "授权信息" -e auth -g -e submit_reset

  # 搜索表单（继承 SearchForm，GET 提交）：会问【搜索字段/操作】，也可 -e "元素=字段,op" 内联免交互
  composer xqkeji:form UserSearch -s -e "SearchKey=username|fullname,like" -e Status,SearchSubmit

  # 对【已存在】的表单：-a 交互追加元素、-r 按编号删引用（2,5.1 = 第 2 条与第 5 个 Tab 内的第 1 条）
  composer xqkeji:form User -a
  composer xqkeji:form User -r

要点：

  - 表单名与元素名自动转大驼峰；元素在 base 已存在引用为 @Name，在本模块为 ~Name
  - 带 -e 时末元素名不含 submit 会询问追加按钮：普通表单 @SubmitReset，搜索表单 -s 为 @SearchSubmit（Tab 表单不处理）
  - -s 的输入元素统一套 '@search' 模板；select_ 元素的默认搜索字段去掉 select_ 再补 _id（select_section → section_id）
  - -s 与 -b/-g 互斥；-r 与 -e/-b/-g/-a/-s 互斥
  - 完整规则（xq-s- 规格与操作符别名、@search 的新建/复用两种写法、Tab 命名、-r 的元素文件清理）见仓库 AGENTS.md
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
| `--element` | -e | 可选值 | 是 | 列元素：可多次或逗号分隔（-e id,Username）；只给 -e 不带值则交互循环逐个添加 |
| `--tree` | -T | 不需要值 | 否 | 创建树形表格（继承 TreegridTable） |
| `--drag` | -D | 不需要值 | 否 | 拖动排序表格：写 $isDrag=true，配套 b_order 与 Admin.php；列含 ordernum 时自动等效 |
| `--no-controller` | -N | 不需要值 | 否 | 只建表格与元素，不建控制器、不写 acl.php/menu.php/zh_cn.php |
| `--controller-file` | -f | 不需要值 | 否 | 生成控制器实体文件 controller/{Class}.php（默认用虚拟控制器，只写 acl/menu/lang）；仅普通表格有效 |
| `--icon` | -i | 需要值 | 否 | 菜单图标（默认按中文名/表名词根自动推荐；bi-person / bi bi-person / person 三种写法等价） |
| `--no-form` | -F | 不需要值 | 否 | 不自动创建同名配套表单（默认会用表格列派生一份 form/{Class}.php） |
| `--add` | -a | 不需要值 | 否 | 向【已存在】的表格交互式追加列：选插入位置后按 xqkeji:element 流程建元素 |
| `--remove` | -r | 不需要值 | 否 | 从【已存在】的表格按编号删除列引用（可逗号分隔一次删多条），只动 $el 那一行文本 |
| `--foot` | - | 可选值 | 否 | 本表底部按钮：--foot=add,b_delete,export（动作名或 base 按钮名均可）；不带值则交互录入 |
| `--no-check-all` | - | 不需要值 | 否 | 本表底栏不要全选框（foot 文件写 protected $checkAll = [];）；需与 --foot 同用 |
| `--no-pager` | - | 不需要值 | 否 | 本表底栏不要分页条（foot 文件写 protected $pager = [];）；需与 --foot 同用 |

### 帮助 / 示例

```
创建表格类（表格 + 列元素），默认同时准备控制器配置与同名配套表单

用法示例：

  # 表格建在【当前模块】的 table/ 下，先切模块
  composer xqkeji:use -- edu

  # 建表：-e 可多次使用也可逗号分隔；只给 -e 不带值则交互循环逐个加列
  composer xqkeji:table User -e id,Username,SwitchCheck,EditDelete
  composer xqkeji:table User -e

  # 树表（TreegridTable）/ 拖动排序表（$isDrag，配套 b_order 动作与 controller/{表}/Admin.php）
  composer xqkeji:table User -T
  composer xqkeji:table User -D -e id,name,ordernum,edit_delete

  # 对【已存在】的表格：-a 交互追加列、-r 按编号删列、-D 只补写 $isDrag
  composer xqkeji:table User -a
  composer xqkeji:table User -r
  composer xqkeji:table User -D

  # 本表专属底部按钮：生成 table/element/FootUser.php，表格 $foot 改为 '~FootUser'
  composer xqkeji:table User --foot=add,b_delete,export
  composer xqkeji:table User --foot=export --no-pager

  # 默认虚拟控制器 + 自动派生同名表单 + 自动推荐菜单图标；按需覆盖
  composer xqkeji:table User -N -F
  composer xqkeji:table User -f -i bi-person

要点：

  - 表名与列名自动转大驼峰（user_list → UserList）；元素在 base 已存在引用为 @Name，在本模块为 ~Name
  - 带 -e 时首列自动置为 Id，末列名不含 delete 时询问追加 @EditDelete
  - select_ 前缀列免选类型，生成继承 ListSelectModel 的空子类
  - -D 与 -T 互斥；-e 列含 ordernum 时未传 -D 也按拖动排序处理
  - 完整规则（默认动作集、b_order/Admin.php、--foot 按钮词表与旧结构迁移、-r 的元素文件清理）见仓库 AGENTS.md
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
| `--create` | -c | 不需要值 | 否 | 创建元素（不传 -c/-e/-r 时默认就是创建） |
| `--edit` | -e | 不需要值 | 否 | 修改已存在的元素 |
| `--remove` | -r | 不需要值 | 否 | 删除元素（会要求确认） |
| `--type` | -y | 可选值 | 否 | 元素类型：-y=Text 直接指定；只给 -y 不带值则弹选择列表 |
| `--list` | -l | 可选值 | 否 | 项目列表（Select/Check/Radio）：值\|文本,值\|文本（也支持 值=文本） |
| `--default` | -D | 可选值 | 否 | 默认值 |
| `--model` | -m | 可选值 | 否 | 模型名（Select/Check/Radio）：项目列表由模型动态加载 |
| `--filters` | -f | 可选值 | 否 | 过滤器（仅表单元素，写 protected $filters）：逗号分隔，带参数用 名称=>参数；不带值则交互输入 |
| `--vt` | -t | 可选值 | 否 | 验证规则（仅表单元素，写 protected $vt）：多条用分号、选项用冒号且写成 名称=值（多个用竖线，值可为数字/true/false）；规则名自动补 $ 前缀；不带值则交互输入 |

### 帮助 / 示例

```
创建、修改或删除单个元素（按当前模式落到 form/element/ 或 table/element/）

用法示例：

  # 元素目录由当前模式决定，先切模式（-f 表单 / -t 表格）
  composer xqkeji:use -f
  composer xqkeji:element Username -c -y=Text
  composer xqkeji:element Username -c -y          # 只给 -y 不带值 = 弹出类型选择列表

  # 选项类元素：-l 项目列表（值|文本 或 值=文本，逗号分隔）、-D 默认值、-m 改由模型动态加载
  composer xqkeji:element Status -c -y=Select -l "1|启用,0|禁用" -D=1
  composer xqkeji:element Roles -c -y=Check -l "1|管理员,2|编辑"
  composer xqkeji:element Status -c -y=Select -m=category_type

  # 过滤器与验证规则（只写 protected $filters / $vt，仅表单元素）
  composer xqkeji:element Nickname -c -f "trim,upper" -t "required;email"
  composer xqkeji:element Intro -c -f "striptags,trim,replace=> |-"   # 带参数的过滤器用 名称=>参数
  composer xqkeji:element Age -c -t "between:min=1|max=99"   # 带选项的验证规则用 规则:名称=值|名称=值
  composer xqkeji:element Intro -c -t "min:min=10|mode=length|included=true"   # $min/$max 默认比字符串长度，mode=number 比数值大小

  # 表格模式下的 select_ 前缀：免选类型、不询问中文名，生成继承 ListSelectModel 的空子类
  composer xqkeji:use -t
  composer xqkeji:element select_dept -c

  # 修改与删除
  composer xqkeji:element Username -e -y=Select
  composer xqkeji:element Username -r

要点：

  - 元素名自动转大驼峰（user_name → UserName）；不传 -c/-e/-r 时默认执行创建
  - 名称转蛇形后以 select_ 开头即走 select 约定：表单侧 SelectModel、表格侧 ListSelectModel 的空子类
  - Select 自动 class => 'form-select'；Check/Radio 自动 template => '@check'
  - -f/-t 在表格模式会提示并忽略；未传时类体不写这两个属性（select_/ListSelectModel 空子类也不写）
  - 可选的过滤器类型全表、$vt 的展开格式与 $min/$max 的选项含义见仓库 AGENTS.md
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
| `--copy` | - | 不需要值 | 否 | 装副本而非链接（写 path 仓库的 options.symlink=false，由 Composer 执行） |
| `--no-update` | - | 不需要值 | 否 | 只改 composer.json，不自动跑 update/require |
| `--require` | - | 不需要值 | 否 | 强制走 composer require（默认自动判定：包未安装→require，已安装→update） |
| `--local-alias` | - | 不需要值 | 否 | 版本别名写进【本地包】的 extra.branch-alias（会改动本地包 composer.json）；默认只写宿主的内联别名 |
| `--no-alias` | - | 不需要值 | 否 | 完全不做版本别名：宿主 require 写 *，也不动本地包 |

### 帮助 / 示例

```
将 composer 包注册为本地路径包（path 仓库），改本地源码即时生效

用法示例：

  # 默认建链接并自动安装/更新（相对路径与绝对路径均可）
  composer xqkeji:path xqkeji/composer F:/docker/code/php/composer
  composer xqkeji:path xqkeji/xq-app-content ../xq-app-content

  # 装副本（改完源码要重跑本命令同步）/ 只改 composer.json / 强制走 require
  composer xqkeji:path xqkeji/composer ../composer --copy
  composer xqkeji:path xqkeji/composer ../composer --no-update
  composer xqkeji:path xqkeji/composer ../composer --require

  # 版本别名的落点：默认写宿主 require 的内联别名；--local-alias 改写入本地包；--no-alias 完全不写
  composer xqkeji:path xqkeji/composer ../composer --local-alias
  composer xqkeji:path xqkeji/composer ../composer --no-alias

要点：

  - 写两处宿主配置：repositories 的 path 条目（本次排在最前，沿用宿主原有的数组/对象形式）
    与 require 约束；并兜底 minimum-stability: dev + prefer-stable: true，
    本地包 type 为 composer-plugin 时自动放行 allow-plugins
  - 建链还是复制完全交给 Composer（只写仓库项的 options.symlink）：
    Windows 建 junction，普通权限即可，不需要开发者模式或管理员
  - 看安装日志判断结果：Junctioning/Symlinking from = 链接建成；
    Mirroring from = 走了复制（查 options.symlink 或环境变量 COMPOSER_MIRROR_PATH_REPOS）；
    Extracting archive = 输给别的源
  - 本地包没有 version 字段时报的是 dev-<分支>，会被其它包的 ^x 稳定约束卡住；
    默认把别名写进【宿主】require 的 "dev-<分支> as <系列>.x-dev"，本地包文件零改动
  - 装完校验 vendor/{包} 是否真指向本地目录，没指向就自动跑换来源阶梯
    （update → reinstall →（包没装才）require → 删 vendor 目录 + install → 空提交换 reference），
    每步复查、链上就返回；不用 composer remove
  - 阶梯每步为什么必要、系列号与分支名的取值顺序见仓库 AGENTS.md
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
反射导出全部 xqkeji:* 命令的定义为 docs/commands.json + docs/commands.md

用法示例：

  composer xqkeji:doc                 # 输出到本插件仓库根的 docs/
  composer xqkeji:doc -o ../docs      # 指定输出目录

要点：

  - 数据取自各命令【活的】 configure()（name/description/arguments/options/help），改完命令重跑一次即刷新，不要手工编辑 docs/
  - 自动过滤 Console 全局选项（--help / --quiet / --verbose / --version / --ansi / --no-ansi / --no-interaction）
  - 默认输出目录由本文件位置定位到插件仓库根，与在哪个工程里运行无关
```

