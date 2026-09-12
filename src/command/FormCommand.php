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
            ->addArgument('elements', InputArgument::OPTIONAL | InputArgument::IS_ARRAY, '表单元素列表')
            ->addOption('tab', 'b', InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, 'Tab配置（可多次使用）')
            ->addOption('global', 'g', InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, '全局表单元素（在Tab之外）')
            ->setHelp(<<<'EOF'
创建表单类和表单元素

<info>用法示例：</info>

  <comment># 创建普通表单（无元素）</comment>
  composer xqkeji:form User

  <comment># 创建普通表单（带元素列表）</comment>
  composer xqkeji:form User Username Password Email

  <comment># 创建Tab表单（Tab英文名称自动生成）</comment>
  composer xqkeji:form User -b "基本信息" Username Password -b "授权信息" Auth Csrf

  <comment># 创建Tab表单（带全局元素）</comment>
  composer xqkeji:form User -b "基本信息" username password -b "授权信息" auth csrf -g submit_reset

  <comment># 创建Tab表单（中文名称不加引号）</comment>
  composer xqkeji:form User -b 基本信息 username password -b 授权信息 auth csrf

<info>说明：</info>

  - 表单名支持大小写，自动转为大驼峰（如 user → User、user_login → UserLogin）
  - 表单元素名支持小写加下划线或大驼峰，命令行时可以用小写加_或-的格式
  - 表单类创建在当前模块的 form/ 目录下
  - 表单元素创建在当前模块的 form/element/ 目录下
  - 如果元素在 base 模块已存在，使用 @ElementName 引入
  - 如果元素在当前模块已存在或新创建，使用 ~ElementName 引入
  - 创建新元素时会交互式询问中文名称，已存在的元素不会询问
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
        
        // 解析Tab和全局元素
        list($tabGroups, $globalElements) = $this->parseTabAndGlobal($input, $name);

        // Tab/全局表单的元素已通过 parseTabAndGlobal 从 argv 解析到 tabGroups/globalElements；
        // 普通表单（无 -b/-g）的元素来自命令行位置参数 elements，需传入 createForm，否则 $el 为空
        $isTabForm = !empty($tabGroups) || !empty($globalElements);
        $elements = $input->getArgument('elements') ?? [];

        $form = new Form($this->getIO(), $this->requireComposer());
        $form->createForm($name, $isTabForm ? [] : $elements, $input, $output, $tabGroups, $globalElements);

        return 0;
    }
    
    /**
     * 从原始命令行参数中解析Tab组和全局元素
     * 通过 $_SERVER['argv'] 获取原始参数，避免 Symfony 解析干扰
     *
     * 格式：composer xqkeji:form FormName -b "Tab中文名称" element1 element2 -b "Tab2中文" el3 el4 -g globalEl1
     * Tab英文名称自动生成：{表单名小写下划线}_tab{序号}
     */
    private function parseTabAndGlobal(InputInterface $input, string $formName): array
    {
        $tabGroups = [];
        $globalElements = [];

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

            // 检测 -b 或 --tab
            if ($token === '-b' || $token === '--tab') {
                $i++;
                // 第一个参数是Tab中文名称（可带引号也可不带）
                $tabText = $tokens[$i] ?? '';
                $tabIndex++;

                // 收集tab元素直到下一个 -b/--tab/-g/--global 或结束
                $tabElements = [];
                $i++;
                while ($i < $count) {
                    $nextToken = $tokens[$i];
                    if ($nextToken === '-b' || $nextToken === '--tab' || $nextToken === '-g' || $nextToken === '--global') {
                        break;
                    }
                    // 跳过其他选项（如 --no-interaction），元素名不会以 - 开头
                    if (isset($nextToken[0]) && '-' === $nextToken[0]) {
                        $i++;
                        continue;
                    }
                    $tabElements[] = $nextToken;
                    $i++;
                }

                // Tab英文名称自动生成：{表单名}_tab{序号}
                $tabName = $formSnakeName . '_tab' . $tabIndex;

                $tabGroups[] = [
                    'name' => $tabName,
                    'text' => $tabText,
                    'elements' => $tabElements,
                ];
                continue;
            }

            // 检测 -g 或 --global
            if ($token === '-g' || $token === '--global') {
                $i++;
                while ($i < $count) {
                    $nextToken = $tokens[$i];
                    if ($nextToken === '-b' || $nextToken === '--tab' || $nextToken === '-g' || $nextToken === '--global') {
                        break;
                    }
                    // 跳过其他选项（如 --no-interaction），元素名不会以 - 开头
                    if (isset($nextToken[0]) && '-' === $nextToken[0]) {
                        $i++;
                        continue;
                    }
                    $globalElements[] = $nextToken;
                    $i++;
                }
                continue;
            }

            $i++;
        }

        return [$tabGroups, $globalElements];
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
