<?php
namespace xqkeji\composer;

use Composer\IO\IOInterface;

/**
 * 语言配置（lang/zh_cn.php）读写
 *
 * 键名约定（全部小写、蛇形，与框架一致）：
 *   '{模块} {控制器} {动作} title'            动作页面标题
 *   '{模块} {控制器} {动作} success|failed'    操作结果提示
 *   '{模块} module {控制器} {动作} auth'        动作权限描述
 *   '{模块} module {控制器} auth'              控制器权限描述
 *   '{模块} module {控制器}'                   控制器显示名称（中文名）
 *   '{模块} module title' / '{模块} module auth' 模块名称与权限描述
 *   '{模块} {元素蛇形} name'                    元素中文名（备用）
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

        $subject = ($controllerTitle !== null && $controllerTitle !== '') ? $controllerTitle : self::toCamelCase($controller);
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
        // 仅在显式指定或可读取到中文名时写入；空白（未指定且 lang 中无记录）则不写，避免残留驼峰默认名
        if ($controllerTitle !== null && $controllerTitle !== '') {
            self::set($lang, "{$module} module {$controller}", $controllerTitle);
        }

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
     * 读取单条语言配置值（键统一 strtolower 后查找）；不存在返回 null
     */
    public static function getValue(string $modulePath, string $key): ?string
    {
        $key = strtolower($key);
        $langFile = self::langFile($modulePath);
        if (!is_file($langFile)) {
            return null;
        }
        $lang = include $langFile;
        if (!is_array($lang) || !isset($lang[$key])) {
            return null;
        }
        $v = $lang[$key];
        return is_string($v) ? $v : null;
    }

    /**
     * 去重写入单条语言配置（保留已有翻译）
     *
     * 若 $key 已存在且非空，则保留原有值不覆盖（满足「本来有翻译的不用加，没有的加上去」）；
     * 仅当 key 不存在或值为空字符串时才写入。返回是否实际写入。
     * 键统一 strtolower，确保 zh_cn.php 下标全小写（值不改写）。
     */
    public static function ensureName(IOInterface $io, string $modulePath, string $key, string $value): bool
    {
        $key = strtolower($key);
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
     * 显式写入单条语言配置（覆盖式）。
     *
     * 用于 -t 显式指定中文名：用户明确给定即以设置值为准，覆盖已有值。
     * 键统一 strtolower，确保 zh_cn.php 下标全小写（值不改写）。
     */
    public static function put(IOInterface $io, string $modulePath, string $key, string $value): void
    {
        $key = strtolower($key);
        $langFile = self::langFile($modulePath);
        $lang = self::load($langFile, $io);
        if ($lang === null) {
            return;
        }
        $lang[$key] = $value;
        self::save($langFile, $lang);
        $io->write("<info>✓ 已写入语言配置（覆盖）: $key => $value</info>");
    }

    /**
     * 解析中文名（统一优先级，适用于模块/控制器/表单/表格/元素/动作）：
     *   1. 显式设置（-t 非空）：用设置值，并覆盖写入 zh_cn.php（用户明确给定即以设置值为准）；
     *   2. 未设置：试读 zh_cn.php，读到了直接用（不重复写入，原值已是设置值）；
     *   3. 都没有：交互提示用户设置（交互模式下询问；非交互模式或留空则回退 $default）。
     *
     * 第三优先级改为「交互提示」而非空白：需要中文名、既未显式设置又读不到时，
     * 主动询问用户，避免产生无中文名/驼峰默认名的脏数据。
     *
     * @param string|null $setTitle     命令行 -t 传入的中文名（可能为空串或 null）
     * @param string      $key          语言键（统一 strtolower）
     * @param string|null $promptLabel  交互提示文案（如 "请输入表单 'xxx' 的中文名称："）；传 null 则不交互
     * @param string      $default      非交互模式或留空时的回退默认值（通常为类名/蛇形名）
     */
    public static function resolve(IOInterface $io, string $modulePath, string $key, ?string $setTitle, ?string $promptLabel = null, string $default = ''): string
    {
        // 1. 显式设置（非空）：用设置值并覆盖写入
        if ($setTitle !== null && $setTitle !== '') {
            self::put($io, $modulePath, $key, $setTitle);
            return $setTitle;
        }

        // 2. 试读 lang：读到了直接用（不重复写）
        $read = self::getValue($modulePath, $key);
        if ($read !== null && $read !== '') {
            return $read;
        }

        // 3. 都没有：交互提示用户设置；非交互或留空则回退默认
        $value = $default;
        if ($io->isInteractive() && $promptLabel !== null) {
            $asked = $io->ask("<question>{$promptLabel}</question> ", $default);
            if ($asked !== null && $asked !== '') {
                $value = $asked;
            }
        }
        if ($value !== '') {
            // 去重写入（刚确认 lang 中为空，必然写入）
            self::ensureName($io, $modulePath, $key, $value);
        }
        return $value;
    }

    /**
     * 写入模块语言配置
     *
     * @param string $moduleTitle 模块显示名（已带「管理」后缀，如 教学管理）；空串表示「空白」不写入
     */
    public static function writeModule(IOInterface $io, string $modulePath, string $module, string $moduleTitle): void
    {
        if ($moduleTitle === '') {
            // 空白：不写入，保留 lang 原状（满足「未指定且 lang 无记录则不写」）
            return;
        }
        $langFile = self::langFile($modulePath);
        $lang = self::load($langFile, $io);
        if ($lang === null) {
            return;
        }

        // 显式设置以覆盖式写入（用户明确给定即为准）
        $lang["{$module} module title"] = $moduleTitle;
        $lang["{$module} module auth"] = $moduleTitle;
        // 菜单分组标题自映射（键即中文标题，供框架 lang() 解析，可后续翻译覆盖；缺省即显示中文本身）
        self::set($lang, $moduleTitle, $moduleTitle);

        self::save($langFile, $lang);
        $io->write("<info>✓ 已更新语言配置（模块名称）: $langFile</info>");
    }

    /**
     * 去重赋值：仅当 key 不存在或值为空时才写入，保留已有非空翻译。
     * 键统一 strtolower，确保 zh_cn.php 下标全小写。
     */
    private static function set(array &$lang, string $key, string $value): void
    {
        $key = strtolower($key);
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
