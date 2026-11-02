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
切换当前工作上下文（模块 / 控制器 / 模式），多数生成命令依赖它

<info>用法示例：</info>

  composer xqkeji:use                 <comment># 不带参数 = 显示当前上下文</comment>
  composer xqkeji:use home            <comment># 切模块（-m 是默认，可省略）</comment>
  composer xqkeji:use user_type -c    <comment># 切控制器（user_type / UserType 等价，自动转大驼峰）</comment>
  composer xqkeji:use -f              <comment># 表单模式；-t 为表格模式（决定 xqkeji:element 落到哪个目录）</comment>

<info>要点：</info>

  - 上下文持久化在 runtime/composer/context.php：当前模块、当前控制器、当前模式（form|table）
  - 切控制器前要先有当前模块；-f/-t/-c 同时给出时只执行一件，顺序是表单模式 → 表格模式 → 控制器
  - xqkeji:form 建完自动切到表单模式，xqkeji:table 建完自动切到表格模式

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
