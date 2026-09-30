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
            ->addOption('no-update', null, InputOption::VALUE_NONE, '仅修改 composer.json，不自动运行 composer update/require')
            ->addOption('require', null, InputOption::VALUE_NONE, '强制使用 composer require 安装（默认自动判定：包未安装→require 走完整安装事件流，包已存在→update 转本地路径包）')
            ->addOption('local-alias', null, InputOption::VALUE_NONE, '把版本别名写进【本地包】的 extra.branch-alias（会改动本地包 composer.json）；默认只写宿主的内联别名')
            ->addOption('no-alias', null, InputOption::VALUE_NONE, '完全不做版本别名：宿主 require 写 *，也不动本地包（本地包若报成 dev-x，依赖它的稳定约束会解析失败）')
            ->setHelp(<<<'EOF'
将一个 composer 包注册为本地路径包（本地开发/联调用）

<info>用法示例：</info>

  <comment># 将本地包注册为 path 仓库（默认符号链接，自动 composer update）</comment>
  composer xqkeji:path xqkeji/composer ../composer

  <comment># 指定绝对路径</comment>
  composer xqkeji:path xqkeji/composer F:/docker/code/php/composer

  <comment># 使用复制而非符号链接（Windows 无开发者模式/无管理员权限时）</comment>
  composer xqkeji:path xqkeji/composer ../composer --copy

  <comment># 仅修改 composer.json，不自动更新（之后手动运行 composer update/require 包名）</comment>
  composer xqkeji:path xqkeji/composer ../composer --no-update

  <comment># 包尚未安装：自动改用 composer require 安装（触发与 require 相同的 post-package-install 等事件/插件激活）</comment>
  composer xqkeji:path xqkeji/xq-app-content ../xq-app-content

  <comment># 强制走 composer require（即便包已存在也重新按 require 流程处理）</comment>
  composer xqkeji:path xqkeji/composer ../composer --require

  <comment># 别名写进本地包的 extra.branch-alias（会改动本地包 composer.json）</comment>
  composer xqkeji:path xqkeji/composer ../composer --local-alias

  <comment># 完全不做版本别名（本地包自带 version、或没有稳定约束卡它时）</comment>
  composer xqkeji:path xqkeji/composer ../composer --no-alias

<info>说明：</info>

  - 在当前项目 composer.json 中追加 path 仓库（url=本地路径，symlink=true 默认）
  - 同时在 require 中写入该包的约束（若已存在则覆盖）：本地包无 version 字段时写内联别名 "dev-<分支> as <系列>.x-dev"，否则写 "*"
  - 自动确保 minimum-stability: dev 与 prefer-stable: true（dev 分支可解析）
  - 若本地包 type 为 composer-plugin，自动在 allow-plugins 中放行该包
  - 已存在同名 path 仓库则覆盖更新（幂等）
  - 自动选择安装方式：包【未安装】→ 运行 composer require 包名（安装新包，触发 composer require 的完整事件流，含模块包的 post-package-install 钩子与插件激活）；包【已安装】→ 运行 composer update 包名（把已存在的包转为本地路径包，触发 update 事件）；--require 可强制按 require 流程
  - 默认自动运行上述 update/require；--no-update 可跳过
  - 符号链接(symlink)下本地源码改动即时生效；Windows 需开启开发者模式或以管理员运行，否则请用 --copy
  - 版本别名：本地包 composer.json 没有 version 字段（典型 dev 分支）时，path 仓库报出的版本就是 dev-<分支>（无 .git 时 Composer 固定回退为 dev-main），它不满足其它包对该包的 ^x 稳定约束，而 path 仓库是 canonical、不会回退到别的源，于是解析直接失败。默认做法：把别名写进【宿主项目】的 require —— "dev-<分支> as <系列>.x-dev"（如 dev-main as 1.2.x-dev），本地包文件零改动；本地包处于游离 HEAD 或 worktree（读不到当前分支）时不做别名并提示，因为那时 Composer 报的是最近 tag 的版本
  - 宿主内联别名对传递依赖同样有效：Composer 会把它作为一个等价版本加入解析池，别的项目 require 该包的 ^<系列> 也能命中
  - --local-alias：改为把 <系列>.x-dev 写进【本地包】composer.json 的 extra.branch-alias.dev-<分支>（旧默认行为；会改动本地包文件并按 4 空格重排 composer.json，好处是换任何宿主都自动生效）；--no-alias：两者都不写，require 用 *（仅适合本地包自带 version、或没人用稳定约束卡它的场景）
  - 本地包已自带 version 字段、或已声明当前分支的 extra.branch-alias 时，path 仓库报的版本已是可用版本，不再需要别名（require 写 *）
  - 系列号优先级：① 该包已发布/已安装的最新稳定版本（与 composer 最新版本直接对应，无需 git）② 本地 git 最新 tag ③ 交互输入。每次运行按最新版本自动推进系列号（如 1.2.x-dev→1.3.x-dev）
  - 分支名取自本地包的 .git/HEAD：内联别名模式下无 .git 直接按 dev-main（与 Composer 的回退一致），读到游离 HEAD/worktree 则跳过别名；--local-alias 模式下读不到分支会交互询问（默认 main）

EOF
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $package = trim((string) $input->getArgument('package'));
        $rawPath = trim((string) $input->getArgument('path'));
        $useCopy = (bool) $input->getOption('copy');
        $noUpdate = (bool) $input->getOption('no-update');
        $forceRequire = (bool) $input->getOption('require');
        $noAlias = (bool) $input->getOption('no-alias');
        $localAlias = (bool) $input->getOption('local-alias');

        if ($noAlias && $localAlias) {
            $output->writeln('<error>--no-alias 与 --local-alias 互斥：前者不做任何版本别名，后者把别名写进本地包</error>');
            return 1;
        }

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

        // 版本别名策略：默认只在宿主 require 写内联别名（不动本地包）；--local-alias 改写进本地包；--no-alias 不做
        $requireConstraint = '*';
        if ($localAlias) {
            $this->ensureBranchAlias($package, $absPath, $pkgComposer, $pkgInfo, $output);
        } elseif (!$noAlias) {
            $requireConstraint = $this->inlineAliasConstraint($package, $absPath, $pkgInfo, $output);
        }

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

        // 2) require：追加/覆盖该包约束（默认是宿主侧内联别名，见 inlineAliasConstraint）
        if (!isset($project['require']) || !is_array($project['require'])) {
            $project['require'] = [];
        }
        $oldRequire = $project['require'][$package] ?? null;
        if ($oldRequire !== null && $oldRequire !== $requireConstraint) {
            $output->writeln("<comment>require.{$package} 由 \"{$oldRequire}\" 改为 \"{$requireConstraint}\"</comment>");
        }
        $project['require'][$package] = $requireConstraint;

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
        $output->writeln("  require.{$package} = {$requireConstraint}");
        $output->writeln("  minimum-stability = dev, prefer-stable = true");

        // 5) 自动安装：包未安装 → composer require（完整安装事件流，含模块包 post-package-install 钩子/插件激活）；
        //    包已存在 → composer update（转为本地路径包）。--require 可强制走 require 流程。
        $installed = $this->isPackageInstalled($package);
        $useRequire = $forceRequire || !$installed;
        $verb = $useRequire ? 'require' : 'update';

        if ($noUpdate) {
            $output->writeln('');
            $manual = $useRequire ? "require \"{$package}:{$requireConstraint}\"" : "update {$package}";
            $output->writeln("<comment>已跳过自动执行，请手动运行：composer {$manual}</comment>");
            return 0;
        }

        $output->writeln('');
        if ($useRequire) {
            $output->writeln("<info>该包尚未安装，运行 composer require {$package}（触发与 composer require 相同的安装事件）...</info>");
        } else {
            $output->writeln("<info>包已安装，运行 composer update {$package} 将其转为本地路径包 ...</info>");
        }

        $application = $this->getApplication();
        $application->resetComposer();
        $installer = $application->find($verb);
        // require 需显式带上刚写入的约束（可能是内联别名），避免被改写成解析出的具体版本；update 只接包名
        $installerInput = $useRequire
            ? new ArrayInput([
                'command' => 'require',
                'packages' => ["{$package}:{$requireConstraint}"],
                '--no-interaction' => true,
            ])
            : new ArrayInput([
                'command' => 'update',
                'packages' => [$package],
                '--no-interaction' => true,
            ]);
        $code = $installer->run($installerInput, $output);

        if ($code !== 0) {
            $output->writeln("<error>composer {$verb} 退出码 {$code}，请检查上方输出</error>");
            return $code;
        }

        $output->writeln("<info>完成：{$package} 已" . ($useRequire ? '安装' : '更新') . "为本地路径包（" . ($useCopy ? '复制' : '符号链接') . "）</info>");
        return 0;
    }

    /**
     * 判断某包当前是否已安装（读本地仓库/installed.json，不联网、不受刚写入的 require 影响）。
     * 用于决定 xqkeji:path 用 composer require（未安装，走完整安装事件流）还是 update（已存在，转本地路径）。
     */
    private function isPackageInstalled(string $package): bool
    {
        try {
            $local = $this->requireComposer()->getRepositoryManager()->getLocalRepository();
            return $local->findPackage($package, '*') !== null;
        } catch (\Throwable $e) {
            return false;
        }
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

    /**
     * 宿主侧内联别名：返回要写进宿主 require 的约束（默认别名模式）。
     *
     * path 仓库里无 version 字段的包会报成 dev-<分支>（Composer 无 VCS 信息时固定回退 dev-main），
     * 它不满足其它包对该包的 ^x 稳定约束，而 path 仓库是 canonical 不回退别的源，解析会直接失败。
     * 这里返回 "dev-<分支> as <系列>.x-dev"，把别名落在宿主项目自己的 composer.json 上，本地包文件不动；
     * 本地包已自带 version 或已声明当前分支的 extra.branch-alias（报出的版本已可用）时返回 '*'。
     */
    private function inlineAliasConstraint(string $package, string $absPath, array $pkgInfo, OutputInterface $output): string
    {
        if (isset($pkgInfo['version'])) {
            return '*';
        }

        $branch = $this->detectBranch($absPath);
        if ($branch === null || $branch === '' || $branch === 'HEAD') {
            if (file_exists($absPath . '/.git')) {
                // 有 .git 却读不到分支（detached HEAD / linked worktree）：Composer 会改用最近 tag 的版本，
                // 此时猜 dev-<分支> 反而会让根约束解析失败，所以不做别名，只提示
                $output->writeln('<comment>本地包处于游离 HEAD 或 worktree（读不到当前分支），跳过宿主内联别名，require 用 *；</comment>');
                $output->writeln('<comment>若解析报“版本不满足约束”，请切到固定分支后再跑本命令，或自行在宿主 require 里写内联别名</comment>');
                return '*';
            }
            // 没有 git 仓库：Composer 猜不到版本，固定回退为 dev-main
            $branch = 'main';
        }
        $aliasKey = 'dev-' . $branch;

        $declared = $pkgInfo['extra']['branch-alias'][$aliasKey] ?? null;
        if (is_string($declared) && $declared !== '') {
            $output->writeln("<comment>本地包 extra.branch-alias.{$aliasKey} 已是 {$declared}，无需宿主内联别名（require 用 *）</comment>");
            return '*';
        }

        $resolved = $this->resolveAliasSeries($package, $absPath);
        if ($resolved === null) {
            $output->writeln("<comment>无法确定 {$package} 的稳定版本系列（未发布/未安装、无 git tag、且未交互输入），跳过宿主内联别名，require 用 *；</comment>");
            $output->writeln("<comment>若解析报“版本不满足约束”，请手动在宿主 composer.json 的 require 里把该包写成 \"{$aliasKey} as &lt;系列&gt;.x-dev\"</comment>");
            return '*';
        }
        [$series, $seriesSource] = $resolved;

        $constraint = $aliasKey . ' as ' . $series . '.x-dev';
        $output->writeln("<info>宿主 require 写入内联别名 {$constraint}（系列号来源：{$seriesSource}，本地包文件不改动）</info>");
        return $constraint;
    }

    /**
     * 稳定版本系列（major.minor）与其来源说明，供两种别名模式共用。
     * 优先级：① 包已发布/已安装的最新稳定版（无需 git）② 本地 git 最新 tag ③ 交互输入；都拿不到返回 null。
     *
     * @return array{0:string,1:string}|null
     */
    private function resolveAliasSeries(string $package, string $absPath): ?array
    {
        $io = $this->getIO();

        $series = $this->registryLatestStable($package);
        if ($series !== null) {
            return [$series, '包最新版本'];
        }

        $tag = $this->detectLatestTag($absPath);
        if ($tag !== null && preg_match('/(\d+\.\d+)\.\d+/', $tag, $m)) {
            return [$m[1], '本地 git tag'];
        }

        $ans = $io->ask("无法确定 {$package} 对应的稳定版本系列（如 1.2），请手动输入：", null);
        if (is_string($ans) && preg_match('/^\d+\.\d+$/', trim((string) $ans))) {
            return [trim((string) $ans), '手动输入'];
        }

        return null;
    }

    /**
     * --local-alias：把别名写进【本地包】composer.json 的 extra.branch-alias.dev-<分支> = <系列>.x-dev，
     * 使 path 仓库自身报告出满足 ^x 稳定约束的版本（换任何宿主都生效，代价是改动本地包文件）。
     *
     * 分支名取自本地 git HEAD，无 git 时交互提示（默认 main）；系列号见 resolveAliasSeries，
     * 每次运行会按最新版本自动推进（如 1.2.x-dev → 1.3.x-dev）。
     */
    private function ensureBranchAlias(string $package, string $absPath, string $pkgComposer, array &$pkgInfo, OutputInterface $output): void
    {
        if (isset($pkgInfo['version'])) {
            return;
        }

        $io = $this->getIO();

        // 分支名：本地 git HEAD；无 git 时交互提示（默认 main）
        $branch = $this->detectBranch($absPath);
        if ($branch === null || $branch === '' || $branch === 'HEAD') {
            $ans = $io->ask("该包未检测到 git 分支，branch-alias 的键名用哪个分支（默认 main）？", 'main');
            $branch = is_string($ans) && trim((string) $ans) !== '' ? trim((string) $ans) : 'main';
        }
        $aliasKey = 'dev-' . $branch;

        $resolved = $this->resolveAliasSeries($package, $absPath);
        if ($resolved === null) {
            $output->writeln("<comment>无法确定 {$package} 的稳定版本系列（未发布/未安装、无 git tag、且未交互输入），跳过 branch-alias；</comment>");
            $output->writeln("<comment>其依赖若要求稳定版本（如 ^1.0），请手动在该包 composer.json 的 extra.branch-alias.{$aliasKey} 写入 &lt;系列&gt;.x-dev</comment>");
            return;
        }
        [$series, $seriesSource] = $resolved;

        $alias = $series . '.x-dev';

        if (!isset($pkgInfo['extra']) || !is_array($pkgInfo['extra'])) {
            $pkgInfo['extra'] = [];
        }
        if (!isset($pkgInfo['extra']['branch-alias']) || !is_array($pkgInfo['extra']['branch-alias'])) {
            $pkgInfo['extra']['branch-alias'] = [];
        }

        $existing = $pkgInfo['extra']['branch-alias'][$aliasKey] ?? null;
        if ($existing === $alias) {
            $output->writeln("<comment>本地包 extra.branch-alias.{$aliasKey} 已为 {$alias}（来源：{$seriesSource}），跳过</comment>");
            return;
        }
        if ($existing !== null) {
            $output->writeln("<comment>本地包 extra.branch-alias.{$aliasKey} 由 {$existing} 更新为 {$alias}（来源：{$seriesSource}，版本系列已推进）</comment>");
        }

        $pkgInfo['extra']['branch-alias'][$aliasKey] = $alias;
        $written = file_put_contents(
            $pkgComposer,
            json_encode($pkgInfo, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n"
        );
        if ($written === false) {
            $output->writeln("<error>写入本地包 branch-alias 失败：{$pkgComposer}</error>");
            return;
        }
        $action = $existing === null ? '写入' : '更新';
        $output->writeln("<info>已在本地包 composer.json {$action} extra.branch-alias.{$aliasKey} = {$alias}（来源：{$seriesSource}，path 仓库版本将满足依赖的稳定约束）</info>");
    }

    /**
     * 取该包已发布/已安装的最新稳定版本系列（major.minor），与 composer 最新版本直接对应。
     * 优先读本地已安装仓库（无需网络），回退到远程仓库（packagist/gitee，需网络）。失败返回 null。
     */
    private function registryLatestStable(string $package): ?string
    {
        try {
            $composer = $this->requireComposer();
            $rm = $composer->getRepositoryManager();

            // 1) 已安装版本（本地仓库，无需网络）
            $local = $rm->getLocalRepository();
            $installed = $local->findPackage($package, '*');
            if ($installed !== null) {
                $v = $installed->getVersion();
                if (preg_match('/^(\d+)\.(\d+)\.\d+\.\d+$/', $v, $m)) {
                    return $m[1] . '.' . $m[2];
                }
            }

            // 2) 远程仓库（需网络）
            foreach ($rm->findPackages($package) as $p) {
                $v = $p->getVersion();
                if (preg_match('/^(\d+)\.(\d+)\.\d+\.\d+$/', $v, $m)) {
                    return $m[1] . '.' . $m[2];
                }
            }
        } catch (\Throwable $e) {
            return null;
        }
        return null;
    }

    /**
     * 读取本地包 .git/HEAD 获取当前分支名；无 .git 或 detached 返回 null。
     */
    private function detectBranch(string $absPath): ?string
    {
        $headFile = $absPath . '/.git/HEAD';
        if (!is_file($headFile)) {
            return null;
        }
        $head = trim((string) file_get_contents($headFile));
        if (str_starts_with($head, 'ref: refs/heads/')) {
            return substr($head, strlen('ref: refs/heads/'));
        }
        return null;
    }

    /**
     * 直接读取本地包 .git 目录获取最新 tag（合并 refs/tags/ 与 packed-refs），失败返回 null。
     * 避免 shell_exec 调 git（部分 Windows 环境下带路径参数会失败）。
     */
    private function detectLatestTag(string $absPath): ?string
    {
        $gitDir = $absPath . '/.git';
        if (!is_dir($gitDir)) {
            return null;
        }

        $tags = [];
        $tagDir = $gitDir . '/refs/tags';
        if (is_dir($tagDir)) {
            foreach (glob($tagDir . '/*') as $f) {
                if (is_file($f)) {
                    $tags[] = basename($f);
                }
            }
        }
        $packed = $gitDir . '/packed-refs';
        if (is_file($packed)) {
            foreach (file($packed) as $line) {
                $line = trim((string) $line);
                if ($line === '' || str_starts_with($line, '#')) {
                    continue;
                }
                if (preg_match('#^[\da-f]{40}\s+refs/tags/([^\s]+)$#', $line, $tm)) {
                    $tags[] = $tm[1];
                }
            }
        }
        if (empty($tags)) {
            return null;
        }
        usort($tags, static function ($a, $b): int {
            return version_compare(ltrim($a, 'vV'), ltrim($b, 'vV'));
        });
        return end($tags);
    }
}
