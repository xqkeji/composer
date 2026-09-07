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
            ->addOption('entry', 'e', InputOption::VALUE_REQUIRED, '权限入口（默认 admin）', 'admin')
            ->addOption('auth', 'a', InputOption::VALUE_REQUIRED, '权限类型：auth（需要授权）或 login（需要登录），仅非 guest 入口有效（默认 auth）', 'auth')
            ->addOption('actions', 'A', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, '动作列表，可多次指定或用逗号分隔（默认 admin,add,edit,delete,change,b_delete）')
            ->addOption('title', 't', InputOption::VALUE_REQUIRED, '控制器中文名称（写入菜单与语言文件）')
            ->addOption('file', 'f', InputOption::VALUE_NONE, '创建控制器实体文件（默认不创建，使用虚拟控制器）')
            ->setHelp(<<<'EOF'
创建控制器并配置权限、菜单和语言

<info>用法：</info>
  composer xqkeji:controller <控制器名> [选项]

<info>选项：</info>
  <comment>-e, --entry=ENTRY</comment>      权限入口：admin（默认）、member、guest 或自定义
  <comment>-a, --auth=AUTH</comment>        权限类型：auth（默认，需授权）或 login（需登录），guest 入口忽略
  <comment>-A, --actions=ACTIONS</comment>  动作列表，可多次指定或用逗号分隔
  <comment>-t, --title=TITLE</comment>      控制器中文名称，用于菜单标题与语言文件
  <comment>-f, --file</comment>             生成控制器实体文件（默认不生成，使用虚拟控制器）

<info>默认动作及中文名：</info>
  admin 管理    add 添加    edit 编辑    delete 删除    change 修改    b_delete 批量删除

<info>用法示例：</info>

  <comment># admin 入口，默认动作</comment>
  composer xqkeji:controller term

  <comment># 指定中文名：菜单「学期管理」，语言键生成「添加学期」等</comment>
  composer xqkeji:controller term -t 学期

  <comment># 自定义入口与权限类型</comment>
  composer xqkeji:controller course -e admin -a login
  composer xqkeji:controller profile -e member -a login -t 个人中心

  <comment># 指定动作列表（两种写法等价）</comment>
  composer xqkeji:controller loger -A admin -A delete
  composer xqkeji:controller loger -A admin,delete

  <comment># guest 入口（默认 index 动作，不写菜单）</comment>
  composer xqkeji:controller home -e guest

  <comment># 同时生成控制器实体文件</comment>
  composer xqkeji:controller term -t 学期 -f

<info>权限规则：</info>

  - guest 入口：直接把控制器和动作写入 ACL，不区分 auth/login
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

        $authEntry = (string) $input->getOption('entry');
        $authType = (string) $input->getOption('auth');
        $title = $input->getOption('title');
        $createFile = (bool) $input->getOption('file');

        // 验证 auth_type
        if (!in_array($authType, ['auth', 'login'], true)) {
            $output->writeln('<error>-a/--auth 只能是 auth 或 login</error>');
            return 1;
        }

        // 动作列表支持 -A a -A b 与 -A a,b 两种写法
        $actions = [];
        foreach ((array) $input->getOption('actions') as $item) {
            foreach (explode(',', (string) $item) as $action) {
                $action = trim($action);
                if ($action !== '') {
                    $actions[] = $action;
                }
            }
        }

        $controller = new Controller($this->getIO(), $this->requireComposer());
        $controller->createController($name, $authEntry, $authType, $actions, $createFile, $title);

        return 0;
    }
}
