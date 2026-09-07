<?php
namespace xqkeji\composer\command;

use Composer\Command\BaseCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use xqkeji\composer\Module;

class ModuleCommand extends BaseCommand
{
    use NormalizesShortOptions;

    protected function configure()
    {
        $this->setName('xqkeji:module')
            ->setDescription('创建模块')
            ->addArgument('name', InputArgument::OPTIONAL, '模块名称或包名 (如: home 或 xqkeji/xq-app-home)')
            ->addOption('path', 'p', InputOption::VALUE_REQUIRED, '本地路径（创建 composer 包模块时必须指定）')
            ->addOption('title', 't', InputOption::VALUE_REQUIRED, '模块中文名称（写入菜单与语言文件）')
            ->setHelp(<<<'EOF'
创建新模块

<info>用法示例：</info>

  <comment># 创建本地模块到 app/ 目录</comment>
  composer xqkeji:module home

  <comment># 创建 composer 包模块到指定目录（--path / -p 三种写法等价）</comment>
  composer xqkeji:module xqkeji/xq-app-home --path=F:/docker/code/php/
  composer xqkeji:module xqkeji/xq-app-home -p=F:/docker/code/php/
  composer xqkeji:module xqkeji/xq-app-home -p F:/docker/code/php/

  <comment># 指定模块中文名：菜单与语言文件写入「教学管理」</comment>
  composer xqkeji:module xqkeji/xq-app-edu -p F:/docker/code/php/ -t 教学
  composer xqkeji:module xqkeji/xq-app-edu -p=F:/docker/code/php/ -t=教学

  <comment># 选项可以放在任意位置</comment>
  composer xqkeji:module --path=F:/docker/code/php/ xqkeji/xq-app-home

<info>选项：</info>

  <comment>-p, --path=PATH</comment>    本地路径（创建 composer 包模块时必须指定）
  <comment>-t, --title=TITLE</comment>  模块中文名称，写入 menu.php 与 lang/zh-cn.php

<info>参数写法（四种等价）：</info>

  composer xqkeji:module xq-app-edu -t=教学
  composer xqkeji:module xq-app-edu -t 教学
  composer xqkeji:module xq-app-edu -t教学
  composer xqkeji:module xq-app-edu --title=教学

<info>说明：</info>

  - 本地模块（如 home）：创建到当前项目的 app/ 目录
  - composer 包模块（如 xqkeji/xq-app-home）：必须指定 --path 参数
  - composer 包模块会在指定路径创建包目录，并自动软链接到 vendor
  - 模块名只能包含小写字母、数字和下划线
  - composer 包模块会从包名提取模块名（xq-app-home → home）
  - 创建后自动设置为当前模块

EOF
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $name = $input->getArgument('name');
        
        // 如果没有提供模块名，显示帮助信息
        if (empty($name)) {
            $output->writeln($this->getSynopsis(true));
            $output->writeln('');
            $output->writeln($this->getProcessedHelp());
            return 0;
        }
        
        $path = $input->getOption('path');
        
        // 兼容 -path=xxx 和 -p=xxx 写法（Symfony 短选项不处理 = 分隔符）
        if ($path !== null) {
            if (preg_match('/^ath[=](.+)$/i', $path, $m)) {
                $path = $m[1];
            } elseif (str_starts_with($path, '=')) {
                $path = substr($path, 1);
            }
        }
        
        // 标准化路径分隔符
        if ($path !== null) {
            $path = str_replace('\\', '/', $path);
            $path = rtrim($path, '/');
        }
        
        // 判断是本地模块还是 composer 包模块
        $isComposerPackage = strpos($name, '/') !== false;
        
        // composer 包模块必须指定 --path
        if ($isComposerPackage && empty($path)) {
            $output->writeln('<error>创建 composer 包模块时必须指定本地路径</error>');
            $output->writeln('');
            $output->writeln('<info>示例（以下三种写法均可）：</info>');
            $output->writeln("  composer xqkeji:module {$name} --path=本地电脑路径");
            $output->writeln("  composer xqkeji:module {$name} -path=本地电脑路径");
            $output->writeln("  composer xqkeji:module {$name} -p=本地电脑路径");
            return 1;
        }
        
        $module = new Module($this->getIO(), $this->requireComposer());
        $module->createModule($name, $path, $input->getOption('title'));
        
        return 0;
    }
}
