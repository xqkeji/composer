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
  - 预定义动作继承 xqkeji\mvc\action\ 下对应的动作基类，并重载 run() 方法调用 parent::run()
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
            $content = $this->generatePredefinedAction($namespace, $className, $baseUseClass);
        } else {
            $content = $this->generateCustomAction($namespace, $className);
        }

        file_put_contents($filePath, $content);
        $output->writeln("<info>✓ 动作类已创建: $filePath</info>");

        // 写入语言配置（中文名称）
        $controllerName = $this->toSnakeCase($currentController);
        $controllerTitle = Lang::readControllerTitle($modulePath, $currentModule, $controllerName);
        $title = $input->getOption('title');
        Lang::writeActions(
            $this->getIO(),
            $modulePath,
            $currentModule,
            $controllerName,
            [$actionName],
            $controllerTitle,
            ($title !== null && $title !== '') ? [$actionName => $title] : []
        );

        return 0;
    }

    /**
     * 生成预定义动作类（继承基类动作，重载 run 方法）
     */
    private function generatePredefinedAction(string $namespace, string $className, string $baseUseClass): string
    {
        return <<<PHP
<?php
namespace {$namespace};

use {$baseUseClass} as BaseAction;

class {$className} extends BaseAction
{
    public function run()
    {
        parent::run();
    }
}

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
