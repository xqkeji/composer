<?php
namespace xqkeji\composer;

use Composer\IO\IOInterface;

/**
 * 语言配置（lang/zh_cn.php）读写
 *
 * 键名约定：
 *   '{模块} {控制器} {动作} title'            动作页面标题
 *   '{模块} {控制器} {动作} success|failed'    操作结果提示
 *   '{模块} module {控制器} {动作} auth'        动作权限描述
 *   '{模块} module {控制器} auth'              控制器权限描述
 *   '{模块} module {控制器}'                   控制器显示名称（中文名）
 *   '{模块} module title' / '{模块} module auth' 模块名称与权限描述
 */
class Lang
{
    use PathTrait;

    /**
     * 预定义动作的中文名
     */
    public const ACTION_TITLES = [
        'admin' => '管理',
        'add' => '添加',
        'edit' => '编辑',
        'delete' => '删除',
        'change' => '修改',
        'b_delete' => '批量删除',
        'b_open' => '批量启用',
        'b_close' => '批量禁用',
        'b_order' => '批量排序',
        'index' => '首页',
        'list' => '列表',
        'view' => '查看',
        'display' => '查看',
        'export' => '导出',
        'login' => '登录',
        'logout' => '退出登录',
        'reg' => '注册',
        'reset' => '重置',
        'publish' => '发布',
        'captcha' => '验证码',
        'emailcode' => '邮箱验证码',
        'getoption' => '获取选项',
        'submenu' => '子菜单',
        'subnode' => '子节点',
        'change_password' => '修改密码',
        'update_config' => '更新配置',
        'update_statics' => '更新静态文件',
    ];

    /**
     * 需要生成成功/失败提示的动作
     */
    private const RESULT_ACTIONS = [
        'add', 'edit', 'delete', 'change', 'b_delete',
        'b_open', 'b_close', 'b_order', 'publish', 'reset', 'export',
    ];

    /**
     * 组合动作标题：admin → 「对象管理」，其余 → 「动作+对象」
     */
    public static function actionTitle(string $action, string $subject): string
    {
        if ($action === 'admin') {
            return $subject . '管理';
        }
        $verb = self::ACTION_TITLES[$action] ?? self::toCamelCase($action);
        return $verb . $subject;
    }

    /**
     * 批量写入控制器下多个动作的语言配置（一次读写）
     *
     * @param array $actions 动作名列表
     * @param array $actionTitles 动作中文名覆盖，格式 ['add' => '新增']
     */
    public static function writeActions(
        IOInterface $io,
        string $modulePath,
        string $module,
        string $controller,
        array $actions,
        ?string $controllerTitle = null,
        array $actionTitles = []
    ): void {
        $langFile = self::langFile($modulePath);
        $lang = self::load($langFile, $io);
        if ($lang === null) {
            return;
        }

        $subject = $controllerTitle ?: self::toCamelCase($controller);
        $prefix = "{$module} {$controller}";

        // 控制器菜单项标题（{控制器中文名}管理）自映射到语言文件，与菜单实际显示的标题保持一致
        self::set($lang, $subject . '管理', $subject . '管理');

        foreach ($actions as $action) {
            $title = $actionTitles[$action] ?? self::actionTitle($action, $subject);
            self::set($lang, "{$prefix} {$action} title", $title);

            if (in_array($action, self::RESULT_ACTIONS, true)) {
                self::set($lang, "{$prefix} {$action} success", $title . '成功');
                self::set($lang, "{$prefix} {$action} failed", $title . '失败');
            }

            self::set($lang, "{$module} module {$controller} {$action} auth", $title);
        }

        // admin 列表加载成功/失败提示（特殊文案：加载{控制器中文名}管理列表成功/失败，区别于其它动作的生成格式）
        if (in_array('admin', $actions, true)) {
            self::set($lang, "{$prefix} admin success", '加载' . $subject . '管理列表成功');
            self::set($lang, "{$prefix} admin failed", '加载' . $subject . '管理列表失败');
        }

        // 控制器级权限描述
        self::set($lang, "{$module} module {$controller} auth", $controllerTitle
            ? ($controllerTitle . '管理')
            : (self::toCamelCase($controller) . '管理'));

        // 控制器显示名称（{模块} module {控制器} => {控制器中文名}），供框架 lang() 解析控制器名
        self::set($lang, "{$module} module {$controller}", $subject);

        self::save($langFile, $lang);
        $io->write("<info>✓ 已更新语言配置: $langFile</info>");
    }

    /**
     * 读取控制器的中文名（从 admin 动作的标题反推，不存在返回 null）
     */
    public static function readControllerTitle(string $modulePath, string $module, string $controller): ?string
    {
        $langFile = self::langFile($modulePath);
        if (!is_file($langFile)) {
            return null;
        }
        $lang = include $langFile;
        $title = $lang["{$module} {$controller} admin title"] ?? null;
        if (!is_string($title) || $title === '') {
            return null;
        }
        return preg_replace('/管理$/u', '', $title) ?: null;
    }

    /**
     * 去重写入单条语言配置（保留已有翻译）
     *
     * 若 $key 已存在且非空，则保留原有值不覆盖（满足「本来有翻译的不用加，没有的加上去」）；
     * 仅当 key 不存在或值为空字符串时才写入。返回是否实际写入。
     */
    public static function ensureName(IOInterface $io, string $modulePath, string $key, string $value): bool
    {
        $langFile = self::langFile($modulePath);
        $lang = self::load($langFile, $io);
        if ($lang === null) {
            return false;
        }
        if (isset($lang[$key]) && $lang[$key] !== '') {
            return false;
        }
        $lang[$key] = $value;
        self::save($langFile, $lang);
        $io->write("<info>✓ 已写入语言配置（去重）: $key => $value</info>");
        return true;
    }

    /**
     * 写入模块语言配置
     */
    public static function writeModule(IOInterface $io, string $modulePath, string $module, string $moduleTitle): void
    {
        $langFile = self::langFile($modulePath);
        $lang = self::load($langFile, $io);
        if ($lang === null) {
            return;
        }

        // 去重：已有非空翻译则保留，不覆盖（满足「本来有翻译的不用加」）
        self::set($lang, "{$module} module title", $moduleTitle);
        self::set($lang, "{$module} module auth", $moduleTitle);
        // 菜单分组标题自映射（键即中文标题，供框架 lang() 解析，可后续翻译覆盖；缺省即显示中文本身）
        self::set($lang, $moduleTitle, $moduleTitle);

        self::save($langFile, $lang);
        $io->write("<info>✓ 已更新语言配置（模块名称）: $langFile</info>");
    }

    /**
     * 去重赋值：仅当 key 不存在或值为空时才写入，保留已有非空翻译
     */
    private static function set(array &$lang, string $key, string $value): void
    {
        if (!isset($lang[$key]) || $lang[$key] === '') {
            $lang[$key] = $value;
        }
    }

    private static function langFile(string $modulePath): string
    {
        return $modulePath . DIRECTORY_SEPARATOR . 'lang' . DIRECTORY_SEPARATOR . 'zh_cn.php';
    }

    private static function load(string $langFile, IOInterface $io): ?array
    {
        if (!is_file($langFile)) {
            $io->write("<comment>⚠ 语言配置文件不存在: $langFile</comment>");
            return null;
        }
        $lang = include $langFile;
        return is_array($lang) ? $lang : [];
    }

    private static function save(string $langFile, array $lang): void
    {
        file_put_contents($langFile, "<?php\r\nreturn " . self::exportArray($lang) . ';');
    }

    private static function toCamelCase(string $string): string
    {
        return str_replace(' ', '', ucwords(str_replace('_', ' ', $string)));
    }
}
