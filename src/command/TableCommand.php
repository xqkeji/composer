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
            ->addOption('element', 'e', InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, '列元素：可多次或逗号分隔（-e id,Username）；只给 -e 不带值则交互循环逐个添加')
            ->addOption('tree', 'T', InputOption::VALUE_NONE, '创建树形表格（继承 TreegridTable）')
            ->addOption('drag', 'D', InputOption::VALUE_NONE, '拖动排序表格：写 $isDrag=true，配套 b_order 与 Admin.php；列含 ordernum 时自动等效')
            ->addOption('no-controller', 'N', InputOption::VALUE_NONE, '只建表格与元素，不建控制器、不写 acl.php/menu.php/zh_cn.php')
            ->addOption('controller-file', 'f', InputOption::VALUE_NONE, '生成控制器实体文件 controller/{Class}.php（默认用虚拟控制器，只写 acl/menu/lang）；仅普通表格有效')
            ->addOption('icon', 'i', InputOption::VALUE_REQUIRED, '菜单图标（默认按中文名/表名词根自动推荐；bi-person / bi bi-person / person 三种写法等价）')
            ->addOption('no-form', 'F', InputOption::VALUE_NONE, '不自动创建同名配套表单（默认会用表格列派生一份 form/{Class}.php）')
            ->addOption('add', 'a', InputOption::VALUE_NONE, '向【已存在】的表格交互式追加列：选插入位置后按 xqkeji:element 流程建元素')
            ->addOption('remove', 'r', InputOption::VALUE_NONE, '从【已存在】的表格按编号删除列引用（可逗号分隔一次删多条），只动 $el 那一行文本')
            ->addOption('foot', null, InputOption::VALUE_OPTIONAL, '本表底部按钮：--foot=add,b_delete,export（动作名或 base 按钮名均可）；不带值则交互录入')
            ->addOption('no-check-all', null, InputOption::VALUE_NONE, '本表底栏不要全选框（foot 文件写 protected $checkAll = [];）；需与 --foot 同用')
            ->addOption('no-pager', null, InputOption::VALUE_NONE, '本表底栏不要分页条（foot 文件写 protected $pager = [];）；需与 --foot 同用')
            ->setHelp(<<<'EOF'
创建表格类（表格 + 列元素），默认同时准备控制器配置与同名配套表单

<info>用法示例：</info>

  <comment># 表格建在【当前模块】的 table/ 下，先切模块</comment>
  composer xqkeji:use -- edu

  <comment># 建表：-e 可多次使用也可逗号分隔；只给 -e 不带值则交互循环逐个加列</comment>
  composer xqkeji:table User -e id,Username,SwitchCheck,EditDelete
  composer xqkeji:table User -e

  <comment># 树表（TreegridTable）/ 拖动排序表（$isDrag，配套 b_order 动作与 controller/{表}/Admin.php）</comment>
  composer xqkeji:table User -T
  composer xqkeji:table User -D -e id,name,ordernum,edit_delete

  <comment># 对【已存在】的表格：-a 交互追加列、-r 按编号删列、-D 只补写 $isDrag</comment>
  composer xqkeji:table User -a
  composer xqkeji:table User -r
  composer xqkeji:table User -D

  <comment># 本表专属底部按钮：生成 table/element/FootUser.php，表格 $foot 改为 '~FootUser'</comment>
  composer xqkeji:table User --foot=add,b_delete,export
  composer xqkeji:table User --foot=export --no-pager

  <comment># 默认虚拟控制器 + 自动派生同名表单 + 自动推荐菜单图标；按需覆盖</comment>
  composer xqkeji:table User -N -F
  composer xqkeji:table User -f -i bi-person

<info>要点：</info>

  - 表名与列名自动转大驼峰（user_list → UserList）；元素在 base 已存在引用为 @Name，在本模块为 ~Name
  - 带 -e 时首列自动置为 Id，末列名不含 delete 时询问追加 @EditDelete
  - select_ 前缀列免选类型，生成继承 ListSelectModel 的空子类
  - -D 与 -T 互斥；-e 列含 ordernum 时未传 -D 也按拖动排序处理
  - 完整规则（默认动作集、b_order/Admin.php、--foot 按钮词表与旧结构迁移、-r 的元素文件清理）见仓库 AGENTS.md

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
        $menuIcon = (string) $input->getOption('icon');

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

        $table->createTable($name, $elements, $input, $output, $isTree, $withController, $isDrag, $withForm, $controllerFile, $footArg, $footOpts, $menuIcon);
        
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
