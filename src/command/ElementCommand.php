<?php
namespace xqkeji\composer\command;

use Composer\Command\BaseCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use xqkeji\composer\Element;

class ElementCommand extends BaseCommand
{
    protected function configure()
    {
        $this->setName('xqkeji:element')
            ->setDescription('创建、修改或删除表单/表格元素')
            ->addArgument('name', InputArgument::OPTIONAL, '元素名称')
            ->addOption('create', 'c', InputOption::VALUE_NONE, '创建元素')
            ->addOption('edit', 'e', InputOption::VALUE_NONE, '修改元素')
            ->addOption('remove', 'r', InputOption::VALUE_NONE, '删除元素')
            ->addOption('type', 't', InputOption::VALUE_OPTIONAL, '指定元素类型（与 -c/-e 配合），不带值则弹出选择列表')
            ->setHelp(<<<'EOF'
创建、修改或删除表单/表格元素（根据当前模式决定）

<info>用法示例：</info>

  <comment># 创建元素（默认操作，使用默认类型）</comment>
  composer xqkeji:element Username

  <comment># 创建元素（指定类型）</comment>
  composer xqkeji:element Username -c -t=Text
  composer xqkeji:element Username -c -t Text

  <comment># 创建元素（-t 不带值，弹出类型选择列表）</comment>
  composer xqkeji:element Username -c -t

  <comment># 修改元素（指定类型）</comment>
  composer xqkeji:element Username -e -t=Select

  <comment># 修改元素（-t 不带值，弹出类型选择列表）</comment>
  composer xqkeji:element Username -e -t

  <comment># 删除元素</comment>
  composer xqkeji:element Username -r

<info>说明：</info>

  - 元素名称支持大小写，自动转为大驼峰（如 username → Username、user_name → UserName）
  - 表单元素创建在当前模块的 form/element/ 目录下
  - 表格元素创建在当前模块的 table/element/ 目录下
  - 创建/修改前需要先使用 xqkeji:use -f（表单模式）或 -t（表格模式）设置当前模式
  - 不指定 -c/-e/-r 时，默认为创建操作
  - 使用 -t 不带值会强制弹出类型选择列表（交互模式）
  - 使用 -t=类型名 直接指定类型
  - 删除操作会要求确认

EOF
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // 从原始命令行参数解析，避免 Symfony VALUE_OPTIONAL 吞掉位置参数
        list($name, $action, $specifiedType) = $this->parseFromArgv();

        // 如果没有提供元素名，显示帮助信息
        if (empty($name)) {
            $output->writeln($this->getSynopsis(true));
            $output->writeln('');
            $output->writeln($this->getProcessedHelp());
            return 0;
        }

        // 验证元素名称
        if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $name)) {
            $output->writeln('<error>元素名称格式无效，只能包含字母、数字和下划线，且以字母开头</error>');
            return 1;
        }

        $element = new Element($this->getIO(), $this->requireComposer());

        // 删除操作
        if ($action === 'remove') {
            $element->removeElement($name);
            return 0;
        }

        // 修改操作
        if ($action === 'edit') {
            $element->editElement($name, $specifiedType);
            return 0;
        }

        // 创建操作（默认）
        $element->createElement($name, $specifiedType);

        return 0;
    }

    /**
     * 从 $_SERVER['argv'] 解析命令参数
     * 返回 [元素名, 操作(create/edit/remove), 指定类型|null]
     */
    private function parseFromArgv(): array
    {
        $name = null;
        $action = 'create'; // 默认创建
        $specifiedType = null;
        $hasTypeFlag = false; // 是否使用了 -t 参数（不带值）

        $argv = $_SERVER['argv'] ?? [];
        if (empty($argv)) {
            return [$name, $action, $specifiedType];
        }

        $tokens = [];
        $foundCommand = false;
        foreach ($argv as $arg) {
            if (!$foundCommand) {
                if (strpos($arg, 'xqkeji:element') !== false) {
                    $foundCommand = true;
                }
                continue;
            }
            if ($arg === '--help' || $arg === '-h') {
                continue;
            }
            $tokens[] = $arg;
        }

        $i = 0;
        $count = count($tokens);

        while ($i < $count) {
            $token = $tokens[$i];

            // 解析 -c / --create
            if ($token === '-c' || $token === '--create') {
                $action = 'create';
                $i++;
                continue;
            }

            // 解析 -e / --edit
            if ($token === '-e' || $token === '--edit') {
                $action = 'edit';
                $i++;
                continue;
            }

            // 解析 -r / --remove
            if ($token === '-r' || $token === '--remove') {
                $action = 'remove';
                $i++;
                continue;
            }

            // 解析 -t / --type
            if ($token === '-t' || $token === '--type') {
                $hasTypeFlag = true;
                $i++;
                // 检查下一个token是否是类型值（不是另一个选项）
                if ($i < $count && strpos($tokens[$i], '-') !== 0) {
                    $specifiedType = $tokens[$i];
                    $i++;
                }
                continue;
            }

            // 解析 -t=类型名 / --type=类型名
            if (strpos($token, '-t=') === 0) {
                $hasTypeFlag = true;
                $specifiedType = substr($token, 3);
                $i++;
                continue;
            }
            if (strpos($token, '--type=') === 0) {
                $hasTypeFlag = true;
                $specifiedType = substr($token, 7);
                $i++;
                continue;
            }

            // 第一个非选项参数作为元素名
            if ($name === null && strpos($token, '-') !== 0) {
                $name = $token;
            }

            $i++;
        }

        // -t 不带值 → 设置为空字符串，表示需要弹出选择列表
        if ($hasTypeFlag && $specifiedType === null) {
            $specifiedType = '';
        }

        return [$name, $action, $specifiedType];
    }
}
