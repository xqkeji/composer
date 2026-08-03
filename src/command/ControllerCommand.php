<?php
namespace xqkeji\composer\command;

use Composer\Command\BaseCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use xqkeji\composer\Module;

class ControllerCommand extends BaseCommand
{
    protected function configure()
    {
        $this->setName('xqkeji:controller')
            ->setDescription('创建控制器')
            ->addArgument('name', InputArgument::REQUIRED, '控制器名称')
            ->addArgument('action', InputArgument::OPTIONAL, '动作名称');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $module = new Module($this->getIO(), $this->requireComposer());
        
        $name = $input->getArgument('name');
        $action = $input->getArgument('action');
        
        $module->createController($name, $action);
        
        return 0;
    }
}
