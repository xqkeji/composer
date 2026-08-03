<?php
namespace xqkeji\composer\command;

use Composer\Command\BaseCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use xqkeji\composer\Module;

class ModuleCommand extends BaseCommand
{
    protected function configure()
    {
        $this->setName('xqkeji:module')
            ->setDescription('创建模块')
            ->addArgument('name', InputArgument::REQUIRED, '模块名称或包名 (如: home 或 xqkeji/xq-app-home)')
            ->addArgument('target', InputArgument::OPTIONAL, '目标路径');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $module = new Module($this->getIO(), $this->requireComposer());
        
        $name = $input->getArgument('name');
        $target = $input->getArgument('target');
        
        $module->createModule($name, $target);
        
        return 0;
    }
}
