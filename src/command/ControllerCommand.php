<?php
namespace xqkeji\composer\command;

use Composer\Command\BaseCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use xqkeji\composer\Controller;

class ControllerCommand extends BaseCommand
{
    protected function configure()
    {
        $this->setName('xqkeji:controller')
            ->setDescription('创建控制器')
            ->addArgument('name', InputArgument::REQUIRED, '控制器名称')
            ->addArgument('auth_entry', InputArgument::OPTIONAL, '权限入口（默认 guest）', 'guest')
            ->addArgument('actions', InputArgument::OPTIONAL | InputArgument::IS_ARRAY, '动作列表（默认为空）', []);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $controller = new Controller($this->getIO(), $this->requireComposer());
        
        $name = $input->getArgument('name');
        $authEntry = $input->getArgument('auth_entry');
        $actions = $input->getArgument('actions');
        
        $controller->createController($name, $authEntry, $actions);
        
        return 0;
    }
}
