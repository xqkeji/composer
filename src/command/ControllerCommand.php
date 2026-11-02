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
    use NormalizesShortOptions;

    protected function configure()
    {
        $this->setName('xqkeji:controller')
            ->setDescription('创建控制器')
            ->addArgument('name', InputArgument::OPTIONAL, '控制器名称')
            ->addOption('entry', 'e', InputOption::VALUE_REQUIRED, '权限入口（默认 admin）', 'admin')
            ->addOption('auth', 'a', InputOption::VALUE_REQUIRED, '权限类型：auth（需要授权）或 login（需要登录），仅非 guest 入口有效（默认 auth）', 'auth')
            ->addOption('actions', 'A|s', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, '动作列表，可多次指定或用逗号分隔（默认 admin,add,edit,delete,change,b_delete）')
            ->addOption('title', 't', InputOption::VALUE_REQUIRED, '控制器中文名称（写入菜单与语言文件）')
            ->addOption('icon', 'i', InputOption::VALUE_REQUIRED, '菜单图标类样式（默认按中文名/控制器名关键词自动推荐，如 bi-person）')
            ->addOption('file', 'f', InputOption::VALUE_NONE, '创建控制器实体文件（默认不创建，使用虚拟控制器）')
            ->setHelp(<<<'EOF'
创建控制器并初始化 acl.php / menu.php / zh_cn.php（默认虚拟控制器，不落地实体文件）

<info>用法示例：</info>

  <comment># admin 入口 + 默认动作集；-t 中文名进菜单与语言文件</comment>
  composer xqkeji:controller term -t 学期

  <comment># 菜单图标按名字自动推荐（学生 → bi-mortarboard）；-i 覆盖推荐</comment>
  composer xqkeji:controller student -t 学生
  composer xqkeji:controller term -t 学期 -i calendar3

  <comment># 其它入口 / 只放行的动作 / guest 前台入口</comment>
  composer xqkeji:controller profile -e member -a login -t 个人中心
  composer xqkeji:controller loger -A admin,delete
  composer xqkeji:controller home -e guest

  <comment># 需要控制器实体文件 controller/{Class}.php 时才加 -f</comment>
  composer xqkeji:controller term -t 学期 -f

<info>要点：</info>

  - 默认动作集：admin 管理、add 添加、edit 编辑、delete 删除、change 修改、b_delete 批量删除
  - 入口决定写哪些配置：admin 写 acl+menu+lang；member 等其它入口只写 acl；guest 只写 acl、默认 index 动作、不写菜单
  - -a 只有 auth / login 两个值，对 guest 入口无效；短参数区分大小写：-A 与 -s 都是 --actions，-a 是 --auth
  - 虚拟控制器：acl.php 里有该控制器与动作定义即按约定生效；图标推荐优先级与词表见仓库 AGENTS.md

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
        $icon = (string) $input->getOption('icon');
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
        $controller->createController($name, $authEntry, $authType, $actions, $createFile, $title, $icon);

        return 0;
    }
}
