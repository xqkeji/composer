<?php
namespace xqkeji\composer\command;

use Composer\Command\BaseCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use xqkeji\composer\Module;

class RemoveCommand extends BaseCommand
{
    protected function configure()
    {
        $this->setName('xqkeji:remove')
            ->setDescription('删除 composer 模块')
            ->addArgument('name', InputArgument::REQUIRED, '模块名称或包名')
            ->addOption('path', 'p', InputOption::VALUE_REQUIRED, '本地包目录路径（删除后是否清理）')
            ->addOption('force', 'f', InputOption::VALUE_NONE, '强制删除，不询问确认')
            ->setHelp(<<<'EOF'
删除 composer 模块

<info>用法示例：</info>

  <comment># 删除 composer 模块（会清理所有相关配置）</comment>
  composer xqkeji:remove xqkeji/xq-app-home

  <comment># 删除并清理本地源代码包目录（-p 指定包所在父目录）</comment>
  composer xqkeji:remove xqkeji/xq-app-home --path=F:/docker/code/php

  <comment># 强制删除，不询问确认</comment>
  composer xqkeji:remove xqkeji/xq-app-home --force

<info>说明：</info>

  - 该命令会完整清理 composer 模块的所有痕迹
  - 清理项目 composer.json 中的 path repository 配置
  - 清理项目 composer.json 中的 require 依赖
  - 清理 config/composer.php 中的模块映射
  - 删除 vendor 中的软链接
  - 如果指定 --path，会验证本地包目录的 composer.json 包名，匹配后删除源代码包目录（危险操作）
  - 包名不匹配时会警告并要求单独确认，--force 无法跳过此安全确认

<info>与 composer remove 的区别：</info>

  - composer remove 只会移除 require 依赖和 vendor 中的包
  - xqkeji:remove 会额外清理 path repository、config/composer.php 和本地目录

EOF
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $name = $input->getArgument('name');
        $localPath = $input->getOption('path');
        $force = $input->getOption('force');

        // 兼容多种传参格式：-p=value / -pvalue / -p value / --path=value / --path value
        // Symfony 对长选项 --path=value 会自动去掉 '='，但短选项 -p=value 会保留 '=' 前缀
        if ($localPath !== null) {
            $localPath = ltrim($localPath);          // 去掉前导空格
            $localPath = ltrim($localPath, '=');      // 去掉短选项可能残留的 '=' 前缀
            $localPath = ltrim($localPath);          // 再次去掉 '=' 后可能存在的空格
            // 去掉首尾成对引号（shell 在 PowerShell 下不会剥离引号）
            if (strlen($localPath) >= 2) {
                $first = $localPath[0];
                $last = substr($localPath, -1);
                if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                    $localPath = substr($localPath, 1, -1);
                }
            }
        }

        // 解析包名与 package 短名（如 xqkeji/xq-app-test -> xq-app-test）
        $isComposerPackage = strpos($name, '/') !== false;
        $packageName = $name;
        $packageShortName = $name;
        if ($isComposerPackage) {
            $parts = explode('/', $name);
            if (count($parts) === 2) {
                $packageShortName = $parts[1];
            }
        }

        // 指定 -p 即表示用户要删除源代码包，构建本地包目录路径
        $fullPackagePath = null;
        $deleteLocal = false;
        if ($localPath !== null) {
            $localPath = str_replace('\\', '/', $localPath);
            $localPath = rtrim($localPath, '/');

            // 候选路径1：父目录 + package 短名（与创建模块时的目录结构一致）
            $candidatePath = $localPath . '/' . $packageShortName;
            if (is_dir($candidatePath)) {
                $fullPackagePath = $candidatePath;
            } elseif (is_dir($localPath)) {
                // 候选路径2：-p 本身即为包目录（兼容直接传入完整路径）
                $fullPackagePath = $localPath;
            } else {
                $output->writeln("<error>指定的本地路径不存在: $localPath</error>");
                return 1;
            }

            // 危险操作：验证本地包目录的 composer.json 包名与待删除模块匹配
            $validated = false;
            $composerJsonFile = $fullPackagePath . '/composer.json';
            if (is_file($composerJsonFile)) {
                $pkgJson = json_decode(file_get_contents($composerJsonFile), true);
                if (is_array($pkgJson) && ($pkgJson['name'] ?? null) === $packageName) {
                    $validated = true;
                }
            }

            if ($validated) {
                $deleteLocal = true;
            } else {
                // 包名不匹配，可能不是相关联的 composer 包，要求单独确认（--force 无法跳过）
                $output->writeln('<comment>警告：本地包目录的 composer.json 包名与待删除模块不匹配，可能不是相关联的 composer 包</comment>');
                $output->writeln("<comment>      本地目录: $fullPackagePath</comment>");
                $output->writeln("<comment>      期望包名: $packageName</comment>");
                $helper = $this->getHelper('question');
                $question = new ConfirmationQuestion(
                    '<question>是否仍要删除该本地包目录？[y/N]</question> ',
                    false
                );
                if ($helper->ask($input, $output, $question)) {
                    $deleteLocal = true;
                }
            }
        }

        $module = new Module($this->getIO(), $this->requireComposer());

        // 提示信息
        if ($isComposerPackage) {
            if ($deleteLocal) {
                $output->writeln('<comment>提示：将清理项目内配置（composer.json、config/composer.php、vendor 软链接）</comment>');
                $output->writeln("<comment>      并删除本地源代码包目录: $fullPackagePath</comment>");
            } else {
                $output->writeln('<comment>提示：该命令只会清理项目内的配置（composer.json、config/composer.php、vendor 软链接）</comment>');
                $output->writeln('<comment>      不会删除项目外的本地包目录，如需删除请手动处理或指定 --path 参数</comment>');
            }
            $output->writeln('');
        }

        // 主确认（--force 可跳过）
        if (!$force) {
            $helper = $this->getHelper('question');
            $confirmMsg = $deleteLocal
                ? "确定要删除模块 '$name' 及本地源代码包吗？此操作不可恢复 [y/N]"
                : "确定要删除模块 '$name' 吗？此操作不可恢复 [y/N]";
            $question = new ConfirmationQuestion(
                "<question>$confirmMsg</question> ",
                false
            );

            if (!$helper->ask($input, $output, $question)) {
                $output->writeln('<info>已取消删除</info>');
                return 0;
            }
        }

        $module->removeModule($name, $deleteLocal ? $fullPackagePath : null, $deleteLocal);

        return 0;
    }
}
