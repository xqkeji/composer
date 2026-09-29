<?php
namespace xqkeji\composer\command;

use Composer\Command\BaseCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use xqkeji\composer\Context;

class UseCommand extends BaseCommand
{
    use NormalizesShortOptions;

    protected function configure()
    {
        $this->setName('xqkeji:use')
            ->setDescription('切换当前模块、控制器或模式')
            ->addArgument('name', InputArgument::OPTIONAL, '模块或控制器名称')
            ->addOption('module', 'm', InputOption::VALUE_NONE, '切换模块（默认）')
            ->addOption('controller', 'c', InputOption::VALUE_NONE, '切换控制器')
            ->addOption('form', 'f', InputOption::VALUE_NONE, '设置为表单模式')
            ->addOption('table', 't', InputOption::VALUE_NONE, '设置为表格模式')
            ->setHelp(<<<'EOF'
切换当前工作上下文（模块、控制器或模式）

<info>用法示例：</info>

  <comment># 切换到模块</comment>
  composer xqkeji:use home
  composer xqkeji:use home -m

  <comment># 切换到控制器（支持小写加下划线，自动转换为大驼峰）</comment>
  composer xqkeji:use user_type -c
  composer xqkeji:use UserType -c

  <comment># 设置为表单模式（xqkeji:element 创建表单元素）</comment>
  composer xqkeji:use -f

  <comment># 设置为表格模式（xqkeji:element 创建表格元素）</comment>
  composer xqkeji:use -t

  <comment># 显示当前上下文</comment>
  composer xqkeji:use

<info>说明：</info>

  - 不带参数时显示当前上下文
  - 默认切换模块（-m 可省略）
  - 使用 -c 切换控制器
  - 使用 -f 设置为表单模式，-t 设置为表格模式
  - 控制器名称支持小写加下划线（如 user_type），会自动转换为大驼峰命名（UserType）
  - 切换控制器前必须先切换到模块

EOF
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $context = new Context($this->getIO(), $this->requireComposer());
        
        $name = $input->getArgument('name');
        $isController = $input->getOption('controller');
        $isForm = $input->getOption('form');
        $isTable = $input->getOption('table');
        
        // 如果没有参数，显示当前上下文
        if ($name === null && !$isForm && !$isTable) {
            $context->showContext();
            return 0;
        }
        
        // 设置表单模式
        if ($isForm) {
            $success = $context->switchMode('form');
            return $success ? 0 : 1;
        }
        
        // 设置表格模式
        if ($isTable) {
            $success = $context->switchMode('table');
            return $success ? 0 : 1;
        }
        
        // 切换控制器
        if ($isController) {
            $success = $context->switchController($name);
            return $success ? 0 : 1;
        }
        
        // 切换模块（默认）
        $success = $context->switchModule($name);
        return $success ? 0 : 1;
    }
}
