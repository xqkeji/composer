<?php
namespace xqkeji\composer;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Util\Filesystem;
use Composer\Factory;
use Composer\Installer;

class Module implements EventSubscriberInterface
{
    use PathTrait;

    public static function getSubscribedEvents(): array
    {
        return [];
    }
    
    private IOInterface $io;
    private Composer $composer;
    private Filesystem $filesystem;
    private Context $context;

    public function __construct(IOInterface $io, Composer $composer)
    {
        $this->io = $io;
        $this->composer = $composer;
        $this->filesystem = new Filesystem();
        $this->context = new Context($io, $composer);
    }

    /**
     * 获取项目根目录（通过 Composer）
     */
    private function getProjectRootPath(): string
    {
        // 使用 composer.json 所在目录作为项目根目录
        $composerFile = Factory::getComposerFile();
        return dirname(realpath($composerFile));
    }

    /**
     * 获取当前模块路径
     */
    private function getCurrentModulePath(): ?string
    {
        $currentModule = $this->context->getCurrentModule();
        if ($currentModule === null) {
            return null;
        }
        
        $rootPath = $this->getProjectRootPath();
        $modulePath = $rootPath . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . $currentModule;
        
        if (is_dir($modulePath)) {
            return $modulePath;
        }
        
        return null;
    }

    /**
     * 切换到指定模块
     */
    public function useModule(string $moduleName): void
    {
        $this->context->switchModule($moduleName);
    }

    /**
     * 创建模块（公开方法）
     */
    public function createModule(string $name, ?string $targetPath = null, ?string $title = null): void
    {
        // 判断模式
        if (strpos($name, '/') !== false) {
            // composer 包模式
            $this->createComposerModule($name, $targetPath, $title);
        } else {
            // 本地模块模式
            $this->createLocalModule($name, $title);
        }
    }

    /**
     * 显示使用说明
     */
    private function showUsage(): void
    {
        $this->io->write('<info>用法:</info>');
        $this->io->write('  composer xqkeji:use <module_name>');
        $this->io->write('  composer xqkeji:module <name>');
        $this->io->write('  composer xqkeji:module <vendor/package>');
        $this->io->write('  composer xqkeji:module <vendor/package> <target-path>');
        $this->io->write('  composer xqkeji:controller <name>');
        $this->io->write('  composer xqkeji:controller <name> <action>');
        $this->io->write('');
        $this->io->write('<info>示例:</info>');
        $this->io->write('  composer xqkeji:use home');
        $this->io->write('  composer xqkeji:module home');
        $this->io->write('  composer xqkeji:module xqkeji/xq-app-home');
        $this->io->write('  composer xqkeji:module xqkeji/xq-app-home F:/docker/code/php/');
        $this->io->write('  composer xqkeji:controller home');
        $this->io->write('  composer xqkeji:controller home add');
    }



    /**
     * 创建本地模块到 app/ 目录
     */
    private function createLocalModule(string $moduleName, ?string $title = null): void
    {
        // 验证模块名称
        if (!preg_match('/^[a-z][a-z0-9_]*$/', $moduleName)) {
            $this->io->write('<error>模块名称格式无效，只能包含小写字母、数字和下划线，且以字母开头</error>');
            return;
        }

        // 获取当前项目的根目录（通过 Composer）
        $rootPath = $this->getProjectRootPath();
        $appPath = $rootPath . DIRECTORY_SEPARATOR . 'app';
        $modulePath = $appPath . DIRECTORY_SEPARATOR . $moduleName;

        // 检查模块是否已存在
        if (is_dir($modulePath)) {
            $this->io->write("<error>模块 '$moduleName' 已存在: $modulePath</error>");
            return;
        }

        // 创建 app 目录（如果不存在）
        if (!is_dir($appPath)) {
            mkdir($appPath, 0755, true);
            $this->io->write("<info>创建 app 目录: $appPath</info>");
        }

        // 使用 example/src/ 里的模板代码
        $exampleSrcPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'example' . DIRECTORY_SEPARATOR . 'src';
        
        if (!is_dir($exampleSrcPath)) {
            $this->io->write("<error>示例代码目录不存在: $exampleSrcPath</error>");
            return;
        }

        $this->copyDirectory($exampleSrcPath, $modulePath, $moduleName);
        
        // 写入中文模块名称（菜单与语言文件）
        $this->updateModuleTitle($modulePath, $moduleName, $title);
        
        $this->io->write("<info>✓ 模块 '$moduleName' 已成功创建: $modulePath</info>");
        $this->showGeneratedStructure($modulePath);
        
        // 自动设置为当前模块
        $this->context->saveContext($moduleName);
    }

    /**
     * 创建 composer 包模块
     */
    private function createComposerModule(string $packageName, ?string $targetPath = null, ?string $title = null): void
    {
        // 解析包名: xqkeji/xq-app-home
        $parts = explode('/', $packageName);
        if (count($parts) !== 2) {
            $this->io->write('<error>包名格式无效，应为 vendor/package</error>');
            return;
        }

        // 强制指定本地路径
        if (empty($targetPath)) {
            $this->io->write('<error>创建 composer 包模块必须指定本地路径</error>');
            $this->io->write('<info>用法: composer xqkeji:module vendor/package /path/to/local</info>');
            return;
        }

        [$vendor, $package] = $parts;
        
        // 从包名提取模块名: xq-app-home -> home
        $moduleName = $package;
        if (strpos($package, 'xq-app-') === 0) {
            $moduleName = substr($package, 7);
        } elseif (strpos($package, 'xq-com-') === 0) {
            $moduleName = substr($package, 7);
        }

        // 在指定的本地路径创建包目录（如 F:\docker\code\php\xq-app-site）
        $localPath = rtrim($targetPath, '/\\');
        $fullTargetPath = $localPath . DIRECTORY_SEPARATOR . $package;

        // 检查目录是否已存在
        if (is_dir($fullTargetPath)) {
            $this->io->write("<error>目录已存在: $fullTargetPath</error>");
            return;
        }

        // 检查父目录是否存在
        if (!is_dir($localPath)) {
            $this->io->write("<error>指定的本地路径不存在: $localPath</error>");
            return;
        }

        // 创建目录结构
        if (!mkdir($fullTargetPath, 0755, true)) {
            $this->io->write("<error>无法创建目录: $fullTargetPath</error>");
            $this->io->write("<comment>请检查路径权限和磁盘空间</comment>");
            return;
        }

        // 使用 example/ 完整结构（包含 .gitignore、composer.json、LICENSE、README.md）
        $examplePath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'example';
        
        if (!is_dir($examplePath)) {
            $this->io->write("<error>示例代码目录不存在: $examplePath</error>");
            return;
        }

        $this->copyDirectory($examplePath, $fullTargetPath, $moduleName);

        // 更新包的 composer.json
        $this->updatePackageComposerJson($fullTargetPath, $packageName, $moduleName);

        // 写入中文模块名称（菜单与语言文件）
        $this->updateModuleTitle($fullTargetPath . DIRECTORY_SEPARATOR . 'src', $moduleName, $title);

        // 获取当前项目的根目录（通过 Composer）
        $projectPath = $this->getProjectRootPath();

        // 更新当前项目的 composer.json，添加 path repository
        $this->updateProjectComposerJson($projectPath, $packageName, $fullTargetPath);

        $this->io->write("<info>✓ Composer 包模块 '$packageName' 已成功创建: $fullTargetPath</info>");
        $this->showGeneratedStructure($fullTargetPath);

        // 创建软链接到 vendor 目录
        $this->createSymlink($projectPath, $packageName, $fullTargetPath);

        // 更新 config/composer.php 配置文件
        $this->updateComposerConfig($projectPath, $moduleName, $packageName);

        // 自动设置为当前模块
        $this->context->saveContext($moduleName);
    }

    /**
     * 更新 config/composer.php 配置文件
     */
    private function updateComposerConfig(string $projectPath, string $moduleName, string $packageName): void
    {
        $configPath = $projectPath . DIRECTORY_SEPARATOR . 'config';
        $configFile = $configPath . DIRECTORY_SEPARATOR . 'composer.php';

        // 确保 config 目录存在
        if (!is_dir($configPath)) {
            mkdir($configPath, 0755, true);
        }

        // 读取现有配置或创建新配置
        $config = [];
        if (is_file($configFile)) {
            $config = include $configFile;
        }

        // 添加模块映射
        $config[$moduleName] = $packageName;

        // 写入配置文件
        $content = "<?php\r\nreturn " . self::exportArray($config) . ";";
        file_put_contents($configFile, $content);

        $this->io->write("<info>✓ 已更新 config/composer.php 配置</info>");
    }

    /**
     * 写入模块中文名称到 menu.php 与 lang/zh_cn.php
     *
     * 传入的中文名作为模块主体名，菜单与语言文件中统一显示为「{中文名}管理」
     */
    private function updateModuleTitle(string $modulePath, string $moduleName, ?string $title): void
    {
        if ($title === null || $title === '') {
            return;
        }

        $menuFile = $modulePath . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'menu.php';
        if (is_file($menuFile)) {
            $menuConfig = include $menuFile;
            if (is_array($menuConfig)) {
                $menuTitle = $this->appendManage($title);
                foreach (array_keys($menuConfig) as $entry) {
                    if (is_array($menuConfig[$entry])) {
                        $menuConfig[$entry]['title'] = $menuTitle;
                    }
                }
                file_put_contents($menuFile, "<?php\r\nreturn " . self::exportArray($menuConfig) . ";");
                $this->io->write("<info>✓ 已更新菜单配置（模块名称）: $menuFile</info>");
            }
        }

        Lang::writeModule($this->io, $modulePath, $moduleName, $this->appendManage($title));
    }

    /**
     * 中文名后补「管理」后缀（已有则不重复添加）
     */
    private function appendManage(string $title): string
    {
        return str_ends_with($title, '管理') ? $title : ($title . '管理');
    }

    /**
     * 更新包的 composer.json
     */
    private function updatePackageComposerJson(string $packagePath, string $packageName, string $moduleName): void
    {
        $composerFile = $packagePath . DIRECTORY_SEPARATOR . 'composer.json';

        if (!is_file($composerFile)) {
            $this->io->write("<error>包的 composer.json 不存在: $composerFile</error>");
            return;
        }

        $composerJson = json_decode(file_get_contents($composerFile), true);
        if ($composerJson === null) {
            $this->io->write("<error>包的 composer.json 格式无效</error>");
            return;
        }

        // 更新包名
        $composerJson['name'] = $packageName;

        // 声明版本号：path repository 的包若不带 version，composer 只能从 git 分支推出 dev-master，
        // 而根项目默认 minimum-stability 为 stable，require "*" 会解析失败
        if (empty($composerJson['version'])) {
            $composerJson['version'] = '1.0.0';
        }

        // 更新描述
        $composerJson['description'] = "基于新齐低代码开发框架的{$moduleName}模块";

        // 更新 autoload 配置
        if (!isset($composerJson['autoload'])) {
            $composerJson['autoload'] = [];
        }
        if (!isset($composerJson['autoload']['psr-4'])) {
            $composerJson['autoload']['psr-4'] = [];
        }
        $composerJson['autoload']['psr-4']["xqkeji\\app\\{$moduleName}\\"] = 'src/';

        // 保存更新后的 composer.json
        $jsonContent = json_encode($composerJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        file_put_contents($composerFile, $jsonContent);

        $this->io->write("<info>✓ 已更新包的 composer.json</info>");
    }

    /**
     * 创建软链接到 vendor 目录
     */
    private function createSymlink(string $projectPath, string $packageName, string $packagePath): void
    {
        $vendorPath = $projectPath . DIRECTORY_SEPARATOR . 'vendor';
        $targetPath = $vendorPath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $packageName);

        // 创建 vendor 目录（如果不存在）
        if (!is_dir($vendorPath)) {
            mkdir($vendorPath, 0755, true);
        }

        // 创建包的 vendor 目录（如果不存在）
        $packageVendorDir = dirname($targetPath);
        if (!is_dir($packageVendorDir)) {
            mkdir($packageVendorDir, 0755, true);
        }

        // 如果目标已存在，先删除
        if (is_link($targetPath)) {
            unlink($targetPath);
        } elseif (is_dir($targetPath)) {
            // 使用 Filesystem 的 removeDirectory 方法
            $this->filesystem->removeDirectory($targetPath);
        }

        // 确保目标路径完全不存在
        if (file_exists($targetPath)) {
            $this->io->write("<error>无法删除已存在的目录: $targetPath</error>");
            return;
        }

        // 创建软链接（Windows 使用 junction，Linux/Mac 使用 symlink）
        if (DIRECTORY_SEPARATOR === '\\') {
            // Windows: 使用 junction（不需要管理员权限）
            // 确保路径是绝对路径
            $targetPath = realpath(dirname($targetPath)) . DIRECTORY_SEPARATOR . basename($targetPath);
            $packagePath = realpath($packagePath);

            if ($packagePath === false) {
                $this->io->write("<error>包路径不存在: $packagePath</error>");
                return;
            }

            // 使用 exec 创建 junction
            $command = sprintf('mklink /J "%s" "%s"', $targetPath, $packagePath);
            exec($command, $output, $returnCode);

            if (is_dir($targetPath)) {
                $this->io->write("<info>✓ 已创建 junction: $targetPath -> $packagePath</info>");
            } else {
                $this->io->write("<error>✗ 创建 junction 失败</error>");
                $this->io->write("<comment>错误信息: " . implode("\n", $output) . "</comment>");
                $this->io->write("<comment>请手动运行: $command</comment>");
            }
        } else {
            // Linux/Mac: 使用 symlink
            if (symlink($packagePath, $targetPath)) {
                $this->io->write("<info>✓ 已创建软链接: $targetPath -> $packagePath</info>");
            } else {
                $this->io->write("<error>✗ 创建软链接失败: $targetPath</error>");
            }
        }
    }

    /**
     * 更新当前项目的 composer.json，添加 path repository
     */
    private function updateProjectComposerJson(string $projectPath, string $packageName, string $packagePath): void
    {
        $composerFile = $projectPath . DIRECTORY_SEPARATOR . 'composer.json';

        if (!is_file($composerFile)) {
            $this->io->write("<error>项目的 composer.json 不存在: $composerFile</error>");
            return;
        }

        $composerJson = json_decode(file_get_contents($composerFile), true);
        if ($composerJson === null) {
            $this->io->write("<error>项目的 composer.json 格式无效</error>");
            return;
        }

        // 添加 path repository
        if (!isset($composerJson['repositories'])) {
            $composerJson['repositories'] = [];
        }

        // 检查是否已存在该 repository
        $exists = false;
        $normalized = str_replace('\\', '/', $packagePath);
        foreach ($composerJson['repositories'] as $repo) {
            if (isset($repo['type']) && $repo['type'] === 'path' &&
                isset($repo['url']) && str_replace('\\', '/', $repo['url']) === $normalized) {
                $exists = true;
                break;
            }
        }

        if (!$exists) {
            $composerJson['repositories'][] = [
                'type' => 'path',
                'url' => str_replace('\\', '/', $packagePath)
            ];
        }

        // 添加 require
        if (!isset($composerJson['require'])) {
            $composerJson['require'] = [];
        }
        if (!isset($composerJson['require'][$packageName])) {
            $composerJson['require'][$packageName] = '*';
        }

        // 保存更新后的 composer.json
        $jsonContent = json_encode($composerJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        file_put_contents($composerFile, $jsonContent);

        $this->io->write("<info>✓ 已更新项目的 composer.json，添加了 path repository</info>");
    }

    /**
     * 删除 composer 模块
     */
    public function removeModule(string $name, ?string $localPath = null, bool $deleteLocal = false): void
    {
        // 解析包名
        $packageName = $name;
        $moduleName = $name;
        
        if (strpos($name, '/') !== false) {
            // 从包名提取模块名: xq-app-home -> home
            $parts = explode('/', $name);
            if (count($parts) === 2) {
                $package = $parts[1];
                if (strpos($package, 'xq-app-') === 0) {
                    $moduleName = substr($package, 7);
                } elseif (strpos($package, 'xq-com-') === 0) {
                    $moduleName = substr($package, 7);
                } else {
                    $moduleName = $package;
                }
            }
        }
        
        $projectPath = $this->getProjectRootPath();
        
        // 1. 清理项目 composer.json
        $this->removeFromProjectComposerJson($projectPath, $packageName);
        
        // 2. 清理 config/composer.php
        $this->removeFromComposerConfig($projectPath, $moduleName);
        
        // 3. 删除 vendor 中的软链接
        $this->removeVendorSymlink($projectPath, $packageName);
        
        // 4. 如果需要删除本地目录
        if ($deleteLocal && $localPath !== null && is_dir($localPath)) {
            $this->filesystem->removeDirectory($localPath);
            $this->io->write("<info>✓ 已删除本地包目录: $localPath</info>");
        }
        
        $this->io->write("<info>✓ 模块 '$name' 已完整删除</info>");
    }
    
    /**
     * 从项目 composer.json 中移除包
     */
    private function removeFromProjectComposerJson(string $projectPath, string $packageName): void
    {
        $composerFile = $projectPath . DIRECTORY_SEPARATOR . 'composer.json';
        
        if (!is_file($composerFile)) {
            return;
        }
        
        $composerJson = json_decode(file_get_contents($composerFile), true);
        if ($composerJson === null) {
            return;
        }
        
        // 移除 require
        if (isset($composerJson['require'][$packageName])) {
            unset($composerJson['require'][$packageName]);
            $this->io->write("<info>✓ 已从 composer.json 移除 require: $packageName</info>");
        }
        
        // 移除 path repository
        if (isset($composerJson['repositories'])) {
            $composerJson['repositories'] = array_values(array_filter(
                $composerJson['repositories'],
                function ($repo) use ($packageName) {
                    // 保留非 path 类型的 repository
                    if (!isset($repo['type']) || $repo['type'] !== 'path') {
                        return true;
                    }
                    // 保留其他 path repository
                    if (isset($repo['url'])) {
                        $url = $repo['url'];
                        // 检查是否是指向该包的 repository
                        if (strpos($url, $packageName) !== false) {
                            $this->io->write("<info>✓ 已从 composer.json 移除 repository: $url</info>");
                            return false;
                        }
                    }
                    return true;
                }
            ));
        }
        
        // 保存更新后的 composer.json
        $jsonContent = json_encode($composerJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        file_put_contents($composerFile, $jsonContent);
    }
    
    /**
     * 从 config/composer.php 中移除模块映射
     */
    private function removeFromComposerConfig(string $projectPath, string $moduleName): void
    {
        $configFile = $projectPath . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'composer.php';
        
        if (!is_file($configFile)) {
            return;
        }
        
        $config = include $configFile;
        
        if (isset($config[$moduleName])) {
            unset($config[$moduleName]);
            
            // 写入配置文件
            $content = "<?php\r\nreturn " . self::exportArray($config) . ";";
            file_put_contents($configFile, $content);
            
            $this->io->write("<info>✓ 已从 config/composer.php 移除模块: $moduleName</info>");
        }
    }
    
    /**
     * 删除 vendor 中的软链接/junction
     */
    private function removeVendorSymlink(string $projectPath, string $packageName): void
    {
        $vendorPath = $projectPath . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $packageName);
        
        if (!file_exists($vendorPath) && !is_link($vendorPath)) {
            return;
        }
        
        // Windows 上的 junction 用 rmdir 直接删除（不需要递归）
        if (DIRECTORY_SEPARATOR === '\\') {
            rmdir($vendorPath);
        } else {
            // Linux/Mac 使用 unlink 删除 symlink
            if (is_link($vendorPath)) {
                unlink($vendorPath);
            } else {
                $this->filesystem->removeDirectory($vendorPath);
            }
        }
        
        $this->io->write("<info>✓ 已删除 vendor 目录: $vendorPath</info>");
    }
    
    /**
     * 递归复制目录
     */
    private function copyDirectory(string $source, string $target, string $moduleName): void
    {
        if (!is_dir($target)) {
            mkdir($target, 0755, true);
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $targetPath = $target . DIRECTORY_SEPARATOR . $iterator->getSubPathName();
            
            if ($item->isDir()) {
                if (!is_dir($targetPath)) {
                    mkdir($targetPath, 0755, true);
                }
            } else {
                $this->copyAndProcessFile($item->getPathname(), $targetPath, $moduleName);
            }
        }
    }

    /**
     * 复制并处理文件内容
     */
    private function copyAndProcessFile(string $source, string $target, string $moduleName): void
    {
        $content = file_get_contents($source);
        
        // 替换占位符
        $content = str_replace('{MODULE_NAME}', $moduleName, $content);
        $content = str_replace('{MODULE_CLASS}', $this->toCamelCase($moduleName), $content);
        
        file_put_contents($target, $content);
    }

    /**
     * 将下划线命名转换为大驼峰命名
     */
    private function toCamelCase(string $string): string
    {
        return str_replace(' ', '', ucwords(str_replace('_', ' ', $string)));
    }

    /**
     * 显示生成的目录结构
     */
    private function showGeneratedStructure(string $path): void
    {
        $this->io->write("<info>已生成的目录结构:</info>");
        $this->listDirectory($path, '  ');
    }

    /**
     * 列出目录结构
     */
    private function listDirectory(string $path, string $prefix = ''): void
    {
        $items = scandir($path);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            
            $fullPath = $path . DIRECTORY_SEPARATOR . $item;
            $this->io->write($prefix . $item);
            
            if (is_dir($fullPath)) {
                $this->listDirectory($fullPath, $prefix . '  ');
            }
        }
    }

}
