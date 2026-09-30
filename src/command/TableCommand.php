<?php
namespace xqkeji\composer\command;

use Composer\Command\BaseCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use xqkeji\composer\Table;

class TableCommand extends BaseCommand
{
    use NormalizesShortOptions;
    use ElementLoopTrait;

    protected function configure()
    {
        $this->setName('xqkeji:table')
            ->setDescription('创建表格类')
            ->addArgument('name', InputArgument::OPTIONAL, '表格名称')
            ->addOption('element', 'e', InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, '表格元素列表（可多次使用，或用逗号分隔：id,Username）；只传 -e 不带任何元素值时进入交互循环逐个添加')
            ->addOption('tree', 'T', InputOption::VALUE_NONE, '创建树形表格（继承 TreegridTable）')
            ->addOption('drag', 'D', InputOption::VALUE_NONE, '创建可拖动排序的普通表格（继承 Table，并生成 protected $isDrag = true;；与 -T 互斥，树表忽略该参数）；若表格【已存在】则不新建，直接为该表格类补写 protected $isDrag = true;（幂等，可单独执行）。创建时未传 -D 但最终列中含 ordernum 元素也会自动按拖动排序处理（等效 -D，无需再设置一次）。创建时：元素中没有 ordernum 元素且其他有效列（非 ordernum/Id/含 delete）≥2 个 → 询问是否自动添加 @Ordernum 作为最后一个数据列（@EditDelete 之前）；-D 且元素有 ordernum 时（两者同时具备才判断）acl.php 动作集加入 b_order 并更新 zh_cn.php，同时复制模板生成控制器目录 controller/{表名蛇形}/Admin.php（继承 xqkeji\mvc\action\Admin，protected $order = [ordernum => asc] 默认排序，已存在则跳过不覆盖）')
            ->addOption('no-controller', 'N', InputOption::VALUE_NONE, '仅创建表格（表格类+元素），不创建控制器、不更新 acl.php/menu.php/zh_cn.php；树表同时不创建模型类与集合')
            ->addOption('controller-file', 'f', InputOption::VALUE_NONE, '生成控制器实体文件 controller/{Class}.php（默认不生成，使用虚拟控制器：只初始化 acl/menu/lang，只要 acl.php 有定义即生效）；仅对普通表格有效，树表始终复制动作类文件')
            ->addOption('no-form', 'F', InputOption::VALUE_NONE, '创建表格时【不】自动创建同名配套表单（默认会用表格列去掉 id/时间戳/操作列后追加 submit_reset，自动生成同名表单）')
            ->addOption('add', 'a', InputOption::VALUE_NONE, '向【已存在】的表格交互式追加列元素：先列出现有列，选择插入位置（某列之后/最前面），新元素按 xqkeji:element 流程创建')
            ->addOption('remove', 'r', InputOption::VALUE_NONE, '从【已存在】的表格删除列元素引用：列出 protected $el 现有列，输入逗号分隔的多个编号（如 3 或 2,5）一次删除多条；按整行文本方式删除引用行，文件其余内容与手工编辑（$foot、注释等）保留；被删的 ~本模块元素若在本模块所有表单/表格中再无引用，会询问是否顺带删除其元素类文件（默认否，@base 元素永不删）；与创建/追加/--foot 互斥，不碰 acl/menu/lang')
            ->addOption('foot', null, InputOption::VALUE_OPTIONAL, "表格底部操作栏按钮：值为逗号分隔的【动作名】或【base 按钮元素名】（如 --foot=add,b_delete,export 或 --foot=AddButton,BDeleteButton,export，元素名大小写不敏感、可带 @，等价于对应动作名）；只带 --foot 不带值则交互式逐个录入。会为本表生成模块本地 foot 元素类 table/element/Foot{表}.php（继承 ListFoot，只声明 protected \$buttons，壳结构由 ListFoot 渲染）并把表格 \$foot 改为 '~Foot{表}'，按钮的 name 即控制器动作、自动并入 acl.php 与 zh_cn.php；add/b_delete/export 引用 base 已有按钮元素 @AddButton/@BDeleteButton/@ExportButton，其余动作在 \$buttons 里内联 \$Button 条目；对【已存在】的表格只把缺失的按钮插入其 \$buttons 数组（幂等，不覆盖手工内容），旧版内联三级结构（没有 \$buttons）会按本次列表整体重写；树表不支持该参数")
            ->addOption('no-check-all', null, InputOption::VALUE_NONE, '本表底部操作栏【不要全选框】：生成的 foot 文件里写 protected $checkAll = [];（ListFoot 默认渲染表头全选，置空即关闭）；需与 --foot 同用')
            ->addOption('no-pager', null, InputOption::VALUE_NONE, '本表底部操作栏【不要分页条】：生成的 foot 文件里写 protected $pager = [];（ListFoot 默认渲染 @Pager/@PageSize，置空即关闭）；需与 --foot 同用')
            ->setHelp(<<<'EOF'
创建表格类和表格元素

<info>用法示例：</info>

  <comment># 创建普通表格（继承 Table）</comment>
  composer xqkeji:table User

  <comment># 创建普通表格（带元素列表）</comment>
  composer xqkeji:table User -e id -e Username -e SwitchCheck -e LoginTime -e EditDelete
  composer xqkeji:table User -e id,Username,SwitchCheck,LoginTime,EditDelete

  <comment># 只传 -e 不带元素值：进入交互循环逐个添加列（结束后自动补首列 Id，并询问末尾 @EditDelete）</comment>
  composer xqkeji:table User -e

  <comment># 创建树形表格（继承 TreegridTable）</comment>
  composer xqkeji:table User -T -e id -e Username -e SwitchCheck -e LoginTime -e EditDelete

  <comment># 创建可拖动排序的普通表格（继承 Table，并生成 protected $isDrag = true;）</comment>
  composer xqkeji:table User -D -e id,Username,SwitchCheck,LoginTime,EditDelete

  <comment># 给【已存在】的表格补加拖动排序（表格类存在时 -D 不新建，只在类中补写 protected $isDrag = true;）</comment>
  composer xqkeji:table User -D

  <comment># 创建表格（创建新元素时交互式输入中文名称）</comment>
  composer xqkeji:table User -e id,username,switch_check,login_time,edit_delete

  <comment># 仅创建表格（不创建控制器、不更新 acl/menu/lang；树表同时不建模型类与集合）</comment>
  composer xqkeji:table User -N
  composer xqkeji:table User -N -e id -e Username
  composer xqkeji:table User -T -N

  <comment># 建表格时自动同时创建同名表单（去掉 id/时间戳/操作列，末尾加 submit_reset）</comment>
  composer xqkeji:table course -e id,name,select_dept,select_term,status,ordernum,create_time,edit_delete
  <comment>#   → 自动建表单 Course，表单元素为 name, select_dept, select_term, status, ordernum, submit_reset</comment>

  <comment># 建表格时【不】自动创建配套表单</comment>
  composer xqkeji:table course -e id,name,status,edit_delete -F
  composer xqkeji:table course -e id,name,status,edit_delete --no-form

  <comment># 默认使用虚拟控制器（不生成 controller/{Class}.php，仅初始化 acl/menu/lang）</comment>
  composer xqkeji:table course -e id,name,status,edit_delete

  <comment># 需要控制器实体文件时才加 -f/--controller-file（生成 controller/Course.php）</comment>
  composer xqkeji:table course -e id,name,status,edit_delete -f
  composer xqkeji:table course -e id,name,status,edit_delete --controller-file

  <comment># 向【已存在】的表格交互式追加列元素（先列出现有列，再选插入位置）</comment>
  composer xqkeji:table User -a
  composer xqkeji:table User --add

  <comment># 从【已存在】的表格删除列元素引用（列出编号，输入逗号分隔的多个编号一次删多条）</comment>
  composer xqkeji:table User -r
  composer xqkeji:table User --remove
  <comment>#   列出后输入 3 → 删第 3 列；输入 2,6 → 同时删第 2 列与第 6 列</comment>
  <comment>#   若被删的是 ~本模块元素且本模块已无任何表单/表格引用它，会询问是否顺带删除元素类文件（默认否）</comment>

  <comment># 建表时指定底部操作栏按钮（按钮 name 即控制器动作，自动并入 acl.php 与 zh_cn.php）</comment>
  composer xqkeji:table course -e id,name,select_dept,edit_delete --foot=add,b_delete,export
  <comment>#   → 生成模块本地 table/element/FootCourse.php（继承 ListFoot，只声明 protected $buttons = ['@AddButton','@BDeleteButton','@ExportButton'];），表格 $foot = '~FootCourse'</comment>

  <comment># 自定义/批量动作没有 base 按钮元素时，在 $buttons 里内联 $Button 条目（文案与前端行为类会询问）</comment>
  composer xqkeji:table course --foot=b_close

  <comment># 给【已存在】的表格加按钮：只把缺失的条目插入该表 foot 文件的 $buttons 数组，其余手工内容不动</comment>
  composer xqkeji:table course --foot=export

  <comment># 不要全选框 / 不要分页条：为本表 foot 写 protected $checkAll = []; 或 protected $pager = [];</comment>
  composer xqkeji:table course --foot=add,b_delete --no-check-all
  composer xqkeji:table course --foot=export --no-pager

  <comment># --foot 不带值：交互式循环逐个录入按钮动作（留空结束）</comment>
  composer xqkeji:table course --foot

  <comment># 迁移旧版内联三级 foot（没有 $buttons）：按给出的完整按钮列表整体重写为新结构</comment>
  composer xqkeji:table course --foot=AddButton,BDeleteButton,export

<info>说明：</info>

  - 表格名支持大小写，自动转为大驼峰（如 user → User、user_list → UserList）
  - 表格元素名支持小写加下划线或大驼峰，命令行时可以用小写加_或-的格式，自动转为大驼峰
  - 表格元素通过 -e/--element 指定（可多次使用，也可用逗号分隔：-e id,Username），创建树表时忽略该参数改用内置默认元素
  - 只传 -e 不带任何元素值（如 composer xqkeji:table User -e）：进入交互式循环添加模式，每次询问一个列名称（留空结束），加入后询问是否继续添加下一个；循环结束后执行首尾规范化（首列自动置为 Id，末列不含 delete 时询问追加 @EditDelete）；--no-interaction 时报错退出；树表 -T 不支持该模式
  - -e 值可用引号包裹，引号内逗号分隔支持带空格：-e "User Name, Login Time"（无引号时逗号后请勿加空格，否则会被 shell 拆成多个参数）
  - 带了 -e 时首尾列自动规范化（元素先统一解析为 @/~ 引用再判断）：【首列】必须是主键 Id——列表已含 Id 但不在首位则自动移到首位，完全不含则自动按 xqkeji:element 流程创建/复用 Id 并插到首位（无需询问）；【末列】最后一个元素名不含 delete 时，交互式询问是否自动追加操作列 @EditDelete（默认追加；非交互模式直接追加并提示）。树表 -T 用内置默认元素（首 @Id 尾 ~EditDelete{表}），不受此逻辑影响
  - 表格类创建在当前模块的 table/ 目录下
  - 表格元素创建在当前模块的 table/element/ 目录下
  - 如果元素在 base 模块已存在，使用 @ElementName 引入
  - 如果元素在当前模块已存在或新创建，使用 ~ElementName 引入
  - 创建新元素时会交互式询问中文名称，已存在的元素不会询问
  - select_ 前缀列约定（与表单侧对称）：-e 列名转蛇形后以 select_ 开头（如 select_dept / SelectDept）时，免选类型、不询问中文名，直接生成继承 xqkeji\form\element\ListSelectModel 的【空子类】（无 \$name/\$text/\$attrs 属性，行为由基类提供），表格中以 ~SelectDept 引用；-a 追加 select_ 列同样适用
  - 使用 -T/--tree 创建树形表格（继承 TreegridTable），不使用则继承 Table
  - 使用 -D/--drag 创建可拖动排序的普通表格：仍继承 Table，仅在表格类中额外生成 protected \$isDrag = true;
  - 自动等效 -D：创建时未传 -D，只要 -e 最终列中含有名称 ordernum 的元素（如 @Ordernum / ~OrdernumXxx），即自动按拖动排序表格处理（表格类同样写入 protected \$isDrag = true;，与下方 b_order/Admin.php 链路同开同关），无需再补传 -D 设置一次
  - -D 创建时的序号列（ordernum）规则：拖拽排序前端依赖 ordernum 序号列（向 /b-order 提交新顺序）。若 -e 列中【没有任何】名称含 ordernum 的元素、且其他有效列（排除 ordernum/Id/含 delete 的操作列）≥ 2 个，则**交互式询问**是否自动添加 '@Ordernum'（base 模块元素）作为最后一个数据列：末列已是删除类操作列时插在其前（@EditDelete 之前），否则插到末尾（随后仍走 @EditDelete 询问追加）；非交互模式默认添加并提示；已有 ordernum 列（如 ~OrdernumXxx / @Ordernum）则不询问、保持原位不动
  - -D 且最终列中【存在】ordernum 序号列时（-D 与 ordernum 两者同时具备才判断生效），控制器动作集自动追加 'b_order'：写入 acl.php（add/edit/admin/delete/change/b_delete/b_order），并在 zh_cn.php 生成批量排序文案（'{模块} {控制器} b_order title/success/failed'、'{模块} module {控制器} b_order auth'，中文名“批量排序{控制器}”）；-N（不建控制器）时不涉及 acl/lang
  - 同上条件（-D + ordernum 列）还会复制插件模板 src/example/src/controller/order/Admin.php 到控制器目录 controller/{表名全小写蛇形}/Admin.php：继承 xqkeji\mvc\action\Admin、protected \$order=['ordernum'=>'asc'];，使拖拽排序表的列表默认按序号升序；占位符 {MODULE_NAME}/{CONTROLLER_NAME} 自动替换；目标文件已存在则跳过（不覆盖手工修改）；该动作类目录与虚拟控制器并存（-f 实体文件仍按 -f 规则生成）
  - 表格类【已存在】时执行 -D 不会新建表格，而是直接为已有表格类补写拖动参数（幂等）：无 \$isDrag 则在 \$el 属性前插入 protected \$isDrag = true;；\$isDrag = false 则改为 true；已是 true 则跳过。可只传表名 + -D 单独执行，不需要 -e；树状表格（TreegridTable）自带拖拽，执行 -D 会提示并跳过
  - -D 与 -T 互斥：树形表格自带拖拽排序，指定 -T 时忽略 -D
  - 创建树形表格时会自动检查并复制对应的树状控制器动作类（controller/{表格名}/）
  - 使用 -N/--no-controller 仅创建表格（表格类 + 元素），跳过控制器创建与 acl.php/menu.php/zh_cn.php 初始化；树表同时跳过模型类与集合初始化
  - 需要先使用 xqkeji:use 切换到目标模块
  - 使用 -a/--add 向【已存在】的表格追加列元素：先显示当前列列表，输入编号选择在某列后插入（0/回车 = 插到第一列前面），随后按提示输入元素名并按 xqkeji:element 的流程创建（表格模式，可交互选择类型）；若同名元素已存在于 base 或当前模块则直接按 @/~ 引用，不重复创建；-a 与创建新表格互斥（带 -a 时只插入、不新建表格、不涉及控制器/acl/menu/lang）
  - 使用 -r/--remove 从【已存在】的表格删除列元素引用：先按编号列出 protected \$el 的现有列，随后输入逗号分隔的多个编号一次删除多条（如 3 或 2,6；留空取消）。删除是按【整行文本区间】移除引用行，表格类的其它属性（\$foot、\$isDrag）、注释与手工编辑原样保留；不会顺带改动表单文件（同名配套表单需单独执行 xqkeji:form -r）
  - -r 只动表格文件的 \$el，不碰 acl.php/menu.php/zh_cn.php，也不会反向删除控制器/元素；-r 与创建、追加、--foot 互斥（不能与 -e/-T/-D/--foot/-a 同用）；树表同样支持（删除其内置默认列引用）
  - -r 的元素类文件清理是可选项：被删引用若为 ~本模块元素，会在当前模块的 form/*.php 与 table/*.php 里复查该元素是否还有引用（表格的 \$el 与 \$foot 都算），仍被引用则保留并提示；确认无任何引用时才询问【是否顺带删除该元素类文件】，默认不删；@base 元素（如 @Id、@EditDelete）属于 base 模块，永不删除
  - 【默认】建表格时会用 -e 传入的表格列自动派生并创建一个同名表单（form/{大驼峰}.php 及所需 form/element/）：从表格列中剔除“仅表格”元素 id、create_time、create_date、update_time、update_date、edit_delete、delete、view_delete（按蛇形名匹配），剩余列作为表单元素，末尾追加 submit_reset（base 模块的提交/重置按钮，引用为 @SubmitReset）；表单与表格同名并共享控制器与中文名，元素中文名在建表时已写入 lang，建表单过程一般不再重复询问；select_ 前缀列照常生成 SelectModel 子类
  - 使用 -F/--no-form 关闭上述自动建表单行为，仅创建表格；当 -e 未传列、或表格列全部是“仅表格”元素时，本就不会生成表单（无可用表单列）；-N（仅建表不建控制器）不影响该自动建表单逻辑，如需两者都跳过可同时使用 -N -F
  - 控制器默认使用【虚拟控制器】：普通表格不会生成 controller/{Class}.php 实体文件，仅初始化 acl.php/menu.php/zh_cn.php；只要 acl.php 里有该控制器与动作的定义，框架即按约定解析动作、控制器即“存在”。需要实体文件时加 -f/--controller-file（等价 xqkeji:controller 的 -f/--file 语义）；该参数仅对普通表格生效，树状表格仍会复制 controller/{表名}/ 下的动作类文件（admin/add/move 等）
  - 普通表格写入 acl.php/zh_cn.php 的【默认动作集】为 add/edit/admin/delete/change/b_delete（与 xqkeji:controller 默认一致）：b_delete 必带，因为 base 的 @Foot 底栏默认渲染「添加 + 删除」两个按钮（ListFoot 的默认 buttons 为 @AddButton + @BDeleteButton），前端 xq-batch 会把选中行 POST 到 …/{控制器}/b_delete，acl 不放行该动作则按钮点了无效；-D+ordernum 时再追加 b_order，--foot 时把按钮动作并入同一集合
  - 使用 --foot=add,b_delete,export 定制本表【底部操作栏按钮】：条目既可以是【动作名】（统一小写，与 xqkeji:action 一致），也可以直接写 base 按钮元素名（AddButton / BDeleteButton / ExportButton，大小写不敏感、可带 @，等价于 add / b_delete / export），按钮的 name 就是控制器动作（前端 xq-batch 会把选中行 POST 到 …/{controller}/{name}），因此生成器会自动把动作并入 acl.php 并在 zh_cn.php 补 title/success/failed/auth 文案。内置词表默认文案与样式：add 添加(btn-primary xq-add)、b_delete 删除(btn-danger xq-batch)、b_open 启用(btn-success xq-batch)、b_close 禁用(btn-secondary xq-batch)、b_order 排序(btn-info xq-batch)、export 导出(btn-warning xq-export)；未收录的动作名会询问【按钮文案】与【前端行为类】（非交互按 动作名 + xq-batch 处理）；base 的操作列小按钮 EditButton/ViewButton/DeleteButton（btn-sm，由 ~EditDelete 列使用）会被拒绝
  - --foot 的落地方式：ListFoot 自己渲染底栏壳子（tfoot → 全选 td → d-flex 容器 → me-auto 按钮组 → 分页条），子类只声明按钮即可，因此本表的 foot 文件是【模块本地】的 table/element/Foot{表名大驼峰}.php，内容为 class Foot{表} extends ListFoot + protected $name = 'list_foot' + protected $buttons = [...]，表格类 $foot 改为 '~Foot{表名}'，此后这张表加/减按钮只动这一个文件、也只影响这张表（不再改 base 的共享按钮元素）
  - $buttons 的两种条目：add / b_delete / export 在 base 已有按钮元素，直接引用为 '@AddButton' / '@BDeleteButton' / '@ExportButton'；b_open、b_close 与自定义动作没有 base 按钮，生成器在 $buttons 里内联 ['$Button','name'=>动作,'attrs'=>['value'=>文案,'class'=>'btn btn-X me-1 行为类']] 条目
  - 使用 --no-check-all / --no-pager 关掉本表底栏的【全选框】/【分页条】：分别在 foot 文件里写 protected $checkAll = []; 与 protected $pager = [];（ListFoot 只有在子类显式置空时才不渲染这两块，不写就用框架默认）；两者都需与 --foot 同用（要先有本表 foot 文件），对已存在的 foot 文件是幂等的补写/改写
  - 【已存在】的表格执行 --foot：不新建表格，只把缺失的条目以文本方式插入其 foot 文件的 $buttons 数组末尾（沿用数组现有缩进，同动作已存在则跳过、文件其余内容与手工编辑原样保留）；若该表 $foot 还指向 '@Foot'（base 共享 foot），会先生成 '~Foot{表名}' 专属文件再插入，此时按钮不再继承 base foot 里的其它按钮，需要就一并写进 --foot 列表
  - 【迁移旧结构】：1.1.53 及更早生成的 foot 文件是内联三级结构、没有 protected $buttons，对它执行 --foot 必须给出这张表【完整】的按钮列表（如 --foot=AddButton,BDeleteButton,export），生成器会按新结构整体重写该文件（沿用原来的 $name，$el 里的手工改动如自定义 tooltip 不会自动搬进 $buttons，提示后需自行处理）；不给按钮列表只带 --foot 时报错不动文件
  - --foot 不带值（如 composer xqkeji:table course --foot）进入交互式循环录入按钮动作（每次问一个动作名，留空结束）；--no-interaction 下必须给出 --foot=动作列表；树状表格（-T）的 foot 由内置 tree 模板生成，--foot 会提示并跳过（模板自带 protected $pager = [];，树表不分页；全选框 $checkAll 保留框架默认）；-N（仅建表格）时不写 acl/lang，只生成 foot 文件
  - 与 -a 的区别：-a 追加的是【列】（表格 \$el 里的列元素），--foot 维护的是【底部操作栏按钮】；两者都只动当前模块自己的文件，不碰 base

EOF
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $name = $input->getArgument('name');
        
        // 如果没有提供表格名，显示帮助信息
        if (empty($name)) {
            $output->writeln($this->getSynopsis(true));
            $output->writeln('');
            $output->writeln($this->getProcessedHelp());
            return 0;
        }
        
        // 验证表格名称（支持大小写字母、数字和下划线）
        if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $name)) {
            $output->writeln('<error>表格名称格式无效，只能包含字母、数字和下划线，且以字母开头</error>');
            return 1;
        }
        
        // 交互式向已有表格追加列元素（-a/--add）：不创建新表格，直接进入插入流程
        $table = new Table($this->getIO(), $this->requireComposer());
        if ($input->getOption('add')) {
            $table->addElementToTable($name, $input, $output);
            return 0;
        }

        // 从已有表格删除列元素引用（-r/--remove）：只删引用行，不创建、不涉及控制器/acl/menu/lang
        if ($input->getOption('remove')) {
            $conflicts = [];
            if (!empty($input->getOption('element'))) {
                $conflicts[] = '-e';
            }
            if ($input->getOption('tree')) {
                $conflicts[] = '-T';
            }
            if ($input->getOption('drag')) {
                $conflicts[] = '-D';
            }
            if ($input->getOption('foot') !== null) {
                $conflicts[] = '--foot';
            }
            if ($conflicts !== []) {
                $output->writeln('<error>-r/--remove 只用于删除已有表格的列，不能与 ' . implode('/', $conflicts) . '/-a 同时使用</error>');
                return 1;
            }
            $table->removeElementsFromTable($name);
            return 0;
        }

        $elements = $this->flattenElements($input->getOption('element'));
        $isTree = $input->getOption('tree');
        $isDrag = $input->getOption('drag');

        // -e 传了但没给出任何元素值（如 composer xqkeji:table User -e）：进入交互循环逐个添加列（树表忽略）
        if (!$isTree && empty($elements) && $input->hasParameterOption(['-e', '--element'], true)) {
            $elements = $this->collectElementsLoop($this->getIO(), '列元素');
        }

        $withController = !$input->getOption('no-controller');
        $withForm = !$input->getOption('no-form');
        $controllerFile = $input->getOption('controller-file');

        // --foot 底部按钮：带值 = 动作列表；只带 --foot 不带值 = 交互循环录入；完全没提 = null（不处理）
        $footRaw = $input->getOption('foot');
        $footArg = null;
        if (is_string($footRaw) && trim($footRaw) !== '') {
            $footArg = trim($footRaw);
        } elseif ($this->hasBareFootOption()) {
            $footArg = '';
        }

        // --no-check-all / --no-pager：本表 foot 里把 \$checkAll / \$pager 置空（关掉全选框 / 分页条）
        $noCheckAll = (bool) $input->getOption('no-check-all');
        $noPager = (bool) $input->getOption('no-pager');
        $footOptsRequested = $noCheckAll || $noPager;
        $footOpts = ['check_all' => !$noCheckAll, 'pager' => !$noPager];

        // -D 且表格类已存在：不新建表格，只为已有表格类补写 protected $isDrag = true;（幂等）
        if ($isDrag && !$isTree && $table->enableDrag($name)) {
            return 0;
        }

        // --foot（或只带 --no-check-all/--no-pager）且表格类已存在：不新建表格，只维护该表的底部操作栏
        if (($footArg !== null || $footOptsRequested) && !$isTree && $table->applyFoot($name, $footArg, $withController, $footOpts)) {
            return 0;
        }

        if ($footOptsRequested && $footArg === null) {
            $output->writeln('<error>--no-check-all / --no-pager 需要与 --foot 同用：要先为本表生成 foot 文件（protected \$buttons）才谈得上关掉全选框或分页条</error>');
            return 1;
        }

        $table->createTable($name, $elements, $input, $output, $isTree, $withController, $isDrag, $withForm, $controllerFile, $footArg, $footOpts);
        
        return 0;
    }

    /**
     * 命令行里是否出现了不带值的 --foot（直接扫描原始 argv，不依赖 Symfony 版本对
     * hasParameterOption('--foot') 是否匹配 '--foot=值' 的实现差异）
     */
    private function hasBareFootOption(): bool
    {
        foreach ((array) ($_SERVER['argv'] ?? []) as $token) {
            if ($token === '--foot' || $token === '--foot=') {
                return true;
            }
        }
        return false;
    }

    /**
     * 把 -e/--element 选项（IS_ARRAY，单个值可能为逗号分隔）展平为元素名数组
     */
    private function flattenElements($raw): array
    {
        $elements = [];
        foreach ((array) $raw as $item) {
            foreach (explode(',', (string) $item) as $el) {
                $el = trim($el);
                if ($el !== '') {
                    $elements[] = $el;
                }
            }
        }
        return $elements;
    }
}
