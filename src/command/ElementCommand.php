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
            ->addOption('filters', 'f', InputOption::VALUE_OPTIONAL, '过滤器（仅表单元素，写入 protected $filters）：多个用逗号分隔，带参数用 名称=>参数1|参数2，如 string / trim,upper / replace=> |-；不带值则交互输入（默认 string）；可选类型：absint,alnum,alpha,bool,email,float,int,lower,lowerFirst,regex,remove,replace,special,specialFull,string,striptags,trim,upper,upperFirst,upperWords,url,html')
            ->addOption('vt', 't', InputOption::VALUE_OPTIONAL, '验证规则（仅表单元素，写入 protected $vt）：多条用分号分隔，规则参数用冒号分隔，如 required / required;length:3,20 / $confirm；不带值则交互输入（留空不设置）')
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

  <comment># 创建元素并设置过滤器与验证规则（仅表单元素）</comment>
  composer xqkeji:element Age -c -y=Number -f=int -t=required
  composer xqkeji:element Nickname -c -f "trim,upper" -t "required;length:3,20"
  <comment># replace 带两个参数（参数间用 | 分隔，这里表示把空格替换为 -）</comment>
  composer xqkeji:element Intro -c -f "striptags,trim,replace=> |-" -t required

  <comment># 只传 -f / -t 不带值：交互输入（过滤器默认 string，验证规则留空即不设置）</comment>
  composer xqkeji:element Email -c -y=Email -f -t

  <comment># 表格模式下创建 select_ 前缀元素（免选类型、不询问中文名，生成继承 ListSelectModel 的空子类）</comment>
  composer xqkeji:use -t
  composer xqkeji:element select_dept -c

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
  - 创建/修改前需要先使用 xqkeji:use -f（表单模式）或 -t（表格模式）设置当前模式
  - 不指定 -c/-e/-r 时，默认为创建操作
  - 使用 -y 不带值会强制弹出类型选择列表（交互模式）
  - 使用 -y=类型名 直接指定类型
  - 使用 -l 指定项目列表（Select/Check/Radio 类型），格式：值1|文本1,值2|文本2 或 值1=文本1,值2=文本2
  - 使用 -D 指定默认值
  - 使用 -m 指定模型名（Select/Check/Radio），从模型动态加载项目列表（需交互设置字段名）
  - Select 类型自动使用 class => 'form-select'
  - Check/Radio 类型自动使用 template => '@check'
  - 使用 -f/--filters 设置过滤器（仅表单元素）：写入 protected $filters = [...];，多个用逗号分隔（如 trim,upper），带参数的过滤器用 名称=>参数1|参数2（如 replace=> |-、regex=>/^a$/、remove=>ab）；可选值：absint、alnum、alpha、bool、email、float、int、lower、lowerFirst、regex、remove、replace、special、specialFull、string、striptags、trim、upper、upperFirst、upperWords、url、html；只传 -f 不带值时交互输入（直接回车默认 string）
  - 使用 -t/--vt 设置验证规则（仅表单元素）：写入 protected $vt = [[...], ...];，多条规则用分号分隔，规则参数用冒号分隔（如 required;length:3,20 → [ ['required'], ['length', '3,20'] ]）；前端规则可带 $ 前缀（如 $confirm）；只传 -t 不带值时交互输入，留空则不写该属性
  - -f / -t 未传时生成的元素类不含 $filters / $vt 属性（保持原有行为）；表格模式（xqkeji:use -t）下传 -f/-t 会提示并忽略；表格 select_ 前缀与 ListSelectModel 仍生成空子类，不写这两个属性
  - 表格元素类型列表含 ListSelectModel：显式 -y=ListSelectModel 时生成继承 xqkeji\form\element\ListSelectModel 的空子类（无 $name/$text/$attrs 属性、不询问中文名）
  - 表格模式下的 select_ 前缀约定：元素名转蛇形后以 select_ 开头（如 select_dept / SelectDept）时免选类型、不询问中文名，直接生成继承 ListSelectModel 的空子类；-e 修改时同样覆盖重写为空子类
  - 删除操作会要求确认

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
