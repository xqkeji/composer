<?php
namespace xqkeji\composer\command;

use Composer\Command\BaseCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use xqkeji\composer\ConfirmTrait;
use xqkeji\composer\Context;
use xqkeji\composer\Lang;

class ActionCommand extends BaseCommand
{
    use NormalizesShortOptions;
    use ConfirmTrait;

    /**
     * 预定义动作列表（统一小写；生成类继承 xqkeji/mvc/action/ 下对应的大驼峰基类）
     */
    private const PREDEFINED_ACTIONS = [
        'add', 'admin', 'captcha', 'change', 'delete', 'display',
        'edit', 'emailcode', 'export', 'getoption', 'login', 'logout',
        'publish', 'reg', 'reset', 'submenu', 'subnode',
        'b_close', 'b_delete', 'b_open', 'b_order',
        'change_password', 'update_config', 'update_statics',
    ];

    protected function configure()
    {
        $this->setName('xqkeji:action')
            ->setDescription('创建动作类')
            ->addArgument('name', InputArgument::REQUIRED, '动作名（统一小写，如 add、b_close、change_password）')
            ->addOption('title', 't', InputOption::VALUE_REQUIRED, '动作中文名称（写入语言文件，如：批量删除）')
            ->setHelp(<<<'EOF'
创建控制器动作类（动作名与中文名写入语言文件，动作键并入 acl）

<info>用法示例：</info>

  <comment># 动作建在【当前控制器】下，先切模块与控制器</comment>
  composer xqkeji:use home
  composer xqkeji:use user_type -c

  composer xqkeji:action add
  composer xqkeji:action b_close -t 批量关闭      <comment># -t 写 title/success/failed/auth 四条语言键</comment>
  composer xqkeji:action admin                    <comment># 会交互式问默认排序 $order 与默认查询条件 $conditions</comment>

<info>要点：</info>

  - 预定义动作名统一小写、大小写不敏感（add / Add / ADD 等价）：命中后继承
    xqkeji\mvc\action\ 下对应基类并生成【空类体】，行为全由基类提供（不要重写 run()）
  - 未收录的自定义动作名继承 xqkeji\mvc\Action，需自行实现 run()，动作名保持原样不规范化
  - 类文件始终是大驼峰：add → Add.php；名含 _ 或 - 时第一段作子目录（b_close → b/Close.php、change_password → change/Password.php）
  - 预定义动作全表、$order/$conditions 的交互与非交互行为见仓库 AGENTS.md

EOF
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $actionName = $input->getArgument('name');

        $context = new Context($this->getIO(), $this->requireComposer());

        // 使用当前上下文中的控制器
        $currentController = $context->getCurrentController();
        if ($currentController === null) {
            $output->writeln('<error>未设置当前控制器，请先使用 composer xqkeji:use -c 切换控制器</error>');
            return 1;
        }

        // 将控制器名转换为大驼峰类名
        $controllerClassName = $this->toCamelCase($currentController);

        // 获取当前模块
        $currentModule = $context->getCurrentModule();
        if ($currentModule === null) {
            $output->writeln('<error>未设置当前模块，请先使用 composer xqkeji:use -- module_name</error>');
            return 1;
        }

        // 获取有效的模块路径（支持本地模块和 composer 模块）
        $modulePath = $context->getValidModulePath();
        if ($modulePath === null) {
            $output->writeln("<error>模块 '{$currentModule}' 无效或不存在，请检查：</error>");
            $output->writeln('  1. 模块是否在 app/ 目录下存在');
            $output->writeln('  2. 模块是否是 composer 模块（通过 config/composer.php 配置）');
            $output->writeln('  3. 模块的 config/acl.php 文件是否存在');
            return 1;
        }

        // 解析动作名：含 _ 或 - 时，第一部分为子目录，其余转大驼峰为类名
        // 预定义动作名统一按小写匹配（add / Add / ADD 等价），并把动作名规范为小写用于类名推导与语言键；
        // 自定义动作保持原始输入，避免改变既有的大驼峰命名习惯
        $isPredefined = in_array(strtolower($actionName), self::PREDEFINED_ACTIONS, true);
        if ($isPredefined) {
            $actionName = strtolower($actionName);
        }

        $parts = preg_split('/[_\-]/', $actionName);
        if (count($parts) > 1) {
            $subDir = $parts[0];
            $className = $this->toCamelCase(implode('_', array_slice($parts, 1)));
        } else {
            $subDir = null;
            $className = $this->toCamelCase($actionName);
        }

        // 构建命名空间和文件路径
        if ($subDir !== null) {
            $namespace = "app\\{$currentModule}\\controller\\{$controllerClassName}\\{$subDir}";
            $actionDir = $modulePath . DIRECTORY_SEPARATOR . 'controller'
                . DIRECTORY_SEPARATOR . $controllerClassName . DIRECTORY_SEPARATOR . $subDir;
            $baseUseClass = "xqkeji\\mvc\\action\\{$subDir}\\{$className}";
        } else {
            $namespace = "app\\{$currentModule}\\controller\\{$controllerClassName}";
            $actionDir = $modulePath . DIRECTORY_SEPARATOR . 'controller'
                . DIRECTORY_SEPARATOR . $controllerClassName;
            $baseUseClass = "xqkeji\\mvc\\action\\{$className}";
        }

        // 确保目录存在
        if (!is_dir($actionDir)) {
            mkdir($actionDir, 0755, true);
        }

        $filePath = $actionDir . DIRECTORY_SEPARATOR . $className . '.php';

        if (is_file($filePath)) {
            $output->writeln("<error>动作文件已存在: $filePath</error>");
            return 1;
        }

        // 生成文件内容
        if ($isPredefined) {
            // Admin 动作：交互式设置默认排序 $order（字段 + asc/desc，可多组）与查询条件 $conditions
            // （每组 字段/操作符/值 三元组，可多条），有设置才写入对应类属性
            $order = [];
            $conditions = [];
            if ($className === 'Admin') {
                $order = $this->collectOrderInteractive();
                $conditions = $this->collectConditionsInteractive();
            }
            $content = $this->generatePredefinedAction($namespace, $className, $baseUseClass, $order, $conditions);
        } else {
            $content = $this->generateCustomAction($namespace, $className);
        }

        file_put_contents($filePath, $content);
        $output->writeln("<info>✓ 动作类已创建: $filePath</info>");

        // 写入语言配置（中文名称）
        $controllerName = $this->toSnakeCase($currentController);
        $controllerTitle = Lang::readControllerTitle($modulePath, $currentModule, $controllerName);
        $title = $input->getOption('title');

        // 动作 -t 中文名统一优先级：-t 显式设置 > 读取 lang > 交互提示用户设置
        $actionTitleKey = "{$currentModule} {$controllerName} {$actionName} title";
        $subject = ($controllerTitle !== null && $controllerTitle !== '') ? $controllerTitle : $controllerClassName;
        $defaultTitle = Lang::actionTitle($actionName, $subject);
        $resolvedTitle = Lang::resolve(
            $this->getIO(),
            $modulePath,
            $actionTitleKey,
            $title,
            "请输入动作 '{$actionName}' 的中文名称（留空使用 '{$defaultTitle}'）：",
            $defaultTitle
        );
        $actionTitles = [$actionName => $resolvedTitle];

        Lang::writeActions(
            $this->getIO(),
            $modulePath,
            $currentModule,
            $controllerName,
            [$actionName],
            $controllerTitle,
            $actionTitles
        );

        return 0;
    }

    /**
     * Admin 动作的排序交互：逐个询问 排序字段 + asc/desc（可多组），留空跳过。
     *
     * 返回 [字段 => 排序方式] 有序映射；未设置/非交互模式返回空数组（调用方据此不写属性）。
     */
    private function collectOrderInteractive(): array
    {
        $io = $this->getIO();
        if (!$io->isInteractive()) {
            return [];
        }

        $order = [];
        $question = '<question>是否设置默认排序 $order？输入排序字段名（如 ordernum，留空跳过）:</question> ';
        while (true) {
            $field = trim((string)$io->ask($question, ''));
            if ($field === '') {
                break;
            }
            if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $field)) {
                $io->write('<error>字段名无效（只能为字母/数字/下划线且字母开头），请重新输入</error>');
                continue;
            }
            $dir = strtolower(trim((string)$io->ask("<question>字段 '{$field}' 的排序方式（asc / desc）:</question> ", 'asc')));
            if (!in_array($dir, ['asc', 'desc'], true)) {
                $io->write("<error>无效排序方式 '{$dir}'，已按 asc 处理</error>");
                $dir = 'asc';
            }
            if (isset($order[$field])) {
                $io->write("<comment>⚠ 字段 '{$field}' 已设置过，本次覆盖为 {$dir}</comment>");
            }
            $order[$field] = $dir;
            if (!$this->confirmIO($io, '<question>是否继续添加下一个排序字段？</question>', false)) {
                break;
            }
        }

        if (empty($order)) {
            $io->write('<comment>⊘ 未设置排序：Admin 类不写入 $order 属性（默认按主基类约定排序）</comment>');
        }
        return $order;
    }

    /**
     * Admin 动作的查询条件交互：循环录入多维数组 $conditions，每条为三元组 [字段, 操作符, 值]。
     *
     * 例：[['pos_id','=',4],['status','=',1]]。字段留空结束；操作符限 = <> > >= < <= like regex；
     * 值输入纯数字按数字写入（int/float），其余按字符串写入。返回三元组列表，未设置返回空数组。
     */
    private function collectConditionsInteractive(): array
    {
        $io = $this->getIO();
        if (!$io->isInteractive()) {
            return [];
        }

        $conditions = [];
        $question = '<question>是否设置默认查询条件 $conditions？输入字段名（如 pos_id，留空跳过）:</question> ';
        while (true) {
            $field = trim((string)$io->ask($question, ''));
            if ($field === '') {
                break;
            }
            if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $field)) {
                $io->write('<error>字段名无效（只能为字母/数字/下划线且字母开头），请重新输入</error>');
                continue;
            }

            $opPrompt = "<question>字段 '{$field}' 的操作符（= / <> / > / >= / < / <= / like / regex）:</question> ";
            $op = strtolower(trim((string)$io->ask($opPrompt, '=')));
            while (!in_array($op, ['=', '<>', '>', '>=', '<', '<=', 'like', 'regex'], true)) {
                $io->write("<error>无效操作符 '{$op}'，仅支持 = <> > >= < <= like regex，请重新输入（留空按 = 处理）</error>");
                $op = strtolower(trim((string)$io->ask($opPrompt, '=')));
                if ($op === '') {
                    $op = '=';
                }
            }

            $value = trim((string)$io->ask("<question>字段 '{$field}' 的查询值（第 3 项，数字按数字、其余按字符串写入）:</question> ", '0'));
            while ($value === '') {
                $io->write('<error>查询值不能为空，请重新输入</error>');
                $value = trim((string)$io->ask("<question>字段 '{$field}' 的查询值:</question> ", '0'));
            }

            $conditions[] = [$field, $op, $value];
            if (!$this->confirmIO($io, '<question>是否继续添加下一个查询条件？</question>', false)) {
                break;
            }
        }

        if (empty($conditions)) {
            $io->write('<comment>⊘ 未设置查询条件：Admin 类不写入 $conditions 属性</comment>');
        }
        return $conditions;
    }

    /**
     * 渲染单个条件值：纯数字 → 数字字面量（整数去前导零/浮点保留），其余 → 字符串字面量。
     */
    private static function renderConditionValue(string $value): string
    {
        if (is_numeric($value)) {
            if (ctype_digit(ltrim($value, '-')) || preg_match('/^-?\d+$/', $value)) {
                return (string)(int)$value;
            }
            return var_export((float)$value, true);
        }
        return var_export($value, true);
    }

    /**
     * 生成预定义动作类（继承基类动作，空类体，行为完全由基类动作提供）
     *
     * $order 非空时在类体写入 protected $order = ['字段' => 'asc|desc', ...];（Admin 默认排序）；
     * $conditions 非空时写入 protected $conditions = [[字段, 操作符, 值], ...];（Admin 默认查询条件）。
     * 不生成 run() 重写：动作行为完全由基类 xqkeji\mvc\action\* 提供，子类空类体即可（宿主既有动作类同此约定）。
     */
    private function generatePredefinedAction(string $namespace, string $className, string $baseUseClass, array $order = [], array $conditions = []): string
    {
        $body = '';
        if (!empty($order)) {
            $pairs = [];
            foreach ($order as $field => $dir) {
                $pairs[] = "'{$field}' => '{$dir}'";
            }
            $body .= "    protected \$order = [" . implode(', ', $pairs) . "];\n";
        }

        if (!empty($conditions)) {
            $items = [];
            foreach ($conditions as $c) {
                [$field, $op, $value] = $c;
                $items[] = "['{$field}', '{$op}', " . self::renderConditionValue((string)$value) . ']';
            }
            $body .= "    protected \$conditions = [" . implode(', ', $items) . "];\n";
        }

        return <<<PHP
<?php
namespace {$namespace};

use {$baseUseClass} as BaseAction;

class {$className} extends BaseAction
{
{$body}}

PHP;
    }

    /**
     * 生成自定义动作类（继承 Action 基类，实现 run 方法）
     */
    private function generateCustomAction(string $namespace, string $className): string
    {
        return <<<PHP
<?php
namespace {$namespace};

use xqkeji\mvc\Action;

class {$className} extends Action
{
    public function run()
    {

    }
}

PHP;
    }

    /**
     * 将下划线命名转换为大驼峰命名
     */
    private function toCamelCase(string $string): string
    {
        return str_replace(' ', '', ucwords(str_replace('_', ' ', $string)));
    }

    /**
     * 将驼峰命名转换为小写下划线命名
     */
    private function toSnakeCase(string $string): string
    {
        $result = preg_replace('/([A-Z]+)([A-Z][a-z])/', '$1_$2', $string);
        $result = preg_replace('/([a-z\d])([A-Z])/', '$1_$2', $result);
        return strtolower($result);
    }
}
