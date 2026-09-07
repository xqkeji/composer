<?php
namespace xqkeji\composer\command;

use Composer\Command\BaseCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use xqkeji\composer\Controller;

class ControllerCommand extends BaseCommand
{
    protected function configure()
    {
        $this->setName('xqkeji:controller')
            ->setDescription('创建控制器')
            ->addArgument('name', InputArgument::OPTIONAL, '控制器名称')
            ->addArgument('auth_entry', InputArgument::OPTIONAL, '权限入口（默认 guest）', 'guest')
            ->addArgument('auth_type', InputArgument::OPTIONAL, '权限类型：auth（需要授权）或 login（需要登录），仅非 guest 入口有效（默认 auth）', 'auth')
            ->addArgument('actions', InputArgument::OPTIONAL | InputArgument::IS_ARRAY, '动作列表（admin 等入口默认：admin, add, edit, delete, change, b_delete；guest 默认：index）', [])
            ->addOption('file', 'f', InputOption::VALUE_NONE, '创建控制器实体文件（默认不创建，使用虚拟控制器）')
            ->setHelp(<<<'EOF'
创建控制器并配置权限、菜单和语言

<info>用法示例：</info>

  <comment># 创建控制器（guest 入口，默认 index 动作，使用虚拟控制器）</comment>
  composer xqkeji:controller home

  <comment># 创建控制器（admin 入口，默认动作：admin, add, edit, delete, change, b_delete）</comment>
  composer xqkeji:controller customer_type admin

  <comment># 创建控制器（admin 入口，指定动作）</comment>
  composer xqkeji:controller customer_type admin auth admin add edit

  <comment># 同时生成控制器实体文件（低代码默认走虚拟控制器，不需要实体文件）</comment>
  composer xqkeji:controller term admin --file

  <comment># 创建控制器（member 入口，login 类型）</comment>
  composer xqkeji:controller profile member login edit update

  <comment># 创建控制器（自定义入口如 teacher）</comment>
  composer xqkeji:controller course teacher auth list view

<info>参数说明：</info>

  <comment>name</comment>        控制器名称（支持大小写，自动转为大驼峰，如 Home、user_type、UserType）
  <comment>auth_entry</comment>  权限入口：guest（访客）、admin（管理员）、member（会员）或自定义
  <comment>auth_type</comment>   权限类型：auth（需要授权）或 login（需要登录），guest 入口忽略此参数
  <comment>actions</comment>     动作列表，可指定多个，不指定时使用默认动作列表

<info>选项说明：</info>

  <comment>-f, --file</comment>  是否生成控制器实体文件（默认否，交由虚拟控制器处理）

<info>权限规则：</info>

  - guest 入口：直接添加控制器和动作，不区分 auth/login
  - admin 入口：更新 ACL、menu 和 lang 配置
  - 其他入口（member、teacher 等）：只更新 ACL，不处理 menu 和 lang

EOF
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $name = $input->getArgument('name');
        
        // 如果没有提供控制器名，显示帮助信息
        if (empty($name)) {
            $output->writeln($this->getSynopsis(true));
            $output->writeln('');
            $output->writeln($this->getProcessedHelp());
            return 0;
        }
        
        $controller = new Controller($this->getIO(), $this->requireComposer());
        $authEntry = $input->getArgument('auth_entry');
        $authType = $input->getArgument('auth_type');
        $actions = $input->getArgument('actions');
        $createFile = (bool) $input->getOption('file');
        
        // 验证 auth_type
        if (!in_array($authType, ['auth', 'login'])) {
            $output->writeln('<error>auth_type 必须是 auth 或 login</error>');
            return 1;
        }
        
        $controller->createController($name, $authEntry, $authType, $actions, $createFile);
        
        return 0;
    }
}
