<?php
namespace xqkeji\composer;

use Composer\Composer;
use Composer\Factory;
use Composer\IO\IOInterface;

class Table
{
    use PathTrait;

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
     * 创建表格（公开方法）
     */
    public function createTable(string $tableName, array $elements = [], $input = null, $output = null, bool $isTree = false): void
    {
        // 验证表格名称（支持大小写字母、数字和下划线）
        if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $tableName)) {
            $this->io->write('<error>表格名称格式无效，只能包含字母、数字和下划线，且以字母开头</error>');
            return;
        }

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

        // 转换为大驼峰类名（表格/控制器类名，不含模块前缀）
        $className = $this->toCamelCase($tableName);
        // 表名（集合名）= 模块名_控制器名，如 edu_dept、content_category
        $configName = $this->toSnakeCase($currentModule) . '_' . $this->toSnakeCase($tableName);

        // 创建 table 目录
        $tablePath = $modulePath . DIRECTORY_SEPARATOR . 'table';
        if (!is_dir($tablePath)) {
            mkdir($tablePath, 0755, true);
        }

        // 表格元素引用（生成 $el 用）
        $elementRefs = [];
        // 表格中文名（两分支共用：树表经 resolveTreeTableCn 交互解析；普通表经 Lang::resolve 解析，作为控制器显示名）
        $tableCn = '';

        if ($isTree) {
            if (!empty($elements)) {
                $this->io->write("<comment>⚠ 树状表格使用内置默认元素（@Id / ~Name{表} / @Status / ~EditDelete{表}），已忽略命令行传入的元素参数</comment>");
            }
            // 树状表格中文名：优先复用控制器显示名（共享键 {模块} module {表名蛇形}），
            // 不存在则交互询问并去重写入 lang；用于替换树元素模板中的中文名占位符
            $tableCn = $this->resolveTreeTableCn($modulePath, $currentModule, $tableName, $className);
            // 复制并改名 tree 元素到模块 table/element/（不带 tree 子目录），元素中文名随表格中文名更新
            $this->ensureTreeElements($modulePath, $currentModule, $tableName, $className, $configName, $tableCn);
            // 树状表格默认元素（@Id / ~Name{表} / @Status / ~EditDelete{表}），忽略命令行传入的 elements
            $elementRefs = [
                '@Id',
                '~Name' . $className,
                '@Status',
                '~EditDelete' . $className,
            ];
        } else {
            // 非树状：逐元素查找或创建（命令行传入的 elements）
            if (!empty($elements)) {
                $elementPath = $tablePath . DIRECTORY_SEPARATOR . 'element';
                if (!is_dir($elementPath)) {
                    mkdir($elementPath, 0755, true);
                }

                foreach ($elements as $element) {
                    $elementRef = $this->processElement($modulePath, $element, $currentModule, $input, $output);
                    if ($elementRef !== null) {
                        $elementRefs[] = $elementRef;
                    }
                }
            }
            // 普通表格中文名：统一优先级（设置 > 读取 lang > 交互提示），作为控制器显示名
            $tableCn = Lang::resolve(
                $this->io,
                $modulePath,
                "{$currentModule} module " . $this->toSnakeCase($tableName),
                null,
                "请输入表格 '" . $this->toSnakeCase($tableName) . "' 的中文名称（留空使用 '{$className}'）：",
                $className
            );
        }

        // 创建表格类
        $this->createTableFile($tablePath, $className, $configName, $elementRefs, $currentModule, $isTree);

        // 自动创建控制器（树表与普通表都创建，但动作/元素不同）：
        //   - 树表：复制 tree 动作类（admin/add/move）+ 初始化集合，动作含 move、复制 tree 元素
        //   - 普通表：创建单文件控制器（admin/add/edit/delete）+ acl/menu/lang 初始化，
        //            不含 move、不复制 tree 元素、不初始化树集合
        if ($isTree) {
            $this->ensureTreeController($modulePath, $currentModule, $tableName, $tableCn);
            // 初始化树集合（建索引 + 根节点）：作为代码生成器的一部分直接执行，
            // 不依赖任何 composer 事件。集合名 = 模块名_控制器名（$configName）
            $this->seedTreeCollection($configName);
        } else {
            $this->ensureNormalController($modulePath, $currentModule, $tableName, $tableCn);
        }

        // 自动切换为表格模式
        $this->context->switchMode('table');
    }

    /**
     * 处理表格元素（查找或创建）
     */
    private function processElement(string $modulePath, string $elementName, string $currentModule, $input = null, $output = null): ?string
    {
        // 转换为大驼峰类名
        $className = $this->toCamelCase($elementName);
        // 转换为小写下划线格式
        $configName = $this->toSnakeCase($elementName);

        // 1. 先在 base 模块查找
        $baseElementPath = $this->findElementInModule('base', $className);
        if ($baseElementPath !== null) {
            $this->io->write("<info>✓ 在 base 模块找到元素: $className</info>");
            return '@' . $className;
        }

        // 2. 在当前模块查找
        $currentElementPath = $this->findElementInModule($currentModule, $className);
        if ($currentElementPath !== null) {
            $this->io->write("<info>✓ 在当前模块找到元素: $className</info>");
            return '~' . $className;
        }

        // 3. 创建新元素 - 解析中文名称（统一优先级：设置 > 读取 lang > 交互提示）
        $elementPath = $modulePath . DIRECTORY_SEPARATOR . 'table' . DIRECTORY_SEPARATOR . 'element';
        if (!is_dir($elementPath)) {
            mkdir($elementPath, 0755, true);
        }

        // 元素中文名统一解析（键全小写蛇形）；lang 已记录则直接复用，否则交互提示
        $elementText = Lang::resolve(
            $this->io,
            $modulePath,
            "{$currentModule} {$configName} name",
            null,
            "请输入元素 '{$configName}' 的中文名称（留空使用 '{$className}'）：",
            $className
        );
        // 兜底确保非空（非交互或留空回退 $className）
        if ($elementText === '') {
            $elementText = $className;
        }

        $this->createElementFile($elementPath, $className, $configName, $elementText);
        $this->io->write("<info>✓ 已创建表格元素: $className</info>");
        return '~' . $className;
    }

    /**
     * 在指定模块中查找表格元素
     */
    private function findElementInModule(string $moduleName, string $className): ?string
    {
        $rootPath = $this->getRootPath();

        // 1. 检查 app 目录下的模块
        $localModulePath = $rootPath . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . $moduleName;
        if (is_dir($localModulePath)) {
            $elementFile = $localModulePath . DIRECTORY_SEPARATOR . 'table' . DIRECTORY_SEPARATOR . 'element' . DIRECTORY_SEPARATOR . $className . '.php';
            if (is_file($elementFile)) {
                return $elementFile;
            }
        }

        // 2. 从 config/composer.php 获取模块对应的包名
        $composerConfigFile = $rootPath . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'composer.php';
        if (!is_file($composerConfigFile)) {
            return null;
        }

        $composerConfig = include $composerConfigFile;
        if (!isset($composerConfig[$moduleName])) {
            return null;
        }

        $packageName = $composerConfig[$moduleName];

        // 3. 在 vendor 目录下的包中查找
        $vendorPackagePath = $rootPath . DIRECTORY_SEPARATOR . 'vendor'
            . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $packageName);

        if (is_dir($vendorPackagePath)) {
            // 检查 src/table/element/
            $srcPath = $vendorPackagePath . DIRECTORY_SEPARATOR . 'src';
            if (is_dir($srcPath)) {
                $elementFile = $srcPath . DIRECTORY_SEPARATOR . 'table' . DIRECTORY_SEPARATOR . 'element' . DIRECTORY_SEPARATOR . $className . '.php';
                if (is_file($elementFile)) {
                    return $elementFile;
                }
            }

            // 检查包根目录的 table/element/
            $elementFile = $vendorPackagePath . DIRECTORY_SEPARATOR . 'table' . DIRECTORY_SEPARATOR . 'element' . DIRECTORY_SEPARATOR . $className . '.php';
            if (is_file($elementFile)) {
                return $elementFile;
            }
        }

        return null;
    }

    /**
     * 创建表格元素文件
     */
    private function createElementFile(string $elementPath, string $className, string $configName, string $elementText): void
    {
        $filePath = $elementPath . DIRECTORY_SEPARATOR . $className . '.php';

        if (is_file($filePath)) {
            $this->io->write("<comment>⚠ 表格元素已存在: $filePath</comment>");
            return;
        }

        $currentModule = $this->context->getCurrentModule();
        $content = $this->generateElementContent($currentModule, $className, $configName, $elementText);
        file_put_contents($filePath, $content);
    }

    /**
     * 生成表格元素类内容
     */
    private function generateElementContent(string $moduleName, string $className, string $configName, string $elementText): string
    {
        $namespace = "xqkeji\\app\\{$moduleName}\\table\\element";
        $useListItem = 'xqkeji' . '\\' . 'form' . '\\' . 'element' . '\\' . 'ListItem';
        $useModel = 'xqkeji' . '\\' . 'mvc' . '\\' . 'builder' . '\\' . 'Model';

        return "<?php\nnamespace {$namespace};\n\nuse {$useListItem};\nuse {$useModel};\n\nclass {$className} extends ListItem\n{\n    protected \$name = '{$configName}';\n    protected \$text = '{$elementText}';\n    protected \$attrs = [\n        'style' => 'min-width:200px;',\n    ];\n}\n";
    }

    /**
     * 创建表格文件
     */
    private function createTableFile(string $tablePath, string $className, string $configName, array $elementRefs, string $moduleName, bool $isTree = false): void
    {
        $filePath = $tablePath . DIRECTORY_SEPARATOR . $className . '.php';

        if (is_file($filePath)) {
            $this->io->write("<error>表格已存在: $filePath</error>");
            return;
        }

        $content = $this->generateTableContent($moduleName, $className, $configName, $elementRefs, $isTree);
        file_put_contents($filePath, $content);

        $this->io->write("<info>✓ 表格已创建: $filePath</info>");
    }

    /**
     * 生成表格类内容
     */
    private function generateTableContent(string $moduleName, string $className, string $configName, array $elementRefs, bool $isTree = false): string
    {
        $namespace = "xqkeji\\app\\{$moduleName}\\table";

        // 构建元素列表字符串
        $elementsStr = '';
        if (!empty($elementRefs)) {
            $elementsStr = "\n";
            foreach ($elementRefs as $ref) {
                $elementsStr .= "        '{$ref}',\n";
            }
            $elementsStr .= "    ";
        }

        // 根据 $isTree 选择基类与默认 foot
        if ($isTree) {
            $useTable = 'xqkeji' . '\\' . 'form' . '\\' . 'TreegridTable';
            $baseClass = 'TreegridTable';
            $foot = "'~Foot{$className}'";
        } else {
            $useTable = 'xqkeji' . '\\' . 'form' . '\\' . 'Table';
            $baseClass = 'Table';
            $foot = "'@Foot'";
        }

        return "<?php\nnamespace {$namespace};\n\nuse {$useTable};\n\nclass {$className} extends {$baseClass}\n{\n    protected \$name = '{$configName}';\n    protected \$foot = {$foot};\n\n    // 表格元素列表\n    protected \$el = [{$elementsStr}];\n}\n";
    }

    /**
     * 树状表格：解析中文名（统一优先级：设置 > 读取 lang > 交互提示）
     *
     * 优先复用控制器显示名（共享键 {模块} module {表名蛇形}），与表单/表格共享同一中文名；
     * 读不到时交互提示用户设置（非交互回退 $className），并去重写入 lang。
     * 返回的中文名用于替换树元素模板中的 {中文名称}/{中文名} 占位符。
     */
    private function resolveTreeTableCn(string $modulePath, string $currentModule, string $tableName, string $className): string
    {
        $langKey = "{$currentModule} module " . $this->toSnakeCase($tableName);

        return Lang::resolve(
            $this->io,
            $modulePath,
            $langKey,
            null,
            "请输入树状表格 '" . $this->toSnakeCase($tableName) . "' 的中文名称（留空使用 '{$className}'）：",
            $className
        );
    }

    /**
     * 树状表格：复制并改名 tree 元素到模块的 table/element/（不带 tree 子目录）
     *
     * 源：插件自身 src/example/src/table/element/tree/
     * 目标：{模块路径}/table/element/（文件名 Tree 后缀 -> 表格大驼峰名，如 NameTree -> NameTestTree）
     * 复制时替换：
     *   - 命名空间占位符 {MODULE_NAME} -> 当前模块
     *   - 元素类名 / 引用中的 Tree 后缀 -> 表格大驼峰名（NameTree -> NameTestTree 等）
     *   - 中文名占位符 {中文名称}/{中文名} -> 表格中文名
     *   - FootTree 的 $name 占位符 {TABELE_NAME} -> 表名（模块_表）
     *   - ToolbarTree 的 $name（list-toolbar-tree）-> list-toolbar-{表蛇形}
     * 元素文件已存在则幂等跳过。
     */
    private function ensureTreeElements(string $modulePath, string $currentModule, string $tableName, string $className, string $configName, string $tableCn): void
    {
        $elementDir = $modulePath . DIRECTORY_SEPARATOR . 'table' . DIRECTORY_SEPARATOR . 'element';
        if (!is_dir($elementDir)) {
            mkdir($elementDir, 0755, true);
        }

        // 插件自身的 example 树状元素模板目录（与 Table.php 同级的 src 下）
        $treeTemplateDir = __DIR__ . DIRECTORY_SEPARATOR . 'example' . DIRECTORY_SEPARATOR
            . 'src' . DIRECTORY_SEPARATOR . 'table' . DIRECTORY_SEPARATOR . 'element' . DIRECTORY_SEPARATOR . 'tree';

        if (!is_dir($treeTemplateDir)) {
            $this->io->write("<error>未找到树状元素模板目录: {$treeTemplateDir}</error>");
            return;
        }

        $files = glob($treeTemplateDir . DIRECTORY_SEPARATOR . '*.php') ?: [];
        if (empty($files)) {
            $this->io->write("<comment>⚠ 树状元素模板目录为空，未复制任何文件</comment>");
            return;
        }

        $tableSnake = $this->toSnakeCase($tableName);

        foreach ($files as $templateFile) {
            $content = file_get_contents($templateFile);
            if ($content === false) {
                $this->io->write("<error>读取树状元素模板失败: {$templateFile}</error>");
                continue;
            }

            // 命名空间占位符 {MODULE_NAME} -> 当前模块
            $content = str_replace('{MODULE_NAME}', $currentModule, $content);
            // 元素类名 / 引用中的 Tree 后缀 -> 表格大驼峰名（NameTree -> NameTestTree 等）
            $content = str_replace('Tree', $className, $content);
            // 中文名占位符（NameTree::$text 的 {中文名称} 与 ToolbarTree title 的 {中文名}）
            $content = str_replace(['{中文名称}', '{中文名}'], $tableCn, $content);
            // FootTree::$name 的 {TABELE_NAME}（模板原拼写）-> 表名（模块_表）
            $content = str_replace('{TABELE_NAME}', $configName, $content);
            // ToolbarTree::$name 的 list-toolbar-tree -> list-toolbar-{表蛇形}
            $content = str_replace('list-toolbar-tree', 'list-toolbar-' . $tableSnake, $content);

            // 文件名：Tree 后缀 -> 表格大驼峰名（不带 tree 子目录）
            $base = basename($templateFile);
            $newBase = str_replace('Tree', $className, $base);
            $target = $elementDir . DIRECTORY_SEPARATOR . $newBase;

            if (is_file($target)) {
                $this->io->write("<comment>⚠ 树状元素已存在，跳过: {$target}</comment>");
                continue;
            }
            file_put_contents($target, $content);
            $this->io->write("<info>✓ 已复制树状元素: {$target}</info>");
        }
    }

    /**
     * 树形表格：自动检查并复制对应的树状控制器动作类，并补齐控制器配置初始化
     *
     * 目标目录：{模块路径}/controller/{表格名全小写蛇形}/
     * 源模板：插件自身的 src/example/src/controller/tree/
     * 复制时替换命名空间占位符 {MODULE_NAME} -> 当前模块、{CONTROLLER_NAME} -> 表格名全小写蛇形
     * （目录名与命名空间段均为全小写，符合框架 PSR-4：xqkeji\app\{模块}\controller\{表名}\）
     *
     * 复制动作类后，复用 Controller::initControllerConfig 补齐 acl.php / menu.php / zh_cn.php
     * 的控制器相关项，使树状表格自动创建的控制器与手动 xqkeji:controller 创建的控制器保持一致。
     * （acl/menu/lang 三项写入均为幂等：acl 覆盖同值、menu 去重跳过、lang 去重不覆盖）
     *
     * @param string $tableCn 表格中文名（来自 resolveTreeTableCn，作为控制器显示名）
     */
    private function ensureTreeController(string $modulePath, string $currentModule, string $tableName, string $tableCn): void
    {
        // 目录名 / 命名空间段统一使用全小写蛇形（如 test_tree），与框架命名约定一致
        $dirName = $this->toSnakeCase($tableName);
        $controllerDir = $modulePath . DIRECTORY_SEPARATOR . 'controller' . DIRECTORY_SEPARATOR . $dirName;

        // 插件自身的 example 树状控制器模板目录（与 Table.php 同级的 src 下）
        $treeTemplateDir = __DIR__ . DIRECTORY_SEPARATOR . 'example' . DIRECTORY_SEPARATOR
            . 'src' . DIRECTORY_SEPARATOR . 'controller' . DIRECTORY_SEPARATOR . 'tree';

        if (!is_dir($treeTemplateDir)) {
            $this->io->write("<error>未找到树状控制器模板目录: {$treeTemplateDir}</error>");
            return;
        }

        if (!is_dir($controllerDir)) {
            if (!mkdir($controllerDir, 0755, true) && !is_dir($controllerDir)) {
                $this->io->write("<error>创建树状控制器目录失败: {$controllerDir}</error>");
                return;
            }
            $this->io->write("<info>✓ 已创建树状控制器目录: {$controllerDir}</info>");
        } else {
            $this->io->write("<comment>⚠ 树状控制器目录已存在，跳过复制（仅补齐配置初始化）: {$controllerDir}</comment>");
        }

        $files = glob($treeTemplateDir . DIRECTORY_SEPARATOR . '*.php') ?: [];
        if (empty($files)) {
            $this->io->write("<comment>⚠ 树状控制器模板目录为空，未复制任何文件: {$treeTemplateDir}</comment>");
        } else {
            foreach ($files as $templateFile) {
                $content = file_get_contents($templateFile);
                if ($content === false) {
                    $this->io->write("<error>读取树状控制器模板失败: {$templateFile}</error>");
                    continue;
                }
                // 替换命名空间占位符（{CONTROLLER_NAME} 使用全小写蛇形，与目录名一致）
                $content = str_replace(
                    ['{MODULE_NAME}', '{CONTROLLER_NAME}'],
                    [$currentModule, $dirName],
                    $content
                );
                $target = $controllerDir . DIRECTORY_SEPARATOR . basename($templateFile);
                if (is_file($target)) {
                    $this->io->write("<comment>⚠ 树状控制器动作类已存在，跳过: {$target}</comment>");
                    continue;
                }
                file_put_contents($target, $content);
                $this->io->write("<info>✓ 已复制树状控制器动作类: {$target}</info>");
            }
        }

        // 补齐控制器配置初始化（acl / menu / lang），与普通 xqkeji:controller 创建保持一致
        $configName = $this->toSnakeCase($tableName);
        $controller = new Controller($this->io, $this->composer);
        $controller->initControllerConfig(
            $modulePath,
            $configName,
            ['admin', 'edit', 'delete', 'change', 'add', 'move'],
            $tableCn
        );
    }

    /**
     * 普通（非树状）表格：自动创建单文件控制器并补齐 acl/menu/lang 初始化
     *
     * 与普通 xqkeji:controller --file 创建保持一致：
     *   - 创建 controller/{大驼峰表名}.php（继承 xqkeji\mvc\Controller，动作由框架基类按约定解析，无需单独动作类文件）
     *   - 动作集为 admin/edit/delete/change/add（含 change，不含树表专属的 move）
     *   - 不复制 tree 元素、不初始化树集合
     * 控制器文件已存在则幂等跳过（仅补齐配置初始化）。
     *
     * @param string $tableCn 表格中文名（作为控制器显示名，来自 Lang::resolve）
     */
    private function ensureNormalController(string $modulePath, string $currentModule, string $tableName, string $tableCn): void
    {
        $className = $this->toCamelCase($tableName);
        $controllerFile = $modulePath . DIRECTORY_SEPARATOR . 'controller' . DIRECTORY_SEPARATOR . $className . '.php';

        if (!is_file($controllerFile)) {
            $namespace = "xqkeji\\app\\{$currentModule}\\controller";
            $content = "<?php\nnamespace {$namespace};\n\nuse xqkeji\\mvc\\Controller;\n\nclass {$className} extends Controller\n{\n\n}\n";
            if (!is_dir(dirname($controllerFile))) {
                mkdir(dirname($controllerFile), 0755, true);
            }
            file_put_contents($controllerFile, $content);
            $this->io->write("<info>✓ 已创建普通表格控制器: {$controllerFile}</info>");
        } else {
            $this->io->write("<comment>⚠ 普通表格控制器已存在，跳过创建（仅补齐配置初始化）: {$controllerFile}</comment>");
        }

        // 补齐控制器配置初始化（acl / menu / lang），与普通 xqkeji:controller 创建保持一致
        // 动作集含 change；不含树表专属的 move；普通表中文名作为控制器显示名
        $controller = new Controller($this->io, $this->composer);
        $controller->initControllerConfig(
            $modulePath,
            $this->toSnakeCase($tableName),
            ['admin', 'edit', 'delete', 'change', 'add'],
            $tableCn
        );
    }

    /**
     * 树状表格：即时初始化 MongoDB 集合（建索引 + 根节点）
     *
     * 作为代码生成器的一部分，在生成树表文件时直接执行，不依赖任何 composer 事件。
     * 逻辑参考 xq-app-content/src/composer/Install.php：
     *   - 建索引：name / name+parent_id / left_value / right_value
     *   - 插入固定 _id 的根节点（嵌套集合左右值 left=1 / right=2）
     * 集合名 = 模块名_控制器名（即传入的 $collection）。
     *
     * 容错：config 缺失、未启用 mongodb 扩展、MongoDB 不可达 均只告警，不阻断文件生成。
     */
    private function seedTreeCollection(string $collection): void
    {
        $containerFile = $this->getRootConfigPath() . DIRECTORY_SEPARATOR . 'container.php';
        if (!is_file($containerFile)) {
            $this->io->write("<comment>⚠ 未找到 config/container.php，跳过树集合 {$collection} 初始化（请手动初始化）</comment>");
            return;
        }
        $config = include $containerFile;
        if (!isset($config['db'])) {
            $this->io->write("<comment>⚠ config 中无 db 配置，跳过树集合 {$collection} 初始化</comment>");
            return;
        }
        if (!class_exists('MongoDB\\Driver\\Manager')) {
            $this->io->write("<comment>⚠ 未启用 MongoDB 扩展，跳过树集合 {$collection} 初始化</comment>");
            return;
        }

        $db = $config['db'];
        $hostname = $db['hostname'] ?? '';
        $hostport = $db['hostport'] ?? '';
        $database = $db['database'] ?? '';
        $username = $db['username'] ?? '';
        $password = $db['password'] ?? '';
        $uri = !empty($username)
            ? "mongodb://{$username}:{$password}@{$hostname}:{$hostport}"
            : "mongodb://{$hostname}:{$hostport}";

        try {
            $manager = new \MongoDB\Driver\Manager($uri, [
                'serverSelectionTryOnce'   => false,
                'serverSelectionTimeoutMS' => 500,
                'connectTimeoutMS'         => 500,
            ]);

            // 建索引：name / name+parent_id / left_value / right_value
            $indexes = [
                ['name' => "{$collection}_name",            'key' => ['name' => 1]],
                ['name' => "{$collection}_name_parent_id",  'key' => ['name' => 1, 'parent_id' => 1]],
                ['name' => "{$collection}_left_value",      'key' => ['left_value' => 1]],
                ['name' => "{$collection}_right_value",     'key' => ['right_value' => 1]],
            ];
            foreach ($indexes as $idx) {
                $cmd = new \MongoDB\Driver\Command([
                    'createIndexes' => $collection,
                    'indexes'       => [$idx],
                ]);
                $res = $manager->executeCommand($database, $cmd)->toArray();
                $ok = !empty($res) ? intval($res[0]->ok) : 0;
                $this->io->write($ok > 0
                    ? "<info>✓ 创建集合 {$collection} 索引 {$idx['name']} 成功</info>"
                    : "<comment>⚠ 创建集合 {$collection} 索引 {$idx['name']} 失败</comment>");
            }

            // 根节点（嵌套集合左右值）；固定 _id，集合间相互隔离不冲突
            $rootId = new \MongoDB\BSON\ObjectId('58514b454a495f524f4f5430');
            $countCmd = new \MongoDB\Driver\Command([
                'count' => $collection,
                'query' => ['_id' => $rootId],
            ]);
            $cnt = $manager->executeCommand($database, $countCmd)->toArray();
            $exists = !empty($cnt) && intval($cnt[0]->n) > 0;
            if (!$exists) {
                $bulk = new \MongoDB\Driver\BulkWrite();
                $bulk->insert([
                    '_id'         => $rootId,
                    'name'        => 'XQKEJI_TREE_ROOT',
                    'parent_id'   => '',
                    'depth'       => 0,
                    'left_value'  => 1,
                    'right_value' => 2,
                    'create_time' => time(),
                    'update_time' => time(),
                ]);
                $manager->executeBulkWrite("{$database}.{$collection}", $bulk);
                $this->io->write("<info>✓ 初始化集合 {$collection} 根节点成功</info>");
            } else {
                $this->io->write("<comment>⚠ 集合 {$collection} 根节点已存在，跳过</comment>");
            }
        } catch (\Throwable $e) {
            $this->io->write("<comment>⚠ 树集合 {$collection} 初始化失败（不影响文件生成）：{$e->getMessage()}</comment>");
        }
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
