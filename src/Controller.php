<?php
namespace xqkeji\composer;

use Composer\Composer;
use Composer\IO\IOInterface;

class Controller
{
    use PathTrait;

    private IOInterface $io;
    private Composer $composer;
    private ?string $currentModule = null;

    public function __construct(IOInterface $io, Composer $composer)
    {
        $this->io = $io;
        $this->composer = $composer;
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
     * 获取项目根目录（通过 Composer）
     */
    private function getProjectRootPath(): string
    {
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
     * 创建控制器（公开方法）
     */
    public function createController(string $controllerName, string $authEntry = 'guest', array $actions = []): void
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

        // 创建控制器类
        $controllerPath = $modulePath . DIRECTORY_SEPARATOR . 'controller';
        $this->createControllerFile($controllerPath, $controllerName);

        // 根据权限入口类型处理配置
        if ($authEntry === 'admin') {
            // admin 入口：更新 ACL、menu 和 lang
            $this->updateAclConfig($modulePath, $authEntry, $controllerName, $actions);
            $this->updateMenuConfig($modulePath, $controllerName, $authEntry);
            $this->updateLangConfig($modulePath, $controllerName, $actions);
        } elseif ($authEntry === 'guest') {
            // guest 入口：更新 ACL（默认 index 动作），不处理 menu 和 lang
            if (empty($actions)) {
                $actions = ['index'];
            }
            $this->updateAclConfig($modulePath, $authEntry, $controllerName, $actions);
        } else {
            // 其他入口（如 member、teacher 等）：只更新 ACL，不处理 menu 和 lang
            $this->updateAclConfig($modulePath, $authEntry, $controllerName, $actions);
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
     * 更新 ACL 配置
     */
    private function updateAclConfig(string $modulePath, string $authEntry, string $controllerName, array $actions): void
    {
        $aclFile = $modulePath . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'acl.php';
        
        if (!is_file($aclFile)) {
            $this->io->write("<comment>⚠ ACL 配置文件不存在: $aclFile</comment>");
            return;
        }

        // 读取现有配置
        $aclConfig = include $aclFile;
        
        // 添加控制器权限配置
        if (!isset($aclConfig[$authEntry])) {
            $aclConfig[$authEntry] = [];
        }
        if (!isset($aclConfig[$authEntry]['auth'])) {
            $aclConfig[$authEntry]['auth'] = [];
        }
        
        $aclConfig[$authEntry]['auth'][$controllerName] = $actions;
        
        // 写回文件
        $content = "<?php\r\nreturn " . var_export($aclConfig, true) . ";";
        file_put_contents($aclFile, $content);
        
        $this->io->write("<info>✓ 已更新 ACL 配置: $aclFile</info>");
    }

    /**
     * 更新菜单配置
     */
    private function updateMenuConfig(string $modulePath, string $controllerName, string $authEntry): void
    {
        $menuFile = $modulePath . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'menu.php';
        
        if (!is_file($menuFile)) {
            $this->io->write("<comment>⚠ 菜单配置文件不存在: $menuFile</comment>");
            return;
        }

        // 读取现有配置
        $menuConfig = include $menuFile;
        
        // 添加菜单项
        $className = $this->toCamelCase($controllerName);
        $menuConfig[] = [
            'name' => "{$this->currentModule}.{$controllerName}.admin",
            'title' => "{$className}管理",
            'url' => "{$this->currentModule}/{$controllerName}/admin",
            'icon' => 'list',
            'sort' => 0,
            'auth' => $authEntry !== 'guest'
        ];
        
        // 写回文件
        $content = "<?php\r\nreturn " . var_export($menuConfig, true) . ";";
        file_put_contents($menuFile, $content);
        
        $this->io->write("<info>✓ 已更新菜单配置: $menuFile</info>");
    }

    /**
     * 更新语言配置
     */
    private function updateLangConfig(string $modulePath, string $controllerName, array $actions): void
    {
        $langFile = $modulePath . DIRECTORY_SEPARATOR . 'lang' . DIRECTORY_SEPARATOR . 'zh-cn.php';
        
        if (!is_file($langFile)) {
            $this->io->write("<comment>⚠ 语言配置文件不存在: $langFile</comment>");
            return;
        }

        // 读取现有配置
        $langConfig = include $langFile;
        
        // 如果未指定动作，使用默认动作列表
        if (empty($actions)) {
            $actions = ['admin', 'add', 'edit', 'delete', 'change', 'b_delete'];
        }
        
        $className = $this->toCamelCase($controllerName);
        $prefix = "{$this->currentModule} {$controllerName}";
        
        // 添加语言配置
        foreach ($actions as $action) {
            $actionClass = $this->toCamelCase($action);
            
            // 动作标题
            $langConfig["{$prefix} {$action} title"] = "{$actionClass}{$className}";
            
            // 成功/失败消息
            if (in_array($action, ['add', 'edit'])) {
                $langConfig["{$prefix} {$action} success"] = "{$actionClass}{$className}成功";
                $langConfig["{$prefix} {$action} failed"] = "{$actionClass}{$className}失败";
            } elseif (in_array($action, ['delete', 'change', 'b_delete'])) {
                $langConfig["{$prefix} {$action} success"] = "{$actionClass}{$className}成功";
                $langConfig["{$prefix} {$action} failed"] = "{$actionClass}{$className}失败";
            }
            
            // 权限描述
            $langConfig["{$this->currentModule} module {$controllerName} {$action} auth"] = "{$actionClass}{$className}";
        }
        
        // 模块权限描述
        $langConfig["{$this->currentModule} module {$controllerName} auth"] = "{$className}管理";
        
        // 写回文件
        $content = "<?php\r\nreturn " . var_export($langConfig, true) . ";";
        file_put_contents($langFile, $content);
        
        $this->io->write("<info>✓ 已更新语言配置: $langFile</info>");
    }

    /**
     * 将下划线命名转换为大驼峰命名
     */
    private function toCamelCase(string $string): string
    {
        return str_replace(' ', '', ucwords(str_replace('_', ' ', $string)));
    }
}
