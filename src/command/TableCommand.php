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

    protected function configure()
    {
        $this->setName('xqkeji:table')
            ->setDescription('创建表格类')
            ->addArgument('name', InputArgument::OPTIONAL, '表格名称')
            ->addOption('element', 'e', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, '表格元素列表（可多次使用，或用逗号分隔：id,Username）')
            ->addOption('tree', 'T', InputOption::VALUE_NONE, '创建树形表格（继承 TreegridTable）')
            ->addOption('drag', 'D', InputOption::VALUE_NONE, '创建可拖动排序的普通表格（继承 Table，并生成 protected $isDrag = true;；与 -T 互斥，树表忽略该参数）')
            ->addOption('no-controller', 'N', InputOption::VALUE_NONE, '仅创建表格（表格类+元素），不创建控制器、不更新 acl.php/menu.php/zh_cn.php；树表同时不创建模型类与集合')
            ->addOption('controller-file', 'f', InputOption::VALUE_NONE, '生成控制器实体文件 controller/{Class}.php（默认不生成，使用虚拟控制器：只初始化 acl/menu/lang，只要 acl.php 有定义即生效）；仅对普通表格有效，树表始终复制动作类文件')
            ->addOption('no-form', 'F', InputOption::VALUE_NONE, '创建表格时【不】自动创建同名配套表单（默认会用表格列去掉 id/时间戳/操作列后追加 submit_reset，自动生成同名表单）')
            ->addOption('add', 'a', InputOption::VALUE_NONE, '向【已存在】的表格交互式追加列元素：先列出现有列，选择插入位置（某列之后/最前面），新元素按 xqkeji:element 流程创建')
            ->setHelp(<<<'EOF'
创建表格类和表格元素

<info>用法示例：</info>

  <comment># 创建普通表格（继承 Table）</comment>
  composer xqkeji:table User

  <comment># 创建普通表格（带元素列表）</comment>
  composer xqkeji:table User -e id -e Username -e SwitchCheck -e LoginTime -e EditDelete
  composer xqkeji:table User -e id,Username,SwitchCheck,LoginTime,EditDelete

  <comment># 创建树形表格（继承 TreegridTable）</comment>
  composer xqkeji:table User -T -e id -e Username -e SwitchCheck -e LoginTime -e EditDelete

  <comment># 创建可拖动排序的普通表格（继承 Table，并生成 protected $isDrag = true;）</comment>
  composer xqkeji:table User -D -e id,Username,SwitchCheck,LoginTime,EditDelete

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

<info>说明：</info>

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
        if ($input->getOption('add')) {
            $table = new Table($this->getIO(), $this->requireComposer());
            $table->addElementToTable($name, $input, $output);
            return 0;
        }

        $elements = $this->flattenElements($input->getOption('element'));
        $isTree = $input->getOption('tree');
        $isDrag = $input->getOption('drag');
        $withController = !$input->getOption('no-controller');
        $withForm = !$input->getOption('no-form');
        $controllerFile = $input->getOption('controller-file');

        $table = new Table($this->getIO(), $this->requireComposer());
        $table->createTable($name, $elements, $input, $output, $isTree, $withController, $isDrag, $withForm, $controllerFile);
        
        return 0;
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
