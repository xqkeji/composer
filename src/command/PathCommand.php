<?php
namespace xqkeji\composer\command;

use Composer\Command\BaseCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class PathCommand extends BaseCommand
{
    use NormalizesShortOptions;

    protected function configure()
    {
        $this->setName('xqkeji:path')
            ->setDescription('将 composer 包注册为本地路径包（path 仓库 + symlink）')
            ->addArgument('package', InputArgument::REQUIRED, '包名（如 xqkeji/composer）')
            ->addArgument('path', InputArgument::REQUIRED, '本地路径（包根目录，需包含 composer.json）')
            ->addOption('copy', null, InputOption::VALUE_NONE, '使用复制而非符号链接（symlink=false），适合无法创建符号链接的环境')
            ->addOption('no-update', null, InputOption::VALUE_NONE, '仅修改 composer.json，不自动运行 composer update')
            ->setHelp(<<<'EOF'
将一个 composer 包注册为本地路径包（本地开发/联调用）

<info>用法示例：</info>

  <comment># 将本地包注册为 path 仓库（默认符号链接，自动 composer update）</comment>
  composer xqkeji:path xqkeji/composer ../composer

  <comment># 指定绝对路径</comment>
  composer xqkeji:path xqkeji/composer F:/docker/code/php/composer

  <comment># 使用复制而非符号链接（Windows 无开发者模式/无管理员权限时）</comment>
  composer xqkeji:path xqkeji/composer ../composer --copy

  <comment># 仅修改 composer.json，不自动更新（之后手动运行 composer update 包名）</comment>
  composer xqkeji:path xqkeji/composer ../composer --no-update

<info>说明：</info>

  - 在当前项目 composer.json 中追加 path 仓库（url=本地路径，symlink=true 默认）
  - 同时在 require 中追加 "包名": "*"（若已存在则更新）
  - 自动确保 minimum-stability: dev 与 prefer-stable: true（dev 分支可解析）
  - 若本地包 type 为 composer-plugin，自动在 allow-plugins 中放行该包
  - 已存在同名 path 仓库则覆盖更新（幂等）
  - 默认自动运行 composer update 包名；--no-update 可跳过
  - 符号链接(symlink)下本地源码改动即时生效；Windows 需开启开发者模式或以管理员运行，否则请用 --copy

EOF
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $package = trim((string) $input->getArgument('package'));
        $rawPath = trim((string) $input->getArgument('path'));
        $useCopy = (bool) $input->getOption('copy');
        $noUpdate = (bool) $input->getOption('no-update');

        // 校验包名
        if (!preg_match('/^[a-z0-9_.-]+\/[a-z0-9_.-]+$/', $package)) {
            $output->writeln('<error>包名格式无效，应为 vendor/name（全小写，如 xqkeji/composer）</error>');
            return 1;
        }

        // 规范化本地路径
        $localPath = str_replace('\\', '/', $rawPath);
        $localPath = rtrim($localPath, '/');
        $absPath = $this->resolvePath($localPath);
        if ($absPath === null || !is_dir($absPath)) {
            $output->writeln("<error>本地路径不存在：{$localPath}</error>");
            return 1;
        }
        $pkgComposer = $absPath . '/composer.json';
        if (!is_file($pkgComposer)) {
            $output->writeln("<error>本地路径下未找到 composer.json：{$absPath}</error>");
            $output->writeln('<info>请在包根目录（包含 composer.json 的目录）下指定路径</info>');
            return 1;
        }

        // 读本地包信息（判断是否为 composer-plugin）
        $pkgInfo = json_decode((string) file_get_contents($pkgComposer), true) ?: [];
        $isPlugin = isset($pkgInfo['type']) && $pkgInfo['type'] === 'composer-plugin';

        // 定位当前项目 composer.json
        $projectFile = $this->getProjectComposerJson();
        if (!is_file($projectFile)) {
            $output->writeln("<error>当前目录未找到 composer.json：{$projectFile}</error>");
            return 1;
        }
        $project = json_decode((string) file_get_contents($projectFile), true) ?: [];
        if (!is_array($project)) {
            $output->writeln('<error>composer.json 解析失败</error>');
            return 1;
        }

        // 1) repositories：追加/覆盖 path 仓库（以包名为键，幂等）
        $repositories = $this->normalizeRepositories($project['repositories'] ?? null);
        $repositories[$package] = [
            'type' => 'path',
            'url' => $absPath,
            'symlink' => !$useCopy,
        ];
        $project['repositories'] = $repositories;

        // 2) require：追加 "包名": "*"
        if (!isset($project['require']) || !is_array($project['require'])) {
            $project['require'] = [];
        }
        $project['require'][$package] = '*';

        // 3) 兜底 minimum-stability: dev + prefer-stable: true（dev 分支可解析）
        if (!isset($project['minimum-stability'])) {
            $project['minimum-stability'] = 'dev';
        } elseif ($project['minimum-stability'] !== 'dev') {
            $project['minimum-stability'] = 'dev';
            $output->writeln('<comment>minimum-stability 已调整为 dev（以解析本地 dev 分支）</comment>');
        }
        $project['prefer-stable'] = true;

        // 4) 若本地包是 composer-plugin，在 allow-plugins 中放行
        if ($isPlugin) {
            if (!isset($project['config']) || !is_array($project['config'])) {
                $project['config'] = [];
            }
            if (!isset($project['config']['allow-plugins']) || !is_array($project['config']['allow-plugins'])) {
                $project['config']['allow-plugins'] = [];
            }
            if (!isset($project['config']['allow-plugins'][$package])) {
                $project['config']['allow-plugins'][$package] = true;
                $output->writeln("<comment>已在 allow-plugins 中放行插件包 {$package}</comment>");
            }
        }

        // 写回 composer.json
        $written = file_put_contents(
            $projectFile,
            json_encode($project, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n"
        );
        if ($written === false) {
            $output->writeln("<error>写入 composer.json 失败：{$projectFile}</error>");
            return 1;
        }

        $output->writeln("<info>已将 {$package} 注册为本地路径包：</info>");
        $output->writeln("  repositories.{$package} = path, url={$absPath}, symlink=" . ($useCopy ? 'false' : 'true'));
        $output->writeln("  require.{$package} = *");
        $output->writeln("  minimum-stability = dev, prefer-stable = true");

        // 5) 自动 composer update
        if ($noUpdate) {
            $output->writeln('');
            $output->writeln("<comment>已跳过自动更新，请手动运行：composer update {$package}</comment>");
            return 0;
        }

        $output->writeln('');
        $output->writeln("<info>运行 composer update {$package} ...</info>");

        $application = $this->getApplication();
        $application->resetComposer();
        $update = $application->find('update');
        $updateInput = new ArrayInput([
            'command' => 'update',
            'packages' => [$package],
            '--no-interaction' => true,
        ]);
        $code = $update->run($updateInput, $output);

        if ($code !== 0) {
            $output->writeln("<error>composer update 退出码 {$code}，请检查上方输出</error>");
            return $code;
        }

        $output->writeln("<info>完成：{$package} 已链接为本地路径包（" . ($useCopy ? '复制' : '符号链接') . "）</info>");
        return 0;
    }

    /**
     * 解析本地路径为绝对路径（相对路径基于当前工作目录）
     */
    private function resolvePath(string $path): ?string
    {
        if (preg_match('#^[a-zA-Z]:/#', $path) || str_starts_with($path, '/')) {
            $real = realpath($path);
            return $real === false ? null : str_replace('\\', '/', $real);
        }
        $real = realpath(getcwd() . '/' . $path);
        return $real === false ? null : str_replace('\\', '/', $real);
    }

    /**
     * 把现有 repositories（可能是顺序数组或键值对象）规整为以键索引的数组，
     * 便于按包名幂等覆盖。
     */
    private function normalizeRepositories($repositories): array
    {
        if (!is_array($repositories)) {
            return [];
        }
        $result = [];
        $i = 0;
        foreach ($repositories as $key => $value) {
            if (is_int($key)) {
                $result['repo-' . $i] = $value;
            } else {
                $result[$key] = $value;
            }
            $i++;
        }
        return $result;
    }

    /**
     * 当前项目 composer.json 路径（composer 命令在根目录执行）
     */
    private function getProjectComposerJson(): string
    {
        return getcwd() . '/composer.json';
    }
}
