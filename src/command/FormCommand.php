<?php
namespace xqkeji\composer\command;

use Composer\Command\BaseCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use xqkeji\composer\Form;

class FormCommand extends BaseCommand
{
    use NormalizesShortOptions;
    use ElementLoopTrait;

    protected function configure()
    {
        $this->setName('xqkeji:form')
            ->setDescription('创建表单类')
            ->addArgument('name', InputArgument::OPTIONAL, '表单名称')
            ->addOption('element', 'e', InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, '元素：可多次或逗号分隔（-e Username,Password）；只给 -e 不带值则交互循环逐个添加')
            ->addOption('tab', 'b', InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, '开启一个 Tab 分组（-b "基本信息"），其后的 -e 元素归入该组')
            ->addOption('global', 'g', InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, '其后 -e 的元素放在 Tab 之外的全局区（仅 Tab 表单用）')
            ->addOption('add', 'a', InputOption::VALUE_NONE, '向【已存在】的表单交互式追加元素：选插入位置后按 xqkeji:element 流程建元素')
            ->addOption('search', 's', InputOption::VALUE_NONE, '创建搜索表单（继承 SearchForm，GET 提交）：逐个元素问【搜索字段,操作】并生成 xq-s- 数组项；与 -b/-g 互斥')
            ->addOption('remove', 'r', InputOption::VALUE_NONE, '从【已存在】的表单按编号删除元素引用（可逗号分隔如 2,5.1），只动 $el 那一行文本')
            ->setHelp(<<<'EOF'
创建表单类（表单 + 元素），建完自动切到表单模式

<info>用法示例：</info>

  <comment># 表单建在【当前模块】的 form/ 下，先切模块</comment>
  composer xqkeji:use -- edu

  <comment># 建表单：-e 可多次使用也可逗号分隔；只给 -e 不带值则交互循环逐个加元素</comment>
  composer xqkeji:form User -e Username,Password,Email
  composer xqkeji:form User -e

  <comment># select_ 前缀元素免选类型，生成继承 SelectModel 的空子类（蛇形 select_dept / 驼峰 SelectDept 均可）</comment>
  composer xqkeji:form Article -e title,select_dept,SelectStatus

  <comment># Tab 表单（继承 TabForm）：-b 开一个分组，其后的 -e 归它；-g 之后为 Tab 之外的全局元素</comment>
  composer xqkeji:form User -b "基本信息" -e username -e password -b "授权信息" -e auth -g -e submit_reset

  <comment># 搜索表单（继承 SearchForm，GET 提交）：会问【搜索字段/操作】，也可 -e "元素=字段,op" 内联免交互</comment>
  composer xqkeji:form UserSearch -s -e "SearchKey=username|fullname,like" -e Status,SearchSubmit

  <comment># 对【已存在】的表单：-a 交互追加元素、-r 按编号删引用（2,5.1 = 第 2 条与第 5 个 Tab 内的第 1 条）</comment>
  composer xqkeji:form User -a
  composer xqkeji:form User -r

<info>要点：</info>

  - 表单名与元素名自动转大驼峰；元素在 base 已存在引用为 @Name，在本模块为 ~Name
  - 带 -e 时末元素名不含 submit 会询问追加按钮：普通表单 @SubmitReset，搜索表单 -s 为 @SearchSubmit（Tab 表单不处理）
  - -s 的输入元素统一套 '@search' 模板；select_ 元素的默认搜索字段去掉 select_ 再补 _id（select_section → section_id）
  - -s 与 -b/-g 互斥；-r 与 -e/-b/-g/-a/-s 互斥
  - 完整规则（xq-s- 规格与操作符别名、@search 的新建/复用两种写法、Tab 命名、-r 的元素文件清理）见仓库 AGENTS.md

EOF
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $name = $input->getArgument('name');
        
        // 如果没有提供表单名，显示帮助信息
        if (empty($name)) {
            $output->writeln($this->getSynopsis(true));
            $output->writeln('');
            $output->writeln($this->getProcessedHelp());
            return 0;
        }
        
        // 验证表单名称（支持大小写字母、数字和下划线）
        if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $name)) {
            $output->writeln('<error>表单名称格式无效，只能包含字母、数字和下划线，且以字母开头</error>');
            return 1;
        }
        
        // 交互式向已有表单追加元素（-a/--add）：不创建新表单，直接进入插入流程
        if ($input->getOption('add')) {
            $form = new Form($this->getIO(), $this->requireComposer());
            $form->addElementToForm($name, $input, $output);
            return 0;
        }

        // 从已有表单删除元素引用（-r/--remove）：只删引用行，不创建、不涉及 acl/menu/lang
        if ($input->getOption('remove')) {
            if ($input->getOption('search') || !empty($input->getOption('element'))
                || !empty($input->getOption('tab')) || !empty($input->getOption('global'))) {
                $output->writeln('<error>-r/--remove 只用于删除已有表单的元素，不能与 -e/-b/-g/-a/-s 同时使用</error>');
                return 1;
            }
            $form = new Form($this->getIO(), $this->requireComposer());
            $form->removeElementsFromForm($name);
            return 0;
        }

        // 解析Tab和全局元素（从 argv 识别 -b/--tab 与 -g/--global 分组）
        list($tabGroups, $globalElements) = $this->parseTabAndGlobal($input, $name);

        // Tab/全局表单的元素已通过 parseTabAndGlobal 归入对应分组；
        // 普通表单（无 -b/-g）的元素来自 -e/--element 选项（支持逗号分隔与重复），需传入 createForm
        $isTabForm = !empty($tabGroups) || !empty($globalElements);
        $isSearchForm = (bool) $input->getOption('search');
        if ($isSearchForm && $isTabForm) {
            $output->writeln('<error>-s/--search 与 -b/--tab、-g/--global 互斥（基类只能有一个）</error>');
            return 1;
        }
        $elements = $this->flattenElements($input->getOption('element'));

        // -e 传了但没给出任何元素值（如 composer xqkeji:form User -e）：进入交互循环逐个添加
        if (!$isTabForm && empty($elements) && $input->hasParameterOption(['-e', '--element'], true)) {
            $elements = $this->collectElementsLoop($this->getIO(), '元素');
        }

        $form = new Form($this->getIO(), $this->requireComposer());
        $form->createForm($name, $isTabForm ? [] : $elements, $input, $output, $tabGroups, $globalElements, $isSearchForm);

        return 0;
    }
    
    /**
     * 从原始命令行参数中解析Tab组和全局元素
     * 通过 $_SERVER['argv'] 获取原始参数，避免 Symfony 解析干扰
     *
     * 元素通过 -e/--element 指定（可多次使用，也可逗号分隔），并按出现顺序归属到当前 Tab 组或全局；
     * 普通表单（无 -b/-g）的元素由 -e/--element 选项原生收集（见 flattenElements），不入此处分组。
     * 格式：composer xqkeji:form FormName -b "Tab中文名称" -e el1 -e el2 -b "Tab2中文" -e el3 -g -e globalEl
     * Tab英文名称自动生成：{表单名小写下划线}_tab{序号}
     */
    private function parseTabAndGlobal(InputInterface $input, string $formName): array
    {
        $tabGroups = [];
        $globalElements = [];
        $inGlobal = false;

        // 从 $_SERVER['argv'] 获取原始命令行参数
        $argv = $_SERVER['argv'] ?? [];
        if (empty($argv)) {
            return [$tabGroups, $globalElements];
        }

        // 找到 xqkeji:form 后面的参数
        $tokens = [];
        $foundCommand = false;
        foreach ($argv as $arg) {
            if (!$foundCommand) {
                if (strpos($arg, 'xqkeji:form') !== false) {
                    $foundCommand = true;
                }
                continue;
            }
            // 跳过 --help/-h
            if ($arg === '--help' || $arg === '-h') {
                continue;
            }
            $tokens[] = $arg;
        }

        $formSnakeName = $this->toSnakeCase($formName);
        $tabIndex = 0;
        $i = 0;
        $count = count($tokens);

        while ($i < $count) {
            $token = $tokens[$i];

            // 检测 -e / --element（元素，归入当前 Tab 组或全局；支持 -e=val / --element=val 连写）
            if ($token === '-e' || $token === '--element') {
                $i++;
                if ($i < $count && strpos($tokens[$i], '-') !== 0) {
                    $this->collectTabElements($tokens[$i], $tabGroups, $globalElements, $tabIndex, $inGlobal);
                    $i++;
                }
                continue;
            }
            if (preg_match('/^-e=(.*)$/s', $token, $m) || preg_match('/^--element=(.*)$/s', $token, $m)) {
                $this->collectTabElements($m[1], $tabGroups, $globalElements, $tabIndex, $inGlobal);
                $i++;
                continue;
            }

            // 检测 -b 或 --tab（开始一个新的 Tab 组）
            if ($token === '-b' || $token === '--tab') {
                $i++;
                $tabText = $tokens[$i] ?? '';
                $tabIndex++;
                $tabName = $formSnakeName . '_tab' . $tabIndex;
                $tabGroups[] = [
                    'name' => $tabName,
                    'text' => $tabText,
                    'elements' => [],
                ];
                $inGlobal = false;
                $i++;
                continue;
            }

            // 检测 -g 或 --global（切换为全局元素模式）
            if ($token === '-g' || $token === '--global') {
                $inGlobal = true;
                $i++;
                continue;
            }

            // 跳过其它选项（如 --no-interaction），元素名不会以 - 开头
            if (isset($token[0]) && '-' === $token[0]) {
                $i++;
                continue;
            }

            $i++;
        }

        return [$tabGroups, $globalElements];
    }

    /**
     * 把一个 -e 值（可能逗号分隔）拆成多个元素，归入当前 Tab 组或全局元素列表
     */
    private function collectTabElements(string $raw, array &$tabGroups, array &$globalElements, int $tabIndex, bool $inGlobal): void
    {
        foreach (explode(',', $raw) as $el) {
            $el = trim($el);
            if ($el === '') {
                continue;
            }
            if ($inGlobal) {
                $globalElements[] = $el;
            } elseif ($tabIndex > 0) {
                $tabGroups[count($tabGroups) - 1]['elements'][] = $el;
            }
            // tabIndex===0 且非 global：普通表单元素由 -e 选项原生收集（flattenElements），不入分组
        }
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

    /**
     * 将驼峰命名转换为小写下划线命名
     */
    private function toSnakeCase(string $string): string
    {
        // 先处理连续大写字母（如 XMLParser -> xml_parser）
        $result = preg_replace('/([A-Z]+)([A-Z][a-z])/', '$1_$2', $string);
        // 再处理普通驼峰（如 xmlParser -> xml_parser）
        $result = preg_replace('/([a-z\d])([A-Z])/', '$1_$2', $result);
        return strtolower($result);
    }
}
