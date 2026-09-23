<?php
namespace xqkeji\composer;

use Composer\Composer;
use Composer\Factory;
use Composer\IO\IOInterface;

class Table
{
    use PathTrait;
    use ElInsertTrait;

    /**
     * 自动配套表单时，从表格列中剔除的“仅表格”元素（主键 / 时间戳 / 操作列），按蛇形名匹配。
     * 剩余列作为表单元素，末尾再追加 submit_reset。
     */
    private const FORM_EXCLUDED_ELEMENTS = [
        'id', 'create_time', 'create_date', 'update_time', 'update_date',
        'edit_delete', 'delete', 'view_delete',
    ];

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
     *
     * @param bool $withForm 是否在创建表格后自动创建同名配套表单（用表格列去掉仅表格元素、追加 submit_reset）；
     *                       仅对有显式 -e 列的普通表格生效，--no-form 或非交互无列时跳过。
     * @param bool $controllerFile 普通表格是否生成控制器实体文件 controller/{Class}.php；默认 false=虚拟控制器
     *                             （只初始化 acl/menu/lang，不落地文件）。仅作用于普通表格，树表始终复制动作类文件。
     */
    public function createTable(string $tableName, array $elements = [], $input = null, $output = null, bool $isTree = false, bool $withController = true, bool $isDrag = false, bool $withForm = true, bool $controllerFile = false): void
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
            if ($isDrag) {
                $this->io->write("<comment>⚠ 树状表格自带拖拽排序，-D/--drag 仅适用于普通表格，已忽略该参数</comment>");
                $isDrag = false;
            }
            // 树状表格中文名：优先复用控制器显示名（共享键 {模块} module {表名蛇形}），
            // 不存在则交互询问并去重写入 lang；用于替换树元素模板中的中文名占位符
            $tableCn = $this->resolveTreeTableCn($modulePath, $currentModule, $tableName, $className, $withController);
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
                    $elementRef = $this->processElement($modulePath, $element, $currentModule, $input, $output, $withController);
                    if ($elementRef !== null) {
                        $elementRefs[] = $elementRef;
                    }
                }
            }

            // 带了 -e：统一规范化首尾列——首列必须为主键 Id（已含则前移，不含则自动补充），
            // 最后列名不含 delete 时询问是否自动追加操作列 @EditDelete
            if (!empty($elements) && !empty($elementRefs)) {
                $idIndex = null;
                foreach ($elementRefs as $i => $r) {
                    if (strcasecmp(ltrim($r, '@~'), 'Id') === 0) {
                        $idIndex = $i;
                        break;
                    }
                }
                if ($idIndex === null) {
                    $idRef = $this->processElement($modulePath, 'id', $currentModule, $input, $output, $withController);
                    if ($idRef !== null) {
                        array_unshift($elementRefs, $idRef);
                        $this->io->write("<info>✓ 表格首列需为主键，已自动在首位添加 '{$idRef}'</info>");
                    }
                } elseif ($idIndex > 0) {
                    $idRef = $elementRefs[$idIndex];
                    array_splice($elementRefs, $idIndex, 1);
                    array_unshift($elementRefs, $idRef);
                    $this->io->write("<info>✓ 表格首列需为主键，已将 '{$idRef}' 移动到首位</info>");
                }

                $lastRef = $elementRefs[count($elementRefs) - 1];
                $lastName = ltrim($lastRef, '@~');
                if (stripos($lastName, 'delete') === false) {
                    $add = true;
                    if ($this->io->isInteractive()) {
                        $add = $this->io->confirm(
                            "<question>最后一列 '{$lastName}' 不含 delete，是否自动追加操作列 @EditDelete 作为最后一个元素？</question>",
                            true
                        );
                    } else {
                        $this->io->write('<comment>非交互模式：最后一列不含 delete，默认自动追加 @EditDelete（不想要请在 -e 末尾自带删除类列）</comment>');
                    }
                    if ($add) {
                        $elementRefs[] = '@EditDelete';
                        $this->io->write('<info>✓ 已在表格末尾追加 @EditDelete</info>');
                    }
                }
            }
            // 普通表格中文名：默认走统一优先级（设置 > 读取 lang > 交互提示），作为控制器显示名；
            // 仅创建表格模式（-N）下只读取已有 lang、不回写，避免改动 zh_cn.php
            $tableCnLangKey = "{$currentModule} module " . $this->toSnakeCase($tableName);
            if ($withController) {
                $tableCn = Lang::resolve(
                    $this->io,
                    $modulePath,
                    $tableCnLangKey,
                    null,
                    "请输入表格 '" . $this->toSnakeCase($tableName) . "' 的中文名称（留空使用 '{$className}'）：",
                    $className
                );
            } else {
                $readCn = Lang::getValue($modulePath, $tableCnLangKey);
                $tableCn = ($readCn !== null && $readCn !== '') ? $readCn : $className;
            }
        }

        // 创建表格类
        $this->createTableFile($tablePath, $className, $configName, $elementRefs, $currentModule, $isTree, $isDrag);

        // 自动创建控制器（树表与普通表都创建，但动作/元素不同）：
        //   - 树表：复制 tree 动作类（admin/add/move）+ 初始化集合，动作含 move、复制 tree 元素
        //   - 普通表：创建单文件控制器（admin/add/edit/delete）+ acl/menu/lang 初始化，
        //            不含 move、不复制 tree 元素、不初始化树集合
        // 仅创建表格模式（-N）：跳过控制器创建与 acl/menu/lang 初始化；树表同时跳过模型类与集合
        if (!$withController) {
            $this->io->write("<comment>⚠ 仅创建表格模式：已跳过控制器创建及 acl.php/menu.php/zh_cn.php 初始化"
                . ($isTree ? "（以及模型类、树集合初始化）" : "") . "</comment>");
        } else {
            if ($isTree) {
                $this->ensureTreeController($modulePath, $currentModule, $tableName, $tableCn);
                // 树状表格：在 model 目录创建树模型类（继承 xqkeji\mvc\model\Tree）
                $this->ensureTreeModel($modulePath, $currentModule, $tableName);
                // 初始化树集合（建索引 + 根节点）：作为代码生成器的一部分直接执行，
                // 不依赖任何 composer 事件。集合名 = 模块名_控制器名（$configName）
                $this->seedTreeCollection($configName);
            } else {
                $this->ensureNormalController($modulePath, $currentModule, $tableName, $tableCn, $controllerFile);
            }
        }

        // 自动创建同名配套表单（可用 --no-form 关闭）：把表格列去掉“仅表格”元素（主键/时间戳/操作列）后，
        // 追加 submit_reset 作为表单元素。表单与表格同名，共享控制器与中文名（lang 键一致），
        // 且元素中文名已在建表时写入 lang，故建表单过程不会再重复询问。仅对传入了 -e 列的表格生效。
        if ($withForm) {
            $formElements = $this->deriveFormElements($elements);
            if (!empty($formElements)) {
                $this->io->write('<info>▶ 自动创建配套表单 ' . $className . '（元素：' . implode(', ', $formElements) . '）</info>');
                $form = new Form($this->io, $this->composer);
                $form->createForm($tableName, $formElements, $input, $output);
            } elseif (!empty($elements)) {
                $this->io->write('<comment>⚠ 表格列均为仅表格元素（主键/时间戳/操作列），已跳过自动创建表单</comment>');
            }
        }

        // 自动切换为表格模式（建表单过程会切到 form 模式，这里统一切回 table，保持 xqkeji:table 的语境）
        $this->context->switchMode('table');
    }

    /**
     * 由表格列推导配套表单的元素名：剔除 FORM_EXCLUDED_ELEMENTS（按蛇形名匹配），
     * 再在末尾追加 submit_reset。若无任何可用列则返回空数组（调用方据此跳过建表单）。
     */
    private function deriveFormElements(array $tableElements): array
    {
        $formElements = [];
        foreach ($tableElements as $el) {
            if (in_array($this->toSnakeCase($el), self::FORM_EXCLUDED_ELEMENTS, true)) {
                continue;
            }
            $formElements[] = $el;
        }
        if (empty($formElements)) {
            return [];
        }
        $formElements[] = 'submit_reset';
        return $formElements;
    }

    /**
     * 向【已存在】的表格交互式追加一个列元素（xqkeji:table 的 -a/--add）。
     *
     * 流程与表单一致：读取当前模块的表格类文件 → 列出 protected $el 现有列 → 交互选择插入位置
     * （某一列之后 / 第一列之前）→ 交互确定新元素名（复用 xqkeji:element 的创建流程）→ 文本插入写回 $el。
     * 表格无 select_ / Tab 概念。
     */
    public function addElementToTable(string $tableName, $input = null, $output = null): void
    {
        if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $tableName)) {
            $this->io->write('<error>表格名称格式无效，只能包含字母、数字和下划线，且以字母开头</error>');
            return;
        }

        $currentModule = $this->context->getCurrentModule();
        if ($currentModule === null) {
            $this->io->write('<error>未设置当前模块，请先使用 composer xqkeji:use -- module_name</error>');
            return;
        }
        $modulePath = $this->context->getValidModulePath();
        if ($modulePath === null) {
            $this->io->write("<error>模块 '{$currentModule}' 无效或不存在</error>");
            return;
        }

        $className = $this->toCamelCase($tableName);
        $tableFile = $modulePath . DIRECTORY_SEPARATOR . 'table' . DIRECTORY_SEPARATOR . $className . '.php';
        if (!is_file($tableFile)) {
            $this->io->write("<error>表格不存在: {$tableFile}</error>");
            $this->io->write("<comment>  请先使用 composer xqkeji:table {$tableName} 创建表格</comment>");
            return;
        }

        $content = file_get_contents($tableFile);
        if ($content === false) {
            $this->io->write("<error>读取表格文件失败: {$tableFile}</error>");
            return;
        }
        $open = $this->elArrayOpen($content);
        if ($open === null) {
            $this->io->write("<error>无法在表格中定位 protected \$el 数组: {$tableFile}</error>");
            return;
        }
        $children = $this->elScanItems($content, $open);

        $targetIndex = 0;
        $defaultIndent = '        ';
        $positionDesc = '第一个元素之前';

        if (empty($children)) {
            $this->io->write('<comment>当前表格暂无列元素，新元素将作为第一列。</comment>');
        } else {
            if (!$this->io->isInteractive()) {
                $this->io->write('<error>交互模式不可用，无法选择插入位置（请在终端下运行）</error>');
                return;
            }

            $this->io->write("<info>表格 '{$className}' 当前列元素列表：</info>");
            foreach ($children as $idx => $item) {
                $text = $this->elItemText($content, $item);
                $ref = $this->elRefName($text);
                $this->io->write('  [' . ($idx + 1) . '] ' . ($ref !== null ? $ref : $text));
            }
            $this->io->write('');

            $max = count($children);
            $answer = trim((string)$this->io->ask(
                "<question>请选择在哪一列【后面】添加（编号 1-{$max}；0 或直接回车 = 插入到第一列前面）:</question> ",
                '0'
            ));
            if ($answer === '') {
                $answer = '0';
            }
            if (!ctype_digit($answer) || (int)$answer < 0 || (int)$answer > $max) {
                $this->io->write('<error>无效编号，已取消</error>');
                return;
            }
            $chosen = (int)$answer;
            if ($chosen > 0) {
                $targetIndex = $chosen;
                $ref = $this->elRefName($this->elItemText($content, $children[$chosen - 1]));
                $positionDesc = '元素 ' . ($ref !== null ? $ref : "第 {$chosen} 列") . ' 之后';
            }
        }

        $elName = trim((string)$this->io->ask(
            '<question>请输入要添加的元素名称（留空取消）:</question> ',
            ''
        ));
        if ($elName === '') {
            $this->io->write('<comment>已取消</comment>');
            return;
        }
        if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $elName)) {
            $this->io->write('<error>元素名称格式无效，只能包含字母、数字和下划线，且以字母开头</error>');
            return;
        }

        $ref = $this->resolveOrAddTableElement($modulePath, $elName, $currentModule);
        if ($ref === null) {
            return;
        }

        $newContent = $this->elInsertRef($content, $open, $targetIndex, $ref, $defaultIndent);
        if (file_put_contents($tableFile, $newContent) === false) {
            $this->io->write("<error>写入表格文件失败: {$tableFile}</error>");
            return;
        }
        $this->io->write("<info>✓ 已将元素 '{$ref}' 添加到表格 {$className}（{$positionDesc}）</info>");
        $this->io->write("  文件: {$tableFile}");
    }

    /**
     * 为【已存在】的表格类启用拖动排序：补写/改写 protected $isDrag = true;（幂等）。
     *
     * 与新建表格（-D）布局一致：属性插在 $el 属性（含其上方独占注释行）之前。
     * 返回 true 表示已处理完毕（含跳过与出错，调用方无需继续）；
     * 返回 false 表示表格类不存在，调用方可回落到正常建表流程。
     */
    public function enableDrag(string $tableName): bool
    {
        if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $tableName)) {
            $this->io->write('<error>表格名称格式无效，只能包含字母、数字和下划线，且以字母开头</error>');
            return true;
        }

        $currentModule = $this->context->getCurrentModule();
        if ($currentModule === null) {
            $this->io->write('<error>未设置当前模块，请先使用 composer xqkeji:use -- module_name</error>');
            return true;
        }
        $modulePath = $this->context->getValidModulePath();
        if ($modulePath === null) {
            $this->io->write("<error>模块 '{$currentModule}' 无效或不存在</error>");
            return true;
        }

        $className = $this->toCamelCase($tableName);
        $tableFile = $modulePath . DIRECTORY_SEPARATOR . 'table' . DIRECTORY_SEPARATOR . $className . '.php';
        if (!is_file($tableFile)) {
            return false;
        }

        $content = (string) file_get_contents($tableFile);

        if (preg_match('/class\s+\w+\s+extends\s+TreegridTable/', $content)) {
            $this->io->write("<comment>⊘ 表格 {$className} 为树状表格（TreegridTable），自带拖拽排序，无需 \$isDrag</comment>");
            return true;
        }

        $eol = str_contains($content, "\r\n") ? "\r\n" : "\n";
        $changed = false;

        if (preg_match('/(protected\s+\$isDrag\s*=\s*)(true|false)/', $content, $m)) {
            if ($m[2] === 'true') {
                $this->io->write("<comment>⊘ 表格已是可拖动排序（\$isDrag = true），跳过: {$tableFile}</comment>");
                return true;
            }
            $content = preg_replace('/(protected\s+\$isDrag\s*=\s*)false/', '${1}true', $content, 1);
            $changed = true;
            $action = "已将 \$isDrag 由 false 改为 true";
        } else {
            if (!preg_match('/^([ \t]*)protected\s+\$el\s*=/m', $content, $m, PREG_OFFSET_CAPTURE)) {
                $this->io->write("<error>无法在表格类中定位 \$el 属性，请手动添加 protected \$isDrag = true;: {$tableFile}</error>");
                return true;
            }
            $lineOffset = $m[0][1];
            $indent = $m[1][0];
            // 若 $el 行上方紧邻独占注释行（如 "// 表格元素列表"），插到注释行之前，与新建表格布局一致
            $before = substr($content, 0, $lineOffset);
            if (preg_match('/([ \t]*\/\/[^\r\n]*\r?\n)$/', $before, $cm)) {
                $lineOffset -= strlen($cm[0]);
            }
            $content = substr_replace(
                $content,
                $indent . 'protected $isDrag = true;' . $eol . $eol,
                $lineOffset,
                0
            );
            $changed = true;
            $action = '已加入 protected $isDrag = true;';
        }

        if ($changed && file_put_contents($tableFile, $content) === false) {
            $this->io->write("<error>写入表格文件失败: {$tableFile}</error>");
            return true;
        }
        $this->io->write("<info>✓ 表格 {$className} {$action}（可拖动排序）</info>");
        $this->io->write("  文件: {$tableFile}");
        return true;
    }

    /**
     * 交互式追加元素专用：查找或创建表格列元素，返回其在 $el 中的引用（@X / ~X）；创建失败返回 null。
     *
     * 新元素走 xqkeji:element 的创建流程（表格模式，交互可选类型），而非仅生成默认 ListItem。
     */
    private function resolveOrAddTableElement(string $modulePath, string $elementName, string $currentModule): ?string
    {
        $className = $this->toCamelCase($elementName);

        if ($this->findElementInModule('base', $className) !== null) {
            $this->io->write("<info>✓ 复用 base 模块元素: $className</info>");
            return '@' . $className;
        }
        if ($this->findElementInModule($currentModule, $className) !== null) {
            $this->io->write("<info>✓ 复用当前模块元素: $className</info>");
            return '~' . $className;
        }

        $elementPath = $modulePath . DIRECTORY_SEPARATOR . 'table' . DIRECTORY_SEPARATOR . 'element';
        if (!is_dir($elementPath)) {
            mkdir($elementPath, 0755, true);
        }

        $this->context->switchMode('table');
        $interactive = $this->io->isInteractive();
        $element = new Element($this->io, $this->composer);
        $element->createElement(
            $elementName,
            $interactive ? '' : null,
            $interactive ? '' : null,
            null,
            null
        );

        if (is_file($elementPath . DIRECTORY_SEPARATOR . $className . '.php')) {
            return '~' . $className;
        }
        $this->io->write('<error>元素创建失败，已取消插入</error>');
        return null;
    }

    /**
     * 处理表格元素（查找或创建）
     */
    private function processElement(string $modulePath, string $elementName, string $currentModule, $input = null, $output = null, bool $writeBack = true): ?string
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
        // 仅创建表格模式（-N）：只读取已有 lang、不回写（不改动 zh_cn.php），读不到回退类名
        $elementLangKey = "{$currentModule} {$configName} name";
        if ($writeBack) {
            $elementText = Lang::resolve(
                $this->io,
                $modulePath,
                $elementLangKey,
                null,
                "请输入元素 '{$configName}' 的中文名称（留空使用 '{$className}'）：",
                $className
            );
        } else {
            $readText = Lang::getValue($modulePath, $elementLangKey);
            $elementText = ($readText !== null && $readText !== '') ? $readText : $className;
        }
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
    private function createTableFile(string $tablePath, string $className, string $configName, array $elementRefs, string $moduleName, bool $isTree = false, bool $isDrag = false): void
    {
        $filePath = $tablePath . DIRECTORY_SEPARATOR . $className . '.php';

        if (is_file($filePath)) {
            $this->io->write("<error>表格已存在: $filePath</error>");
            return;
        }

        $content = $this->generateTableContent($moduleName, $className, $configName, $elementRefs, $isTree, $isDrag);
        file_put_contents($filePath, $content);

        $this->io->write("<info>✓ 表格已创建: $filePath</info>");
    }

    /**
     * 生成表格类内容
     */
    private function generateTableContent(string $moduleName, string $className, string $configName, array $elementRefs, bool $isTree = false, bool $isDrag = false): string
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

        // 可拖动排序（仅普通表格）：在表格类中额外生成 protected $isDrag = true;
        $dragProp = $isDrag ? "    protected \$isDrag = true;\n\n" : '';

        return "<?php\nnamespace {$namespace};\n\nuse {$useTable};\n\nclass {$className} extends {$baseClass}\n{\n    protected \$name = '{$configName}';\n    protected \$foot = {$foot};\n\n{$dragProp}    // 表格元素列表\n    protected \$el = [{$elementsStr}];\n}\n";
    }

    /**
     * 树状表格：解析中文名（统一优先级：设置 > 读取 lang > 交互提示）
     *
     * 优先复用控制器显示名（共享键 {模块} module {表名蛇形}），与表单/表格共享同一中文名；
     * 读不到时交互提示用户设置（非交互回退 $className），并去重写入 lang。
     * 返回的中文名用于替换树元素模板中的 {中文名称}/{中文名} 占位符。
     */
    private function resolveTreeTableCn(string $modulePath, string $currentModule, string $tableName, string $className, bool $writeBack = true): string
    {
        $langKey = "{$currentModule} module " . $this->toSnakeCase($tableName);

        // 仅创建表格模式（-N）：只读取已有 lang、不回写（不改动 zh_cn.php），读不到回退类名
        if (!$writeBack) {
            $read = Lang::getValue($modulePath, $langKey);
            return ($read !== null && $read !== '') ? $read : $className;
        }

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
        // 树表动作集在普通表基础上追加 move（拖拽/移动）与 subnode（子节点懒加载）
        $configName = $this->toSnakeCase($tableName);
        $controller = new Controller($this->io, $this->composer);
        $controller->initControllerConfig(
            $modulePath,
            $configName,
            ['add', 'edit', 'admin', 'delete', 'change', 'move', 'subnode'],
            $tableCn
        );
    }

    /**
     * 树状表格：在 model 目录创建树模型类（继承 xqkeji\mvc\model\Tree）
     *
     * 目标文件：{模块路径}/model/{大驼峰表名}.php
     * 命名空间：xqkeji\app\{模块}\model（{MODULE_NAME} -> 当前模块，{MODEL_NAME} -> 大驼峰表名）
     * 文件已存在则幂等跳过。
     */
    private function ensureTreeModel(string $modulePath, string $currentModule, string $tableName): void
    {
        $className = $this->toCamelCase($tableName);
        $modelDir = $modulePath . DIRECTORY_SEPARATOR . 'model';
        if (!is_dir($modelDir)) {
            mkdir($modelDir, 0755, true);
        }
        $modelFile = $modelDir . DIRECTORY_SEPARATOR . $className . '.php';

        if (is_file($modelFile)) {
            $this->io->write("<comment>⚠ 树状模型类已存在，跳过: {$modelFile}</comment>");
            return;
        }

        $namespace = "xqkeji\\app\\{$currentModule}\\model";
        $content = "<?php\nnamespace {$namespace};\n\nuse xqkeji\\mvc\\model\\Tree;\n\nclass {$className} extends Tree\n{\n}\n";
        file_put_contents($modelFile, $content);
        $this->io->write("<info>✓ 已创建树状模型类: {$modelFile}</info>");
    }

    /**
     * 普通（非树状）表格：初始化控制器配置（acl/menu/lang），并按需生成控制器实体文件
     *
     * 默认使用【虚拟控制器】：不生成 controller/{Class}.php，只要 acl.php 有该控制器/动作定义，
     * 框架即可按约定解析动作，控制器即“存在”。仅当 $controllerFile=true（命令行 -f/--controller-file）
     * 时才落地实体文件（继承 xqkeji\mvc\Controller，动作由基类按约定解析）。
     * 无论是否落地文件，都会补齐 acl/menu/lang 初始化，与 xqkeji:controller 保持一致
     * （动作集 add/edit/admin/delete/change；不含树表专属的 move）。
     *
     * @param string $tableCn        表格中文名（作为控制器显示名，来自 Lang::resolve）
     * @param bool   $controllerFile 是否生成控制器实体文件（默认 false=虚拟控制器）
     */
    private function ensureNormalController(string $modulePath, string $currentModule, string $tableName, string $tableCn, bool $controllerFile = false): void
    {
        $className = $this->toCamelCase($tableName);
        $ctrlPath = $modulePath . DIRECTORY_SEPARATOR . 'controller' . DIRECTORY_SEPARATOR . $className . '.php';

        if ($controllerFile) {
            if (!is_file($ctrlPath)) {
                $namespace = "xqkeji\\app\\{$currentModule}\\controller";
                $content = "<?php\nnamespace {$namespace};\n\nuse xqkeji\\mvc\\Controller;\n\nclass {$className} extends Controller\n{\n\n}\n";
                if (!is_dir(dirname($ctrlPath))) {
                    mkdir(dirname($ctrlPath), 0755, true);
                }
                file_put_contents($ctrlPath, $content);
                $this->io->write("<info>✓ 已创建普通表格控制器实体文件: {$ctrlPath}</info>");
            } else {
                $this->io->write("<comment>⚠ 普通表格控制器实体文件已存在，跳过创建（仅补齐配置初始化）: {$ctrlPath}</comment>");
            }
        } else {
            // 虚拟控制器：不落地上文件，acl.php 有定义即生效
            $this->io->write('<comment>⊘ 未创建控制器实体文件（使用虚拟控制器，acl.php 已定义即生效），如需实体文件请加 -f/--controller-file</comment>');
        }

        // 补齐控制器配置初始化（acl / menu / lang），与普通 xqkeji:controller 创建保持一致
        // 动作集含 change；顺序 add/edit/admin/delete/change（不含树表专属的 move）；普通表中文名作为控制器显示名
        $controller = new Controller($this->io, $this->composer);
        $controller->initControllerConfig(
            $modulePath,
            $this->toSnakeCase($tableName),
            ['add', 'edit', 'admin', 'delete', 'change'],
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
