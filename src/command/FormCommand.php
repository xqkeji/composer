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

    protected function configure()
    {
        $this->setName('xqkeji:form')
            ->setDescription('创建表单类')
            ->addArgument('name', InputArgument::OPTIONAL, '表单名称')
            ->addOption('element', 'e', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, '表单元素列表（可多次使用，或用逗号分隔：Username,Password）')
            ->addOption('tab', 'b', InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, 'Tab配置（可多次使用）')
            ->addOption('global', 'g', InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, '全局表单元素（在Tab之外）')
            ->setHelp(<<<'EOF'
创建表单类和表单元素

<info>用法示例：</info>

  <comment># 创建普通表单（无元素）</comment>
  composer xqkeji:form User

  <comment># 创建普通表单（带元素列表）</comment>
  composer xqkeji:form User -e Username -e Password -e Email
  composer xqkeji:form User -e Username,Password,Email

  <comment># 创建Tab表单（Tab英文名称自动生成）</comment>
  composer xqkeji:form User -b "基本信息" -e Username -e Password -b "授权信息" -e Auth -e Csrf

  <comment># 创建Tab表单（带全局元素）</comment>
  composer xqkeji:form User -b "基本信息" -e username -e password -b "授权信息" -e auth -e csrf -g -e submit_reset

  <comment># 创建Tab表单（中文名称不加引号）</comment>
  composer xqkeji:form User -b 基本信息 -e username -e password -b 授权信息 -e auth -e csrf

<info>说明：</info>

  - 表单名支持大小写，自动转为大驼峰（如 user → User、user_login → UserLogin）
  - 表单元素名支持小写加下划线或大驼峰，命令行时可以用小写加_或-的格式
  - 表单类创建在当前模块的 form/ 目录下
  - 表单元素创建在当前模块的 form/element/ 目录下
  - 如果元素在 base 模块已存在，使用 @ElementName 引入
  - 如果元素在当前模块已存在或新创建，使用 ~ElementName 引入
  - 创建新元素时会交互式询问中文名称，已存在的元素不会询问
  - 表单元素通过 -e/--element 指定（可多次使用，也可用逗号分隔：-e Username,Password），元素名自动转为大驼峰
  - -e 值可用引号包裹，引号内逗号分隔支持带空格：-e "User Name, Email"（无引号时逗号后请勿加空格，否则会被 shell 拆成多个参数）
  - 使用 -b/--tab 创建Tab切换效果的表单（继承 TabForm）
  - Tab英文名称自动生成：{表单名小写下划线}_tab{序号}（如 user_tab1、user_tab2）
  - Tab中文名称可加引号也可不加引号
  - 使用 -g/--global 添加Tab之外的全局元素
  - 需要先使用 xqkeji:use 切换到目标模块

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
        
        // 解析Tab和全局元素（从 argv 识别 -b/--tab 与 -g/--global 分组）
        list($tabGroups, $globalElements) = $this->parseTabAndGlobal($input, $name);

        // Tab/全局表单的元素已通过 parseTabAndGlobal 归入对应分组；
        // 普通表单（无 -b/-g）的元素来自 -e/--element 选项（支持逗号分隔与重复），需传入 createForm
        $isTabForm = !empty($tabGroups) || !empty($globalElements);
        $elements = $this->flattenElements($input->getOption('element'));

        $form = new Form($this->getIO(), $this->requireComposer());
        $form->createForm($name, $isTabForm ? [] : $elements, $input, $output, $tabGroups, $globalElements);

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
