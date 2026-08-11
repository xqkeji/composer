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
    protected function configure()
    {
        $this->setName('xqkeji:module')
            ->setDescription('创建模块')
            ->addArgument('name', InputArgument::OPTIONAL, '模块名称或包名 (如: home 或 xqkeji/xq-app-home)')
            ->addOption('path', 'p', InputOption::VALUE_REQUIRED, '本地路径（创建 composer 包模块时必须指定）')
            ->setHelp(<<<'EOF'
创建新模块

<info>用法示例：</info>

  <comment># 创建本地模块到 app/ 目录</comment>
  composer xqkeji:module home

  <comment># 创建 composer 包模块到指定目录（支持 --path、-path 或 -p）</comment>
  composer xqkeji:module xqkeji/xq-app-home --path=F:/docker/code/php/
  composer xqkeji:module xqkeji/xq-app-home -path=F:/docker/code/php/
  composer xqkeji:module xqkeji/xq-app-home -p=F:/docker/code/php/

  <comment># 选项可以放在任意位置</comment>
  composer xqkeji:module --path=F:/docker/code/php/ xqkeji/xq-app-home

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
        $module->createModule($name, $path);
        
        return 0;
    }
}
