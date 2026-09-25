<?php
namespace xqkeji\composer\command;

use Composer\Command\BaseCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use xqkeji\composer\Context;
use xqkeji\composer\Lang;

class ActionCommand extends BaseCommand
{
    use NormalizesShortOptions;

    /**
     * 预定义动作列表（继承 xqkeji/mvc/action/ 下的动作类）
     */
    private const PREDEFINED_ACTIONS = [
        'Add', 'Admin', 'Captcha', 'Change', 'Delete', 'Display',
        'Edit', 'Emailcode', 'Export', 'Getoption', 'Login', 'Logout',
        'Publish', 'Reg', 'Reset', 'Submenu', 'Subnode',
        'b_close', 'b_delete', 'b_open', 'b_order',
        'change_password', 'update_config', 'update_statics',
    ];

    protected function configure()
    {
        $this->setName('xqkeji:action')
            ->setDescription('创建动作类')
            ->addArgument('name', InputArgument::REQUIRED, '动作名（如 Add、b_close、change_password）')
            ->addOption('title', 't', InputOption::VALUE_REQUIRED, '动作中文名称（写入语言文件，如：批量删除）')
            ->setHelp(<<<'EOF'
创建动作类文件

<info>用法示例：</info>

  <comment># 先切换到目标模块和控制器</comment>
  composer xqkeji:use home
  composer xqkeji:use user_type -c

  <comment># 在当前控制器下创建预定义动作（自动继承对应基类）</comment>
  composer xqkeji:action Add
  composer xqkeji:action Delete
  composer xqkeji:action b_close

  <comment># 创建 Admin 动作：交互式设置默认排序 $order（如 ordernum + asc → protected $order = ['ordernum' => 'asc'];）与默认查询条件 $conditions（如 pos_id = 4 → protected $conditions = [['pos_id', '=', 4]];）</comment>
  composer xqkeji:action Admin

  <comment># 指定中文名称，写入 lang/zh_cn.php（四种写法等价）</comment>
  composer xqkeji:action b_close -t 批量关闭
  composer xqkeji:action b_close -t=批量关闭
  composer xqkeji:action b_close -t批量关闭
  composer xqkeji:action b_close --title=批量关闭

<info>选项：</info>

  <comment>-t, --title=TITLE</comment>  动作中文名称，写入语言文件的 title/success/failed/auth 键

<info>预定义动作列表及中文名：</info>

  Add 添加, Admin 管理, Captcha 验证码, Change 修改, Delete 删除, Display 查看,
  Edit 编辑, Emailcode 邮箱验证码, Export 导出, Getoption 获取选项, Login 登录,
  Logout 退出登录, Publish 发布, Reg 注册, Reset 重置, Submenu 子菜单,
  Subnode 子节点, b_close 批量禁用, b_delete 批量删除, b_open 批量启用,
  b_order 批量排序, change_password 修改密码, update_config 更新配置,
  update_statics 更新静态文件

<info>说明：</info>

  - 动作创建在当前上下文指定的控制器下进行，请先使用 xqkeji:use -c 切换控制器
  - Admin 动作创建时会交互式询问【默认排序 \$order】：逐个输入排序字段名（如 ordernum，留空跳过）与该字段的排序方式（asc / desc，默认 asc），可继续添加多个字段（覆盖同名重复设置）；有设置则在类体写入 protected \$order = ['字段' => 'asc|desc', ...];，未设置则不写入该属性；非交互模式直接跳过
  - Admin 动作还会交互式询问【默认查询条件 \$conditions】：逐个输入三元组——字段名（如 pos_id，留空跳过）、操作符（= / <> / > / >= / < / <= / like / regex，默认 =）、值（纯数字按数字写入，其余按字符串写入），可继续添加多个条件；有设置则在类体写入 protected \$conditions = [['字段', '操作符', 值], ...];（如 [['pos_id', '=', 4], ['status', '=', 1]]），未设置则不写入该属性；非交互模式直接跳过
  - 预定义动作继承 xqkeji\mvc\action\ 下对应的动作基类，生成空类体（行为完全由基类提供，无需重写 run()）
  - 其他动作名继承 xqkeji\mvc\Action 基类，需自行实现 run() 方法
  - 动作名含 _ 或 - 时，第一部分变为子目录名，其余转为大驼峰作为类名
    例：b_close → b/Close.php，change_password → change/Password.php

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
        $parts = preg_split('/[_\-]/', $actionName);
        if (count($parts) > 1) {
            $subDir = $parts[0];
            $className = $this->toCamelCase(implode('_', array_slice($parts, 1)));
        } else {
            $subDir = null;
            $className = $this->toCamelCase($actionName);
        }

        // 判断是否为预定义动作
        $isPredefined = in_array($actionName, self::PREDEFINED_ACTIONS, true);

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
            if (!$io->confirm('<question>是否继续添加下一个排序字段？</question>', false)) {
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
            if (!$io->confirm('<question>是否继续添加下一个查询条件？</question>', false)) {
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
