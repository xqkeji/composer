<?php
namespace xqkeji\composer;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Util\Filesystem;

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
     * 获取当前模块路径
     */
    private function getCurrentModulePath(): ?string
    {
        if ($this->currentModule === null) {
            return null;
        }
        
        $rootPath = self::getRootPath();
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
        $rootPath = self::getRootPath();
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

        $rootPath = self::getRootPath();
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

        // 复制示例代码
        $examplePath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'example';
        
        if (!is_dir($examplePath)) {
            $this->io->write("<error>示例代码目录不存在: $examplePath</error>");
            return;
        }

        $this->copyDirectory($examplePath, $modulePath, $moduleName);
        
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

        [$vendor, $package] = $parts;
        
        // 从包名提取模块名: xq-app-home -> home
        $moduleName = $package;
        if (strpos($package, 'xq-app-') === 0) {
            $moduleName = substr($package, 7);
        } elseif (strpos($package, 'xq-com-') === 0) {
            $moduleName = substr($package, 7);
        }

        // 确定目标路径
        if ($targetPath) {
            // 用户指定了目标路径
            $fullTargetPath = rtrim($targetPath, '/\\') . DIRECTORY_SEPARATOR . $package;
        } else {
            // 默认在 vendor 同级目录创建
            $fullTargetPath = self::getRootPath() . DIRECTORY_SEPARATOR . $vendor . DIRECTORY_SEPARATOR . $package;
        }

        // 检查目录是否已存在
        if (is_dir($fullTargetPath)) {
            $this->io->write("<error>目录已存在: $fullTargetPath</error>");
            return;
        }

        // 创建目录结构
        mkdir($fullTargetPath, 0755, true);

        // 复制示例代码
        $examplePath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'example';
        
        if (!is_dir($examplePath)) {
            $this->io->write("<error>示例代码目录不存在: $examplePath</error>");
            return;
        }

        $this->copyDirectory($examplePath, $fullTargetPath, $moduleName);

        // 创建 composer.json
        $this->createComposerJson($fullTargetPath, $packageName, $moduleName);

        $this->io->write("<info>✓ Composer 包模块 '$packageName' 已成功创建: $fullTargetPath</info>");
        $this->showGeneratedStructure($fullTargetPath);

        // 如果指定了目标路径，创建软链接安装
        if ($targetPath) {
            $this->createSymlink($fullTargetPath, $packageName);
        }
        
        // 自动设置为当前模块（本地模块模式）
        if (!$targetPath) {
            $this->saveCurrentModule($moduleName);
        }
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
     * 创建 composer.json 文件
     */
    private function createComposerJson(string $path, string $packageName, string $moduleName): void
    {
        $composerJson = [
            'name' => $packageName,
            'description' => "基于新齐低代码开发框架的{$moduleName}模块",
            'type' => 'library',
            'license' => 'SSPL-1.0',
            'autoload' => [
                'psr-4' => [
                    "xqkeji\\app\\{$moduleName}\\" => 'src/'
                ]
            ],
            'authors' => [
                [
                    'name' => 'xqkeji.cn'
                ]
            ],
            'require' => []
        ];

        $jsonContent = json_encode($composerJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        file_put_contents($path . DIRECTORY_SEPARATOR . 'composer.json', $jsonContent);
        
        $this->io->write("<info>✓ 已创建 composer.json</info>");
    }

    /**
     * 创建软链接安装到当前项目
     */
    private function createSymlink(string $sourcePath, string $packageName): void
    {
        $vendorPath = self::getVendorPath();
        $targetPath = $vendorPath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $packageName);

        // 创建 vendor 目录（如果不存在）
        if (!is_dir(dirname($targetPath))) {
            mkdir(dirname($targetPath), 0755, true);
        }

        // 如果目标已存在，先删除
        if (is_link($targetPath)) {
            unlink($targetPath);
        } elseif (is_dir($targetPath)) {
            $this->filesystem->removeDirectory($targetPath);
        }

        // 创建软链接
        if (symlink($sourcePath, $targetPath)) {
            $this->io->write("<info>✓ 已创建软链接: $targetPath -> $sourcePath</info>");
        } else {
            $this->io->write("<error>✗ 创建软链接失败: $targetPath</error>");
        }
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
