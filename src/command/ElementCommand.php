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
            ->addOption('create', 'c', InputOption::VALUE_NONE, '创建元素（不传 -c/-e/-r 时默认就是创建）')
            ->addOption('edit', 'e', InputOption::VALUE_NONE, '修改已存在的元素')
            ->addOption('remove', 'r', InputOption::VALUE_NONE, '删除元素（会要求确认）')
            ->addOption('type', 'y', InputOption::VALUE_OPTIONAL, '元素类型：-y=Text 直接指定；只给 -y 不带值则弹选择列表')
            ->addOption('list', 'l', InputOption::VALUE_OPTIONAL, '项目列表（Select/Check/Radio）：值|文本,值|文本（也支持 值=文本）')
            ->addOption('default', 'D', InputOption::VALUE_OPTIONAL, '默认值')
            ->addOption('model', 'm', InputOption::VALUE_OPTIONAL, '模型名（Select/Check/Radio）：项目列表由模型动态加载')
            ->addOption('filters', 'f', InputOption::VALUE_OPTIONAL, '过滤器（仅表单元素，写 protected $filters）：逗号分隔，带参数用 名称=>参数；不带值则交互输入')
            ->addOption('vt', 't', InputOption::VALUE_OPTIONAL, '验证规则（仅表单元素，写 protected $vt）：多条用分号、选项用冒号且写成 名称=值（多个用竖线）；规则名自动补 $ 前缀；不带值则交互输入')
            ->setHelp(<<<'EOF'
创建、修改或删除单个元素（按当前模式落到 form/element/ 或 table/element/）

<info>用法示例：</info>

  <comment># 元素目录由当前模式决定，先切模式（-f 表单 / -t 表格）</comment>
  composer xqkeji:use -f
  composer xqkeji:element Username -c -y=Text
  composer xqkeji:element Username -c -y          <comment># 只给 -y 不带值 = 弹出类型选择列表</comment>

  <comment># 选项类元素：-l 项目列表（值|文本 或 值=文本，逗号分隔）、-D 默认值、-m 改由模型动态加载</comment>
  composer xqkeji:element Status -c -y=Select -l "1|启用,0|禁用" -D=1
  composer xqkeji:element Roles -c -y=Check -l "1|管理员,2|编辑"
  composer xqkeji:element Status -c -y=Select -m=category_type

  <comment># 过滤器与验证规则（只写 protected $filters / $vt，仅表单元素）</comment>
  composer xqkeji:element Nickname -c -f "trim,upper" -t "required;email"
  composer xqkeji:element Intro -c -f "striptags,trim,replace=> |-"   <comment># 带参数的过滤器用 名称=>参数</comment>
  composer xqkeji:element Age -c -t "between:min=1|max=99"   <comment># 带选项的验证规则用 规则:名称=值|名称=值</comment>

  <comment># 表格模式下的 select_ 前缀：免选类型、不询问中文名，生成继承 ListSelectModel 的空子类</comment>
  composer xqkeji:use -t
  composer xqkeji:element select_dept -c

  <comment># 修改与删除</comment>
  composer xqkeji:element Username -e -y=Select
  composer xqkeji:element Username -r

<info>要点：</info>

  - 元素名自动转大驼峰（user_name → UserName）；不传 -c/-e/-r 时默认执行创建
  - 名称转蛇形后以 select_ 开头即走 select 约定：表单侧 SelectModel、表格侧 ListSelectModel 的空子类
  - Select 自动 class => 'form-select'；Check/Radio 自动 template => '@check'
  - -f/-t 在表格模式会提示并忽略；未传时类体不写这两个属性（select_/ListSelectModel 空子类也不写）
  - 可选的过滤器类型全表与 $vt 的展开格式见仓库 AGENTS.md

EOF
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // 从原始命令行参数解析，避免 Symfony VALUE_OPTIONAL 吞掉位置参数
        list($name, $action, $specifiedType, $items, $defaultValue, $modelName, $filters, $vt) = $this->parseFromArgv();

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
            $element->editElement($name, $specifiedType, $items, $defaultValue, $modelName, $filters, $vt);
            return 0;
        }

        // 创建操作（默认）
        $element->createElement($name, $specifiedType, $items, $defaultValue, $modelName, $filters, $vt);

        return 0;
    }

    /**
     * 从 $_SERVER['argv'] 解析命令参数
     * 返回 [元素名, 操作(create/edit/remove), 指定类型|null, 项目列表|null, 默认值|null, 模型名|null, 过滤器|null, 验证规则|null]
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
        $filters = null;
        $hasFiltersFlag = false;
        $vt = null;
        $hasVtFlag = false;

        $argv = $_SERVER['argv'] ?? [];
        if (empty($argv)) {
            return [$name, $action, $specifiedType, $items, $defaultValue, $modelName, $filters, $vt];
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

            // 解析 -f / --filters
            if ($token === '-f' || $token === '--filters') {
                $hasFiltersFlag = true;
                $i++;
                // 检查下一个token是否是过滤器值
                if ($i < $count && strpos($tokens[$i], '-') !== 0) {
                    $filters = $tokens[$i];
                    $i++;
                }
                continue;
            }

            // 解析 -f=过滤器 / --filters=过滤器
            if (strpos($token, '-f=') === 0) {
                $hasFiltersFlag = true;
                $filters = substr($token, 3);
                $i++;
                continue;
            }
            if (strpos($token, '--filters=') === 0) {
                $hasFiltersFlag = true;
                $filters = substr($token, 10);
                $i++;
                continue;
            }

            // 解析 -t / --vt
            if ($token === '-t' || $token === '--vt') {
                $hasVtFlag = true;
                $i++;
                // 检查下一个token是否是验证规则值
                if ($i < $count && strpos($tokens[$i], '-') !== 0) {
                    $vt = $tokens[$i];
                    $i++;
                }
                continue;
            }

            // 解析 -t=规则 / --vt=规则
            if (strpos($token, '-t=') === 0) {
                $hasVtFlag = true;
                $vt = substr($token, 3);
                $i++;
                continue;
            }
            if (strpos($token, '--vt=') === 0) {
                $hasVtFlag = true;
                $vt = substr($token, 5);
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

        // -f 不带值 → 设置为空字符串，表示需要交互输入过滤器
        if ($hasFiltersFlag && $filters === null) {
            $filters = '';
        }

        // -t 不带值 → 设置为空字符串，表示需要交互输入验证规则
        if ($hasVtFlag && $vt === null) {
            $vt = '';
        }

        return [$name, $action, $specifiedType, $items, $defaultValue, $modelName, $filters, $vt];
    }
}
