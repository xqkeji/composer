<?php
namespace xqkeji\composer;

use Composer\Composer;
use Composer\Factory;
use Composer\IO\IOInterface;

class Controller
{
    use PathTrait;

    /**
     * 默认动作列表（非 guest 入口且未指定动作时使用）
     */
    private const DEFAULT_ACTIONS = ['admin', 'add', 'edit', 'delete', 'change', 'b_delete'];

    /**
     * guest 入口的默认动作列表
     */
    private const DEFAULT_GUEST_ACTIONS = ['index'];

    private IOInterface $io;
    private Composer $composer;
    private Context $context;

    public function __construct(IOInterface $io, Composer $composer)
    {
        $this->io = $io;
        $this->composer = $composer;
        $this->context = new Context($io, $composer);
    }

    /**
     * 创建控制器（公开方法）
     */
    public function createController(string $controllerName, string $authEntry = 'guest', string $authType = 'auth', array $actions = [], bool $createFile = false): void
    {
        // 验证控制器名称（支持大小写字母、数字和下划线）
        if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $controllerName)) {
            $this->io->write('<error>控制器名称格式无效，只能包含字母、数字和下划线，且以字母开头</error>');
            return;
        }

        // 转换为小写下划线格式，用于配置文件
        $configName = $this->toSnakeCase($controllerName);

        // 获取当前模块
        $currentModule = $this->context->getCurrentModule();
        if ($currentModule === null) {
            $this->io->write('<error>未设置当前模块，请先使用 composer xqkeji:use -- module_name</error>');
            return;
        }

        // 获取有效的模块路径（支持本地模块和 composer 模块）
        $modulePath = $this->context->getValidModulePath();
        if ($modulePath === null) {
            $this->io->write("<error>模块 '{$currentModule}' 无效或不存在，请检查：</error>");
            $this->io->write('  1. 模块是否在 app/ 目录下存在');
            $this->io->write('  2. 模块是否是 composer 模块（通过 config/composer.php 配置）');
            $this->io->write('  3. 模块的 config/acl.php 文件是否存在');
            return;
        }

        // 未指定动作时使用默认动作列表（统一在入口处解析，保证 ACL / menu / lang 一致）
        if (empty($actions)) {
            $actions = $authEntry === 'guest' ? self::DEFAULT_GUEST_ACTIONS : self::DEFAULT_ACTIONS;
        }

        // 创建控制器类（低代码默认使用虚拟控制器，仅 --file 时才生成实体文件）
        if ($createFile) {
            $controllerPath = $modulePath . DIRECTORY_SEPARATOR . 'controller';
            $this->createControllerFile($controllerPath, $controllerName);
        } else {
            $this->io->write('<comment>⊘ 未创建控制器文件（使用虚拟控制器），如需实体文件请加 -f/--file</comment>');
        }

        // 根据权限入口类型处理配置（配置文件中使用小写下划线名称）
        if ($authEntry === 'admin') {
            // admin 入口：更新 ACL、menu 和 lang
            $this->updateAclConfig($modulePath, $authEntry, $configName, $actions, $authType);
            $this->updateMenuConfig($modulePath, $configName, $authEntry);
            $this->updateLangConfig($modulePath, $configName, $actions);
        } else {
            // guest 及自定义入口（member、teacher 等）：只更新 ACL，不处理 menu 和 lang
            $this->updateAclConfig($modulePath, $authEntry, $configName, $actions, $authType);
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
        $currentModule = $this->context->getCurrentModule();
        $namespace = "app\\{$currentModule}\\controller";
        
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
    private function updateAclConfig(string $modulePath, string $authEntry, string $controllerName, array $actions, string $authType = 'auth'): void
    {
        $aclFile = $modulePath . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'acl.php';
        
        if (!is_file($aclFile)) {
            $this->io->write("<comment>⚠ ACL 配置文件不存在: $aclFile</comment>");
            return;
        }

        // 读取现有配置
        $aclConfig = include $aclFile;
        
        // guest 入口：直接添加控制器和动作，不区分 auth/login
        if ($authEntry === 'guest') {
            if (!isset($aclConfig[$authEntry])) {
                $aclConfig[$authEntry] = [];
            }
            $aclConfig[$authEntry][$controllerName] = $actions;
        } else {
            // 其他入口：区分 auth（需要授权）和 login（需要登录）
            if (!isset($aclConfig[$authEntry])) {
                $aclConfig[$authEntry] = [];
            }
            if (!isset($aclConfig[$authEntry][$authType])) {
                $aclConfig[$authEntry][$authType] = [];
            }
            $aclConfig[$authEntry][$authType][$controllerName] = $actions;
        }
        
        // 写回文件
        $content = "<?php\r\nreturn " . $this->exportArray($aclConfig) . ";";
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
        if (!is_array($menuConfig)) {
            $menuConfig = [];
        }
        
        $className = $this->toCamelCase($controllerName);
        
        // 菜单项结构：url 为 控制器/动作（模块前缀由框架按模块自动补全）
        $menuItem = [
            'url' => "{$controllerName}/admin",
            'title' => "{$className}管理",
            'icon' => 'bi bi-list',
        ];
        
        // 检测顶层遗留的非法数字键（旧版本错误写入的菜单项），仅提示不自动删除
        $strayKeys = array_filter(array_keys($menuConfig), 'is_int');
        if (!empty($strayKeys)) {
            $this->io->write('<comment>⚠ 菜单配置顶层存在 ' . count($strayKeys) . ' 个非法数字键项（旧版本写入），建议手动清理</comment>');
        }
        
        // 兼容旧版扁平结构：['title' => ..., 'children' => [...]] 迁移为按入口分组
        if (isset($menuConfig['children']) && is_array($menuConfig['children'])) {
            $legacyTitle = (isset($menuConfig['title']) && is_string($menuConfig['title']) && $menuConfig['title'] !== '')
                ? $menuConfig['title']
                : $this->getMenuGroupTitle($authEntry);
            $menuConfig = [
                $authEntry => [
                    'title' => $legacyTitle,
                    'children' => $menuConfig['children'],
                ],
            ];
            $this->io->write("<comment>↻ 扁平菜单已迁移为入口分组结构: {$authEntry}</comment>");
        }
        
        // 定位 children：按入口分组，如 ['admin' => ['title' => ..., 'children' => [...]]]
        $groupKey = $authEntry;
        if (!isset($menuConfig[$groupKey]) || !is_array($menuConfig[$groupKey])) {
            $menuConfig[$groupKey] = [
                'title' => $this->getMenuGroupTitle($groupKey),
                'children' => [],
            ];
        }
        if (!isset($menuConfig[$groupKey]['children']) || !is_array($menuConfig[$groupKey]['children'])) {
            $menuConfig[$groupKey]['children'] = [];
        }
        if ($this->hasMenuUrl($menuConfig[$groupKey]['children'], $menuItem['url'])) {
            $this->io->write("<comment>⊘ 菜单项已存在，跳过: {$menuItem['url']}</comment>");
            return;
        }
        $menuConfig[$groupKey]['children'][] = $menuItem;
        
        // 写回文件
        $content = "<?php\r\nreturn " . $this->exportArray($menuConfig) . ";";
        file_put_contents($menuFile, $content);
        
        $this->io->write("<info>✓ 已更新菜单配置: $menuFile</info>");
    }

    /**
     * 判断 children 中是否已存在指定 url 的菜单项
     */
    private function hasMenuUrl(array $children, string $url): bool
    {
        foreach ($children as $item) {
            if (is_array($item) && ($item['url'] ?? null) === $url) {
                return true;
            }
        }
        return false;
    }

    /**
     * 菜单分组的默认标题
     */
    private function getMenuGroupTitle(string $groupKey): string
    {
        $titles = [
            'admin' => '系统管理',
            'member' => '会员中心',
        ];
        return $titles[$groupKey] ?? ($this->toCamelCase($groupKey) . '管理');
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
        
        $className = $this->toCamelCase($controllerName);
        $currentModule = $this->context->getCurrentModule();
        $prefix = "{$currentModule} {$controllerName}";
        
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
            $langConfig["{$currentModule} module {$controllerName} {$action} auth"] = "{$actionClass}{$className}";
        }
        
        // 模块权限描述
        $langConfig["{$currentModule} module {$controllerName} auth"] = "{$className}管理";
        
        // 写回文件
        $content = "<?php\r\nreturn " . $this->exportArray($langConfig) . ";";
        file_put_contents($langFile, $content);
        
        $this->io->write("<info>✓ 已更新语言配置: $langFile</info>");
    }
    
    /**
     * 导出数组为 [] 格式的字符串
     */
    private function exportArray(array $array, int $indent = 0): string
    {
        $output = "[";
        $indentStr = str_repeat('    ', $indent + 1);
        $nextIndentStr = str_repeat('    ', $indent);
        
        $isAssoc = array_keys($array) !== range(0, count($array) - 1);
        
        foreach ($array as $key => $value) {
            $output .= "\n" . $indentStr;
            
            if ($isAssoc) {
                $output .= var_export($key, true) . " => ";
            }
            
            if (is_array($value)) {
                $output .= $this->exportArray($value, $indent + 1);
            } else {
                $output .= var_export($value, true);
            }
            
            $output .= ",";
        }
        
        $output .= "\n" . $nextIndentStr . "]";
        
        return $output;
    }

    /**
     * 将下划线命名转换为大驼峰命名
     */
    private function toCamelCase(string $string): string
    {
        return str_replace(' ', '', ucwords(str_replace('_', ' ', $string)));
    }

    /**
     * 将驼峰命名转换为小写下划线命名
     */
    private function toSnakeCase(string $string): string
    {
        // 先处理连续大写字母（如 XMLParser -> xml_parser）
        $result = preg_replace('/([A-Z]+)([A-Z][a-z])/', '$1_$2', $string);
        // 再处理普通驼峰（如 xmlParser -> xml_parser）
        $result = preg_replace('/([a-z\d])([A-Z])/', '$1_$2', $result);
        return strtolower($result);
    }
}
