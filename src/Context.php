<?php
namespace xqkeji\composer;

use Composer\Composer;
use Composer\Factory;
use Composer\IO\IOInterface;

class Context
{
    use PathTrait;

    private IOInterface $io;
    private Composer $composer;
    private ?string $currentModule = null;
    private ?string $currentController = null;
    private ?string $currentMode = null; // 'form' 或 'table'

    public function __construct(IOInterface $io, Composer $composer)
    {
        $this->io = $io;
        $this->composer = $composer;
        $this->loadContext();
    }

    /**
     * 加载上下文
     */
    private function loadContext(): void
    {
        $configFile = self::getRuntimePath() . DIRECTORY_SEPARATOR . 'composer' . DIRECTORY_SEPARATOR . 'context.php';
        if (is_file($configFile)) {
            $config = include $configFile;
            $this->currentModule = $config['module'] ?? null;
            $this->currentController = $config['controller'] ?? null;
            $this->currentMode = $config['mode'] ?? null;
        }
    }

    /**
     * 保存上下文
     */
    public function saveContext(?string $module = null): void
    {
        if ($module !== null) {
            $this->currentModule = $module;
            $this->currentController = null;
            $this->currentMode = null;
        }

        $configPath = self::getRuntimePath() . DIRECTORY_SEPARATOR . 'composer';
        if (!is_dir($configPath)) {
            mkdir($configPath, 0755, true);
        }
        
        $configFile = $configPath . DIRECTORY_SEPARATOR . 'context.php';
        $content = "<?php\r\nreturn " . var_export([
            'module' => $this->currentModule,
            'controller' => $this->currentController,
            'mode' => $this->currentMode,
        ], true) . ';';
        file_put_contents($configFile, $content);
    }

    /**
     * 获取项目根目录（通过 Composer）
     */
    private function getProjectRootPath(): string
    {
        $composerFile = Factory::getComposerFile();
        return dirname(realpath($composerFile));
    }

    /**
     * 获取当前模块
     */
    public function getCurrentModule(): ?string
    {
        return $this->currentModule;
    }

    /**
     * 获取当前控制器
     */
    public function getCurrentController(): ?string
    {
        return $this->currentController;
    }

    /**
     * 获取当前模式（form/table）
     */
    public function getCurrentMode(): ?string
    {
        return $this->currentMode;
    }

    /**
     * 切换模式（form/table）
     */
    public function switchMode(string $mode): bool
    {
        if (!in_array($mode, ['form', 'table'])) {
            $this->io->write('<error>模式无效，只能是 form 或 table</error>');
            return false;
        }

        $this->currentMode = $mode;
        $this->saveContext();

        $label = $mode === 'form' ? '表单' : '表格';
        $this->io->write("<info>✓ 当前已设置为创建{$label}模式</info>");
        return true;
    }

    /**
     * 获取当前模块路径
     */
    public function getCurrentModulePath(): ?string
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
     * 获取有效的模块路径（支持本地模块和 composer 模块）
     * 返回 null 表示模块无效或不存在
     * 
     * 对于 composer 模块：
     * - 如果 vendor/包名 是本地软链接（symlink 或 junction），返回真实包目录的 src/（文件写入包源码）
     * - 如果 vendor/包名 不是本地软链接（纯 composer 安装的包），回退到 app/{module}/（文件写入项目内）
     */
    public function getValidModulePath(): ?string
    {
        if ($this->currentModule === null) {
            return null;
        }

        return $this->resolveModulePath($this->currentModule);
    }

    /**
     * 按模块名解析模块路径
     *
     * 支持两种模块形态：
     * 1. 本地模块：app/{module}/
     * 2. composer 包模块：config/composer.php 中注册的 vendor 包（src/ 目录）
     *
     * @return string|null 模块源码路径，找不到返回 null
     */
    public function resolveModulePath(string $moduleName): ?string
    {
        if ($moduleName === '') {
            return null;
        }

        $rootPath = $this->getProjectRootPath();

        // 1. 先检查 app 目录下的本地模块
        $localModulePath = $rootPath . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . $moduleName;
        if (is_dir($localModulePath)) {
            // 验证是否是有效模块（检查 config/acl.php 是否存在）
            $aclFile = $localModulePath . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'acl.php';
            if (is_file($aclFile)) {
                return $localModulePath;
            }
        }
        
        // 2. 检查是否是 composer 模块
        $composerConfigFile = $rootPath . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'composer.php';
        if (is_file($composerConfigFile)) {
            $composerConfig = include $composerConfigFile;
            if (isset($composerConfig[$moduleName])) {
                $packageName = $composerConfig[$moduleName];
                // vendor 下的包路径
                $vendorPackagePath = $rootPath . DIRECTORY_SEPARATOR . 'vendor' 
                    . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $packageName);
                
                if (is_dir($vendorPackagePath)) {
                    // 判断 vendor/包名 是否为本地软链接（symlink 或 Windows junction）
                    $isLocalSymlink = is_link($vendorPackagePath);
                    if (!$isLocalSymlink && DIRECTORY_SEPARATOR === '\\') {
                        // Windows: junction 不是 symlink，但可通过检查目录是否为 reparse point 判断
                        // 简单方法：检查目录是否存在 realpath 与自身不同，或检查 vendor 目录是否为 junction
                        // 这里用更可靠的方法：检查父目录中是否有指向该目录的 junction
                        $realVendorPath = realpath($vendorPackagePath);
                        // 如果 realpath 解析后路径与原始路径不同，说明是 junction/symlink
                        if ($realVendorPath !== false && $realVendorPath !== $vendorPackagePath) {
                            $isLocalSymlink = true;
                        }
                    }
                    
                    if ($isLocalSymlink) {
                        // 本地软链接：返回真实包目录的 src/（文件写入包源码）
                        $composerModulePath = $vendorPackagePath . DIRECTORY_SEPARATOR . 'src';
                        if (is_dir($composerModulePath)) {
                            $aclFile = $composerModulePath . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'acl.php';
                            if (is_file($aclFile)) {
                                return $composerModulePath;
                            }
                        }
                    } else {
                        // 非本地软链接（纯 composer 安装的包）：回退到 app/{module}/
                        // 如果 app/{module}/ 存在且有效，使用它；否则返回 null
                        if (is_dir($localModulePath)) {
                            $aclFile = $localModulePath . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'acl.php';
                            if (is_file($aclFile)) {
                                return $localModulePath;
                            }
                        }
                    }
                }
            }
        }
        
        return null;
    }

    /**
     * 切换模块
     */
    public function switchModule(string $moduleName): bool
    {
        // 支持用包名切换：xqkeji/xq-app-edu、xq-app-edu
        $moduleName = $this->normalizeModuleName($moduleName);

        // 验证模块名称
        if (!preg_match('/^[a-z][a-z0-9_]*$/', $moduleName)) {
            $this->io->write('<error>模块名称格式无效</error>');
            return false;
        }

        // 检查模块是否存在（本地模块 app/{module} 或 composer 包模块）
        $modulePath = $this->resolveModulePath($moduleName);
        if ($modulePath === null) {
            $rootPath = $this->getProjectRootPath();
            $localModulePath = $rootPath . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . $moduleName;
            $this->io->write("<error>模块 '{$moduleName}' 不存在</error>");
            $this->io->write("<comment>  已查找本地模块: {$localModulePath}</comment>");
            $this->io->write('<comment>  以及 config/composer.php 中注册的 composer 包模块</comment>');
            return false;
        }

        $this->currentModule = $moduleName;
        $this->currentController = null; // 切换模块时清空控制器
        $this->saveContext();
        
        $this->io->write("<info>✓ 当前模块已设置为: $moduleName</info>");
        $this->io->write("<comment>  模块路径: $modulePath</comment>");
        return true;
    }

    /**
     * 把包名（xqkeji/xq-app-edu、xq-app-edu）归一化为模块名（edu）
     */
    private function normalizeModuleName(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            return $name;
        }

        // 已是合法模块名则直接返回
        if (preg_match('/^[a-z][a-z0-9_]*$/', $name)) {
            return $name;
        }

        $configFile = $this->getProjectRootPath() . DIRECTORY_SEPARATOR . 'config'
            . DIRECTORY_SEPARATOR . 'composer.php';
        if (!is_file($configFile)) {
            return $name;
        }

        $config = include $configFile;
        if (!is_array($config)) {
            return $name;
        }

        $name = str_replace('\\', '/', $name);
        foreach ($config as $module => $package) {
            $package = str_replace('\\', '/', (string) $package);
            // 完整包名匹配：xqkeji/xq-app-edu
            if ($package === $name) {
                return (string) $module;
            }
            // 包名后半段匹配：xq-app-edu
            $pos = strrpos($package, '/');
            if ($pos !== false && substr($package, $pos + 1) === $name) {
                return (string) $module;
            }
        }

        return $name;
    }

    /**
     * 切换控制器
     */
    public function switchController(string $controllerName): bool
    {
        if ($this->currentModule === null) {
            $this->io->write('<error>请先设置当前模块</error>');
            return false;
        }

        // 验证控制器名称（支持小写加下划线或驼峰命名）
        if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $controllerName)) {
            $this->io->write('<error>控制器名称格式无效，只能包含字母、数字和下划线</error>');
            return false;
        }

        // 转换为大驼峰命名
        $className = $this->toCamelCase($controllerName);

        // 获取有效的模块路径（支持本地模块和 composer 模块）
        $modulePath = $this->getValidModulePath();
        if ($modulePath === null) {
            $this->io->write("<error>模块 '{$this->currentModule}' 无效或不存在</error>");
            return false;
        }

        // 低代码支持虚拟控制器：默认不生成实体文件（创建控制器时用 -f 才生成），
        // 因此切换控制器时不再强制要求 controller/{ClassName}.php 真实存在
        $controllerPath = $modulePath . DIRECTORY_SEPARATOR . 'controller' . DIRECTORY_SEPARATOR . $className . '.php';
        if (!is_file($controllerPath)) {
            $this->io->write("<comment>  控制器 '$className' 为虚拟控制器（尚未生成实体文件）</comment>");
        }

        $this->currentController = $className;
        $this->saveContext();

        $this->io->write("<info>✓ 当前控制器已设置为: $className</info>");
        return true;
    }

    /**
     * 将下划线命名转换为大驼峰命名
     */
    private function toCamelCase(string $string): string
    {
        return str_replace(' ', '', ucwords(str_replace('_', ' ', $string)));
    }

    /**
     * 显示当前上下文
     */
    public function showContext(): void
    {
        $this->io->write('<info>当前上下文：</info>');
        $this->io->write('  模块: ' . ($this->currentModule ?? '<comment>未设置</comment>'));
        $this->io->write('  控制器: ' . ($this->currentController ?? '<comment>未设置</comment>'));
        $modeLabel = $this->currentMode === 'form' ? '表单' : ($this->currentMode === 'table' ? '表格' : '<comment>未设置</comment>');
        $this->io->write('  模式: ' . $modeLabel);
    }
}
