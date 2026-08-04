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
    private ?string $currentModule = null;

    public function __construct(IOInterface $io, Composer $composer)
    {
        $this->io = $io;
        $this->composer = $composer;
        $this->filesystem = new Filesystem();
        $this->loadCurrentModule();
    }

    /**
     * 加载当前模块
     */
    private function loadCurrentModule(): void
    {
        $configFile = self::getRuntimePath() . DIRECTORY_SEPARATOR . 'composer' . DIRECTORY_SEPARATOR . 'current_module.php';
        if (is_file($configFile)) {
            $config = include $configFile;
            $this->currentModule = $config['module'] ?? null;
        }
    }

    /**
     * 保存当前模块
     */
    private function saveCurrentModule(string $moduleName): void
    {
        $configPath = self::getRuntimePath() . DIRECTORY_SEPARATOR . 'composer';
        if (!is_dir($configPath)) {
            mkdir($configPath, 0755, true);
        }
        
        $configFile = $configPath . DIRECTORY_SEPARATOR . 'current_module.php';
        $content = "<?php\r\nreturn " . var_export(['module' => $moduleName], true) . ';';
        file_put_contents($configFile, $content);
        
        $this->currentModule = $moduleName;
        $this->io->write("<info>✓ 当前模块已设置为: $moduleName</info>");
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
        if ($this->currentModule === null) {
            return null;
        }
        
        $rootPath = $this->getProjectRootPath();
        $modulePath = $rootPath . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . $this->currentModule;
        
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
        // 验证模块名称
        if (!preg_match('/^[a-z][a-z0-9_]*$/', $moduleName)) {
            $this->io->write('<error>模块名称格式无效</error>');
            return;
        }

        // 检查模块是否存在
        $rootPath = $this->getProjectRootPath();
        $modulePath = $rootPath . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . $moduleName;
        
        if (!is_dir($modulePath)) {
            $this->io->write("<error>模块 '$moduleName' 不存在: $modulePath</error>");
            return;
        }

        $this->saveCurrentModule($moduleName);
    }

    /**
     * 创建模块（公开方法）
     */
    public function createModule(string $name, ?string $targetPath = null): void
    {
        // 判断模式
        if (strpos($name, '/') !== false) {
            // composer 包模式
            $this->createComposerModule($name, $targetPath);
        } else {
            // 本地模块模式
            $this->createLocalModule($name);
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
    private function createLocalModule(string $moduleName): void
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
        
        $this->io->write("<info>✓ 模块 '$moduleName' 已成功创建: $modulePath</info>");
        $this->showGeneratedStructure($modulePath);
        
        // 自动设置为当前模块
        $this->saveCurrentModule($moduleName);
    }

    /**
     * 创建 composer 包模块
     */
    private function createComposerModule(string $packageName, ?string $targetPath = null): void
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

        // 创建目录结构
        mkdir($fullTargetPath, 0755, true);

        // 使用 example/ 完整结构（包含 .gitignore、composer.json、LICENSE、README.md）
        $examplePath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'example';
        
        if (!is_dir($examplePath)) {
            $this->io->write("<error>示例代码目录不存在: $examplePath</error>");
            return;
        }

        $this->copyDirectory($examplePath, $fullTargetPath, $moduleName);

        // 更新包的 composer.json
        $this->updatePackageComposerJson($fullTargetPath, $packageName, $moduleName);

        // 获取当前项目的根目录（通过 Composer）
        $projectPath = $this->getProjectRootPath();

        // 更新当前项目的 composer.json，添加 path repository
        $this->updateProjectComposerJson($projectPath, $packageName, $fullTargetPath);

        $this->io->write("<info>✓ Composer 包模块 '$packageName' 已成功创建: $fullTargetPath</info>");
        $this->showGeneratedStructure($fullTargetPath);

        // 创建软链接到 vendor 目录
        $this->createSymlink($projectPath, $packageName, $fullTargetPath);

        // 自动设置为当前模块
        $this->saveCurrentModule($moduleName);
    }

    /**
     * 创建控制器（公开方法）
     */
    public function createController(string $controllerName, ?string $actionName = null): void
    {
        // 验证控制器名称
        if (!preg_match('/^[a-z][a-z0-9_]*$/', $controllerName)) {
            $this->io->write('<error>控制器名称格式无效，只能包含小写字母、数字和下划线，且以字母开头</error>');
            return;
        }

        // 检查当前模块
        $modulePath = $this->getCurrentModulePath();
        if ($modulePath === null) {
            $this->io->write('<error>未设置当前模块，请先使用 composer xqkeji:use -- module_name</error>');
            return;
        }

        $controllerPath = $modulePath . DIRECTORY_SEPARATOR . 'controller';

        if ($actionName === null) {
            // 创建单个控制器文件
            $this->createControllerFile($controllerPath, $controllerName);
        } else {
            // 创建控制器目录和动作类
            $this->createControllerWithActions($controllerPath, $controllerName, $actionName);
        }
    }

    /**
     * 创建控制器文件
     */
    private function createControllerFile(string $controllerPath, string $controllerName): void
    {
        if (!is_dir($controllerPath)) {
            mkdir($controllerPath, 0755, true);
        }

        $className = $this->toCamelCase($controllerName);
        $filePath = $controllerPath . DIRECTORY_SEPARATOR . $className . '.php';

        if (is_file($filePath)) {
            $this->io->write("<error>控制器已存在: $filePath</error>");
            return;
        }

        $content = $this->generateControllerContent($className);
        file_put_contents($filePath, $content);

        $this->io->write("<info>✓ 控制器已创建: $filePath</info>");
    }

    /**
     * 创建控制器目录和动作类
     */
    private function createControllerWithActions(string $controllerPath, string $controllerName, string $actionName): void
    {
        // 验证动作名称
        if (!preg_match('/^[a-z][a-z0-9_]*$/', $actionName)) {
            $this->io->write('<error>动作名称格式无效，只能包含小写字母、数字和下划线，且以字母开头</error>');
            return;
        }

        $controllerDir = $controllerPath . DIRECTORY_SEPARATOR . $controllerName;
        
        if (!is_dir($controllerDir)) {
            mkdir($controllerDir, 0755, true);
        }

        $className = $this->toCamelCase($actionName);
        $filePath = $controllerDir . DIRECTORY_SEPARATOR . $className . '.php';

        if (is_file($filePath)) {
            $this->io->write("<error>动作类已存在: $filePath</error>");
            return;
        }

        $content = $this->generateActionContent($className, $controllerName);
        file_put_contents($filePath, $content);

        $this->io->write("<info>✓ 动作类已创建: $filePath</info>");
    }

    /**
     * 生成控制器类内容
     */
    private function generateControllerContent(string $className): string
    {
        $namespace = "app\\{$this->currentModule}\\controller";
        
        return <<<PHP
<?php
namespace {$namespace};

use xqkeji\mvc\Controller;

class {$className} extends Controller
{

}

PHP;
    }

    /**
     * 生成动作类内容
     */
    private function generateActionContent(string $className, string $controllerName): string
    {
        $namespace = "app\\{$this->currentModule}\\controller\\{$controllerName}";
        
        return <<<PHP
<?php
namespace {$namespace};

use xqkeji\mvc\Action;

class {$className} extends Action
{
    public function run()
    {
        // TODO: 实现动作逻辑
    }
}

PHP;
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
        foreach ($composerJson['repositories'] as $repo) {
            if (isset($repo['type']) && $repo['type'] === 'path' &&
                isset($repo['url']) && $repo['url'] === $packagePath) {
                $exists = true;
                break;
            }
        }

        if (!$exists) {
            $composerJson['repositories'][] = [
                'type' => 'path',
                'url' => $packagePath
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
