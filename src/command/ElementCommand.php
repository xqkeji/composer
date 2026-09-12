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
    use NormalizesShortOptions;

    protected function configure()
    {
        $this->setName('xqkeji:element')
            ->setDescription('创建、修改或删除表单/表格元素')
            ->addArgument('name', InputArgument::OPTIONAL, '元素名称')
            ->addOption('create', 'c', InputOption::VALUE_NONE, '创建元素')
            ->addOption('edit', 'e', InputOption::VALUE_NONE, '修改元素')
            ->addOption('remove', 'r', InputOption::VALUE_NONE, '删除元素')
            ->addOption('type', 'y', InputOption::VALUE_OPTIONAL, '指定元素类型（与 -c/-e 配合），不带值则弹出选择列表')
            ->addOption('list', 'l', InputOption::VALUE_OPTIONAL, '项目列表（Select/Check/Radio），格式：值1|文本1,值2|文本2')
            ->addOption('default', 'D', InputOption::VALUE_OPTIONAL, '默认值')
            ->addOption('model', 'm', InputOption::VALUE_OPTIONAL, '模型名（Select/Check/Radio），从模型动态加载项目列表')
            ->setHelp(<<<'EOF'
创建、修改或删除表单/表格元素（根据当前模式决定）

<info>用法示例：</info>

  <comment># 创建元素（默认操作，使用默认类型）</comment>
  composer xqkeji:element Username

  <comment># 创建元素（指定类型）</comment>
  composer xqkeji:element Username -c -y=Text
  composer xqkeji:element Username -c -y Text

  <comment># 创建元素（-y 不带值，弹出类型选择列表）</comment>
  composer xqkeji:element Username -c -y

  <comment># 创建 Select 元素（带项目列表和默认值，| 分隔符）</comment>
  composer xqkeji:element Status -c -y=Select -l "1|启用,0|禁用" -D=1

  <comment># 创建 Select 元素（= 分隔符）</comment>
  composer xqkeji:element Status -c -y=Select -l "1=启用,0=禁用" -D=1

  <comment># 创建 Check 元素（带项目列表）</comment>
  composer xqkeji:element Roles -c -y=Check -l "1|管理员,2|编辑,3|用户"

  <comment># 创建 Radio 元素（带项目列表和默认值）</comment>
  composer xqkeji:element Gender -c -y=Radio -l "1|男,2|女" -D=1

  <comment># 创建 Select 元素（从模型动态加载项目列表）</comment>
  composer xqkeji:element Status -c -y=Select -m=category_type

  <comment># 修改元素（指定类型）</comment>
  composer xqkeji:element Username -e -y=Select

  <comment># 修改元素（-y 不带值，弹出类型选择列表）</comment>
  composer xqkeji:element Username -e -y

  <comment># 删除元素</comment>
  composer xqkeji:element Username -r

<info>说明：</info>

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

EOF
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // 从原始命令行参数解析，避免 Symfony VALUE_OPTIONAL 吞掉位置参数
        list($name, $action, $specifiedType, $items, $defaultValue, $modelName) = $this->parseFromArgv();

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
            $element->editElement($name, $specifiedType, $items, $defaultValue, $modelName);
            return 0;
        }

        // 创建操作（默认）
        $element->createElement($name, $specifiedType, $items, $defaultValue, $modelName);

        return 0;
    }

    /**
     * 从 $_SERVER['argv'] 解析命令参数
     * 返回 [元素名, 操作(create/edit/remove), 指定类型|null, 项目列表|null, 默认值|null, 模型名|null]
     */
    private function parseFromArgv(): array
    {
        $name = null;
        $action = 'create'; // 默认创建
        $specifiedType = null;
        $hasTypeFlag = false; // 是否使用了 -y 参数（不带值）
        $items = null;
        $hasItemsFlag = false;
        $defaultValue = null;
        $hasDefaultFlag = false;
        $modelName = null;
        $hasModelFlag = false;

        $argv = $_SERVER['argv'] ?? [];
        if (empty($argv)) {
            return [$name, $action, $specifiedType, $items, $defaultValue, $modelName];
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

            // 解析 -y / --type
            if ($token === '-y' || $token === '--type') {
                $hasTypeFlag = true;
                $i++;
                // 检查下一个token是否是类型值（不是另一个选项）
                if ($i < $count && strpos($tokens[$i], '-') !== 0) {
                    $specifiedType = $tokens[$i];
                    $i++;
                }
                continue;
            }

            // 解析 -y=类型名 / --type=类型名
            if (strpos($token, '-y=') === 0) {
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

            // 解析 -l / --list
            if ($token === '-l' || $token === '--list') {
                $hasItemsFlag = true;
                $i++;
                // 检查下一个token是否是列表值
                if ($i < $count && strpos($tokens[$i], '-') !== 0) {
                    $items = $tokens[$i];
                    $i++;
                }
                continue;
            }

            // 解析 -l=列表 / --list=列表
            if (strpos($token, '-l=') === 0) {
                $hasItemsFlag = true;
                $items = substr($token, 3);
                $i++;
                continue;
            }
            if (strpos($token, '--list=') === 0) {
                $hasItemsFlag = true;
                $items = substr($token, 7);
                $i++;
                continue;
            }

            // 解析 -D / --default
            if ($token === '-D' || $token === '--default') {
                $hasDefaultFlag = true;
                $i++;
                // 检查下一个token是否是默认值
                if ($i < $count && strpos($tokens[$i], '-') !== 0) {
                    $defaultValue = $tokens[$i];
                    $i++;
                }
                continue;
            }

            // 解析 -D=值 / --default=值
            if (strpos($token, '-D=') === 0) {
                $hasDefaultFlag = true;
                $defaultValue = substr($token, 3);
                $i++;
                continue;
            }
            if (strpos($token, '--default=') === 0) {
                $hasDefaultFlag = true;
                $defaultValue = substr($token, 10);
                $i++;
                continue;
            }

            // 解析 -m / --model
            if ($token === '-m' || $token === '--model') {
                $hasModelFlag = true;
                $i++;
                // 检查下一个token是否是模型名
                if ($i < $count && strpos($tokens[$i], '-') !== 0) {
                    $modelName = $tokens[$i];
                    $i++;
                }
                continue;
            }

            // 解析 -m=模型名 / --model=模型名
            if (strpos($token, '-m=') === 0) {
                $hasModelFlag = true;
                $modelName = substr($token, 3);
                $i++;
                continue;
            }
            if (strpos($token, '--model=') === 0) {
                $hasModelFlag = true;
                $modelName = substr($token, 8);
                $i++;
                continue;
            }

            // 第一个非选项参数作为元素名
            if ($name === null && strpos($token, '-') !== 0) {
                $name = $token;
            }

            $i++;
        }

        // -y 不带值 → 设置为空字符串，表示需要弹出选择列表
        if ($hasTypeFlag && $specifiedType === null) {
            $specifiedType = '';
        }

        // -l 不带值 → 设置为空字符串，表示需要交互输入
        if ($hasItemsFlag && $items === null) {
            $items = '';
        }

        // -d 不带值 → 设置为空字符串，表示需要交互输入
        if ($hasDefaultFlag && $defaultValue === null) {
            $defaultValue = '';
        }

        // -m 不带值 → 设置为空字符串，表示需要交互输入
        if ($hasModelFlag && $modelName === null) {
            $modelName = '';
        }

        return [$name, $action, $specifiedType, $items, $defaultValue, $modelName];
    }
}
