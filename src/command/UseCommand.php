<?php
namespace xqkeji\composer\command;

use Composer\Command\BaseCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use xqkeji\composer\Module;

class UseCommand extends BaseCommand
{
    protected function configure()
    {
        $this->setName('xqkeji:use')
            ->setDescription('切换到指定模块')
            ->addArgument('module', InputArgument::REQUIRED, '模块名称');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $module = new Module($this->getIO(), $this->requireComposer());
        
        $moduleName = $input->getArgument('module');
        $module->useModule($moduleName);
        
        return 0;
    }
}
