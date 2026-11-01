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
            ->addOption('copy', null, InputOption::VALUE_NONE, '使用复制而非链接（写进 path 仓库的 options.symlink=false，由 Composer 自己执行）')
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

  <comment># 不要链接，装副本（options.symlink=false，由 Composer 复制；改完源码需重跑本命令同步）</comment>
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

  - 在当前项目 composer.json 中追加 path 仓库（url=本地路径，options.symlink=true 默认）；建链还是复制交给 Composer，本命令不自己碰文件系统
  - 同时在 require 中写入该包的约束（若已存在则覆盖）：本地包无 version 字段时写内联别名 "dev-<分支> as <系列>.x-dev"，否则写 "*"
  - 自动确保 minimum-stability: dev 与 prefer-stable: true（dev 分支可解析）
  - 若本地包 type 为 composer-plugin，自动在 allow-plugins 中放行该包
  - 已存在同名 path 仓库则覆盖更新（幂等，重复运行内容不变就不打印改动）；指向同一目录、或那个目录里 composer.json 就是同名包的旧 path 条目会被改写掉，避免两个 path 源抢同一个包（抢的时候 Composer 取先注册的那条，你指的目录会被忽略）
  - 本次的 path 条目写在 repositories 最前（顺序数组占第 0 项，键值对象把包名键排到第一）：镜像源往往也提供同一个 dev-<分支> 且 reference 一模一样，排在它后面就永远输掉平局 —— 表现是安装日志 Extracting archive 而不是 Junctioning from，vendor 始终是远端副本
  - repositories 保持宿主原有的容器形式：原来是顺序数组就写数组项，原来是以仓库名为键的对象就写对应键，两者都是 Composer schema 接受的结构
  - 自动选择安装方式：包【未安装】→ 运行 composer require 包名（安装新包，触发 composer require 的完整事件流，含模块包的 post-package-install 钩子与插件激活）；包【已安装】→ 运行 composer update 包名（把已存在的包转为本地路径包，触发 update 事件）；--require 可强制按 require 流程
  - 装完会校验 vendor/{包名} 是否真的指向本地目录，没指向就自动补跑 composer remove 包名 + composer require "包名:约束"。原因：安装来源取自 vendor/composer/installed.json 里记的那一条，而内联别名让 path 包报出的版本与宿主已装版本相等，于是 update/require/reinstall 都判定“已是最新”，只改 composer.json 并不会把 vendor 换成链接（--copy 不做这步校验，复制安装本来就没有链接）
  - 该包被其它包传递依赖时 composer remove 换不了来源，两种表现都会遇到：内联别名跟着 require 行一起被删，而排在前面的 path 源是 canonical、只报 dev-<分支>，这轮解析以 "That repository is canonical ... not installable" 失败并把 composer.json 还原（别名写进本地包 extra.branch-alias 就不受这个影响，即 --local-alias）；别名够用、解析通过时，remove 卸完又会被依赖方装回来，照样 "Removal failed, still present"。这两种情况下紧接着的 require 都只打印 Nothing to modify（文件被还原后约束一字没变），所以别指望这轮能链上，按报错里的处置来
  - 默认自动运行上述 update/require；--no-update 可跳过
  - --copy 写的是 path 仓库的 options.symlink=false：Composer 只从这个位置读开关（PathRepository 把 options 里的 symlink/relative 拷进 transport-options，PathDownloader 据此在"建链"和"镜像复制"之间选），写在仓库项顶层的 symlink 没有任何代码读取，等于没写。本命令 1.2.11 之前写在顶层，所以 --copy 从来没生效过；老宿主里那种顶层条目下次运行会被自动改写成 options 形式
  - 链接由 Composer 建，跨平台不用你操心：Windows 用 mklink /J 建 junction（普通权限即可，不需要开发者模式/管理员），类 Unix 用真 symlink（那种环境才需要权限）。日志分别打印 Junctioning from / Symlinking from；建链失败会退化成 Mirroring from（复制），那不是本命令的失败，是平台的
  - 符号链接下本地源码改动即时生效；不想用链接就加 --copy（装的是副本，每次改完要重跑本命令同步）
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

        // 1) repositories：追加/覆盖 path 仓库，并保持宿主原有的容器形式（顺序数组 / 键值对象）
        $project['repositories'] = $this->registerPathRepository($project, $package, $absPath, !$useCopy, $output);

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

        $repoLabel = self::isListArray($project['repositories']) ? 'repositories[]' : "repositories.{$package}";

        $output->writeln("<info>已将 {$package} 注册为本地路径包：</info>");
        $output->writeln('  ' . $repoLabel . ' = path, url=' . $absPath . ', options.symlink=' . ($useCopy ? 'false' : 'true'));
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
            if (!$useCopy && $installed) {
                $output->writeln("<comment>若跑完 vendor 仍不是本地链接，需改用：composer remove {$package} && composer require \"{$package}:{$requireConstraint}\"</comment>");
            }
            return 0;
        }

        $output->writeln('');
        if ($useRequire) {
            $output->writeln("<info>该包尚未安装，运行 composer require {$package}（触发与 composer require 相同的安装事件）...</info>");
        } else {
            $output->writeln("<info>包已安装，运行 composer update {$package} 将其转为本地路径包 ...</info>");
        }

        // require 需显式带上刚写入的约束（可能是内联别名），避免被改写成解析出的具体版本；update 只接包名
        $installerInput = $useRequire
            ? ['command' => 'require', 'packages' => ["{$package}:{$requireConstraint}"], '--no-interaction' => true]
            : ['command' => 'update', 'packages' => [$package], '--no-interaction' => true];
        $code = $this->runApplicationCommand($installerInput, $output);

        if ($code !== 0) {
            $output->writeln("<error>composer {$verb} 退出码 {$code}，请检查上方输出</error>");
            return $code;
        }

        // 6) 校验 vendor 是否真的指向本地目录。update/require/reinstall 都按 vendor/composer/installed.json
        //    里记录的来源安装，而内联别名让 path 包报出的版本与宿主已装版本相等，于是三者全部判定
        //    “已是最新/无需改动”，vendor 里仍是原来那个普通目录。实测只有 remove + require 能换掉来源。
        if (!$useCopy) {
            $relinkCode = $this->relinkIfNeeded($package, $absPath, $requireConstraint, $isPlugin, $output);
            if ($relinkCode !== 0) {
                return $relinkCode;
            }
        }

        $output->writeln("<info>完成：{$package} 已" . ($useRequire ? '安装' : '更新') . "为本地路径包（" . ($useCopy ? '复制' : '符号链接') . "）</info>");
        return 0;
    }

    /**
     * vendor/{包名} 解析后是否就是本地目录（Windows 上 Composer 建 junction，realpath 会跟到源目录；
     * 复制出来的普通目录只会解析到自身）。
     */
    private function vendorPointsToLocal(string $package, string $absPath): bool
    {
        $real = realpath(getcwd() . '/vendor/' . $package);
        if ($real === false) {
            return false;
        }
        return strtolower(rtrim(str_replace('\\', '/', $real), '/'))
            === strtolower(rtrim($absPath, '/'));
    }

    /**
     * 没链上就自动补跑 composer remove + require（换掉 installed.json 里记的安装来源）。
     */
    private function relinkIfNeeded(string $package, string $absPath, string $requireConstraint, bool $isPlugin, OutputInterface $output): int
    {
        if ($this->vendorPointsToLocal($package, $absPath)) {
            $output->writeln("<info>✓ vendor/{$package} 已指向 {$absPath}</info>");
            return 0;
        }

        $output->writeln('');
        $output->writeln("<comment>vendor/{$package} 还没指向 {$absPath}：composer 按 installed.json 记的来源安装，"
            . "而别名让版本与已装的相等，update/require/reinstall 都认为无需重装。</comment>");
        $output->writeln("<info>自动补跑 composer remove + require 以换成本地链接 ...</info>");

        // remove 的退出码不能中止流程：该包若被其它包传递依赖，remove 会先自己重新解析一轮（宿主 require 里的
        // 内联别名跟着被删掉，path 源又是 canonical，这轮要么 "That repository is canonical ... not installable"
        // 失败并还原 composer.json，要么卸不下来报 "Removal failed, ... is still present"），退出码 2。
        // 两种情况这一步都换不了来源，但 require 仍要跑完 —— 包没被真卸掉时它是唯一还可能生效的动作。
        $removeCode = $this->runApplicationCommand(['command' => 'remove', 'packages' => [$package], '--no-interaction' => true], $output);
        if ($removeCode !== 0) {
            $output->writeln("<comment>composer remove 退出码 {$removeCode}（该包多半被其它包依赖所以没被卸干净），"
                . '继续用 require 把来源换成本地路径</comment>');
        }

        $code = $this->runApplicationCommand([
            'command' => 'require',
            'packages' => ["{$package}:{$requireConstraint}"],
            '--no-interaction' => true,
        ], $output);
        if ($code !== 0) {
            $output->writeln("<error>composer require 退出码 {$code}，包可能已被移除，请检查上方输出并手动补 require</error>");
            return $code;
        }

        // remove 会连带清掉 config.allow-plugins 里的放行项，插件包要补回来
        if ($isPlugin) {
            $this->ensureAllowPlugins($package, $output);
        }

        if ($this->vendorPointsToLocal($package, $absPath)) {
            $output->writeln("<info>✓ vendor/{$package} 已链接到 {$absPath}</info>");
            return 0;
        }

        $real = realpath(getcwd() . '/vendor/' . $package);
        $output->writeln("<error>仍未链接：vendor/{$package} "
            . ($real === false ? '不存在（remove 已卸载、require 未装回）' : '实际指向 ' . str_replace('\\', '/', $real))
            . '</error>');
        $output->writeln('<comment>该包被其它包依赖时 remove 不会真的卸载它，而版本与 reference 都没变时 require 也只判定"无需改动"。可用的处置（按推荐顺序）：</comment>');
        $output->writeln('  0) 先看上方安装日志写的是 Extracting archive 还是 Junctioning from —— archive 说明这次赢的是别的源（镜像源也提供同一个 dev-<分支> 且 reference 相同）。本命令已把 path 条目写到宿主 repositories 的最前来赢这个平局；还抢不过就用 composer show ' . $package . ' -a 看有哪些源在供同一个版本');
        $output->writeln("  1) 在本地包 {$absPath} 里提交一次（哪怕空提交：git commit --allow-empty -m sync）再重跑本命令 —— reference 一变，composer 就会真的重装并 Junctioning from 本地目录（夹具实测：dev-main af46c18 => dev-main 813fde9）");
        $output->writeln("  2) 上面 remove 那步若报 \"That repository is canonical ... not installable\"，说明别的包用 ^x 稳定约束依赖 {$package}：改用 composer xqkeji:path {$package} {$absPath} --local-alias，把别名写进本地包的 extra.branch-alias，remove 删掉宿主 require 行时别名不会跟着丢");
        $output->writeln("  3) composer why {$package} 看是谁在依赖它，必要时把那个包也一并用 xqkeji:path 指到本地");
        $output->writeln("  4) 加 --copy 放弃链接（写进 path 仓库的 options.symlink=false，装的是副本，每次改完重跑本命令同步）。链接本来就交给 Composer 建：Windows 用 mklink /J 建 junction（普通权限就行，不需要开发者模式/管理员），类 Unix 才用真 symlink；日志出现 Mirroring from 说明确实退成了复制");
        return 1;
    }

    /**
     * 按名称在宿主 Application 里跑一条 composer 命令；每次先 resetComposer，让它重新读取改动后的 composer.json/lock。
     */
    private function runApplicationCommand(array $args, OutputInterface $output): int
    {
        $name = (string) $args['command'];
        $application = $this->getApplication();
        $application->resetComposer();
        return $application->find($name)->run(new ArrayInput($args), $output);
    }

    /**
     * 确保宿主 config.allow-plugins 仍放行该插件包（remove 之后该项可能被删）。
     */
    private function ensureAllowPlugins(string $package, OutputInterface $output): void
    {
        $projectFile = $this->getProjectComposerJson();
        $project = json_decode((string) @file_get_contents($projectFile), true) ?: [];
        if (isset($project['config']['allow-plugins'][$package])) {
            return;
        }
        if (!isset($project['config']) || !is_array($project['config'])) {
            $project['config'] = [];
        }
        if (!isset($project['config']['allow-plugins']) || !is_array($project['config']['allow-plugins'])) {
            $project['config']['allow-plugins'] = [];
        }
        $project['config']['allow-plugins'][$package] = true;
        if (file_put_contents($projectFile, json_encode($project, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n") !== false) {
            $output->writeln("<comment>已在 allow-plugins 中重新放行插件包 {$package}</comment>");
        }
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
     * 写入 path 仓库，并保持宿主 repositories 原有的容器形式。
     *
     * Composer 的 repositories 只接受两种结构：顺序数组（每项是仓库定义，或 {"packagist.org": false}
     * 这类"以源名为键"的单项）和以仓库名为键的对象（值必须是带 type 的仓库定义或 false）。
     * 把顺序数组的整数键改成 repo-0 之类的字符串键，会得到 {"repo-0": {"packagist.org": false}}，
     * 这种值既没有 type 也不是布尔，composer 直接判定 composer.json 不符合 schema 并拒绝运行。
     *
     * 两种形式都把本次的 path 条目放在**最前**：同名同版本的多个候选，Composer 取先注册的那条。宿主的
     * 镜像/composer 源通常排在数组前面、也提供同一个 dev-<分支>（本地包已推送过，reference 完全相同），
     * 追加在末尾就会被它抢走 —— 安装日志表现为 Extracting archive 而不是 Junctioning from，vendor 永远是副本。
     *
     * 链接还是复制写在 `options.symlink`，不用自己判断平台：Composer 只认这个键
     * （PathRepository 把 repoConfig['options'] 里 symlink/relative 两项塞进 transport-options，
     * PathDownloader 据此在 STRATEGY_SYMLINK / STRATEGY_MIRROR 之间切换；Windows 走 mklink /J 建 junction，
     * 类 Unix 走 symlink()）。顶层的 `symlink` 没有任何代码读取，写了等于没写 —— 老版本就在这里写，
     * 所以 --copy 从来没生效过，而默认建链接只是因为那本来就是 Composer 的默认行为。
     */
    private function registerPathRepository(array $project, string $package, string $absPath, bool $symlink, OutputInterface $output): array
    {
        $entry = ['type' => 'path', 'url' => $absPath, 'options' => ['symlink' => $symlink]];
        $repositories = $project['repositories'] ?? [];
        if (!is_array($repositories) || $repositories === []) {
            return [$package => $entry];
        }

        if (self::isListArray($repositories)) {
            $list = [];
            foreach ($repositories as $item) {
                if ($this->isIdenticalPathRepo($item, $absPath, $symlink)) {
                    continue; // 本次要写的条目已在，先摘出来再放回队首，内容没变就不声张
                }
                $stale = $this->stalePathRepoReason($item, $package, $absPath);
                if ($stale !== null) {
                    $output->writeln("<comment>已重写 repositories 中的旧 path 项（{$stale}）：{$package} → {$absPath}（options.symlink=" . ($symlink ? 'true' : 'false') . '）</comment>');
                    continue;
                }
                $list[] = $item;
            }
            // 放队首而不是追加：同名同版本的候选 Composer 取先注册的那条，宿主的 composer 镜像源常在数组前面
            // 且同样提供 dev-<分支>，追加在末尾就会输给镜像（表现为 Extracting archive 而不是 Junctioning from）。
            $list = array_merge([$entry], $list);
            return $list;
        }

        foreach (array_keys($repositories) as $key) {
            if ($key === $package) {
                continue;
            }
            $stale = $this->stalePathRepoReason($repositories[$key], $package, $absPath);
            if ($stale !== null) {
                unset($repositories[$key]);
                $output->writeln("<comment>已重写 repositories.{$key}（{$stale}）：{$package} → {$absPath}（options.symlink=" . ($symlink ? 'true' : 'false') . '）</comment>');
            }
        }
        if (isset($repositories[$package]) && !$this->isIdenticalPathRepo($repositories[$package], $absPath, $symlink)) {
            $output->writeln("<comment>repositories.{$package} 已存在，覆盖为 {$absPath}</comment>");
        }
        // 键序同样决定优先级：重建数组，把本次的包放在第一位
        $repositories = array_diff_key($repositories, [$package => 1]);
        return array_merge([$package => $entry], $repositories);
    }

    /**
     * 数组项是否已经与本次要写入的 path 条目完全一致（同 url 同 options.symlink），即重复运行无需声张。
     * 只认 options.symlink：老版本写在顶层，那个位置没人读，命中下面的 stalePathRepoReason 会被改写掉。
     */
    private function isIdenticalPathRepo($item, string $absPath, bool $symlink): bool
    {
        return is_array($item)
            && ($item['type'] ?? null) === 'path'
            && (($item['options']['symlink'] ?? null) === $symlink)
            && $this->isSamePathRepo($item, $absPath);
    }

    /**
     * 该仓库项会不会跟本次注册抢同一个包：同 url、以包名为键的包装、或指向的目录里 composer.json 就是同名包。
     * 命中返回原因文本，不相关返回 null。两个 path 源给出同名同版本时 Composer 取先注册的那条，
     * 旧条目不清掉，后面的 require 会再把包装回原目录。
     */
    private function stalePathRepoReason($item, string $package, string $absPath): ?string
    {
        if (!is_array($item)) {
            return null;
        }
        if (array_keys($item) === [$package]) {
            return "键 {$package}";
        }
        if (($item['type'] ?? null) !== 'path') {
            return null;
        }
        if ($this->isSamePathRepo($item, $absPath)) {
            return '同一目录 ' . $item['url'];
        }
        $dir = $this->resolvePath((string) ($item['url'] ?? ''));
        if ($dir === null) {
            return null;
        }
        $cfg = json_decode((string) @file_get_contents($dir . '/composer.json'), true) ?: [];
        if (($cfg['name'] ?? null) === $package) {
            return '同名包 ' . $item['url'];
        }
        return null;
    }

    /**
     * 顺序数组判定（不依赖 PHP 8.1 的 array_is_list）。
     */
    private static function isListArray(array $arr): bool
    {
        $i = 0;
        foreach ($arr as $key => $unused) {
            if ($key !== $i++) {
                return false;
            }
        }
        return true;
    }

    /**
     * 数组项是否已经是同一 url 的 path 仓库（统一分隔符、去尾斜杠、忽略大小写后比较）。
     */
    private function isSamePathRepo($item, string $absPath): bool
    {
        if (!is_array($item) || ($item['type'] ?? null) !== 'path') {
            return false;
        }
        $url = (string) ($item['url'] ?? '');
        if ($url === '') {
            return false;
        }
        return strtolower(rtrim(str_replace('\\', '/', $url), '/'))
            === strtolower(rtrim(str_replace('\\', '/', $absPath), '/'));
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
