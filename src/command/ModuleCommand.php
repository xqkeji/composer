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
创建新模块（本地 app/ 模块或 composer 包模块），建完自动设为当前模块

<info>用法示例：</info>

  <comment># 本地模块 → 当前项目的 app/{模块名}/</comment>
  composer xqkeji:module home

  <comment># composer 包模块：包名写法必须带 -p 指定包根目录，创建后自动软链接进 vendor</comment>
  composer xqkeji:module xqkeji/xq-app-home -p F:/docker/code/php/

  <comment># -t 中文名写入 menu.php 与 lang/zh_cn.php</comment>
  composer xqkeji:module xqkeji/xq-app-edu -p F:/docker/code/php/ -t 教学

<info>要点：</info>

  - 模块名只能是小写字母、数字、下划线；包名会提取模块名（xqkeji/xq-app-home → home）
  - 短选项写法 -t值 / -t 值 / -t=值 / --title=值 等价，选项可放在任意位置
  - 包模块做好后要让它被宿主以本地路径安装，用 xqkeji:path（见该命令帮助）

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
