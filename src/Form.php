<?php
namespace xqkeji\composer;

use Composer\Composer;
use Composer\Factory;
use Composer\IO\IOInterface;

class Form
{
    use PathTrait;
    use ElInsertTrait;

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
     * 创建表单（公开方法）
     */
    public function createForm(string $formName, array $elements = [], $input = null, $output = null, array $tabGroups = [], array $globalElements = []): void
    {
        // 验证表单名称（支持大小写字母、数字和下划线）
        if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $formName)) {
            $this->io->write('<error>表单名称格式无效，只能包含字母、数字和下划线，且以字母开头</error>');
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

        // 转换为大驼峰类名
        $className = $this->toCamelCase($formName);
        // 转换为小写下划线格式，用于配置文件
        $configName = $this->toSnakeCase($formName);

        // 创建 form 目录
        $formPath = $modulePath . DIRECTORY_SEPARATOR . 'form';
        if (!is_dir($formPath)) {
            mkdir($formPath, 0755, true);
        }

        // 判断是否为Tab表单
        $isTabForm = !empty($tabGroups);

        if ($isTabForm) {
            // Tab表单：处理每个Tab组的元素
            $processedTabGroups = [];
            foreach ($tabGroups as $tabGroup) {
                $tabElements = [];
                foreach ($tabGroup['elements'] as $element) {
                    $elementRef = $this->processElement($modulePath, $element, $currentModule, $input, $output);
                    if ($elementRef !== null) {
                        $tabElements[] = $elementRef;
                    }
                }
                $processedTabGroups[] = [
                    'name' => $this->toSnakeCase($tabGroup['name']),
                    'text' => $tabGroup['text'],
                    'elements' => $tabElements,
                ];
            }

            // 处理全局元素
            $globalRefs = [];
            foreach ($globalElements as $element) {
                $elementRef = $this->processElement($modulePath, $element, $currentModule, $input, $output);
                if ($elementRef !== null) {
                    $globalRefs[] = $elementRef;
                }
            }

            // 创建Tab表单类
            $this->createTabFormFile($formPath, $className, $configName, $processedTabGroups, $globalRefs, $currentModule);
        } else {
            // 普通表单：处理元素
            $elementRefs = [];
            if (!empty($elements)) {
                $elementPath = $formPath . DIRECTORY_SEPARATOR . 'element';
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

            // 创建表单类
            $this->createFormFile($formPath, $className, $configName, $elementRefs, $currentModule);
        }

        // 表单显示名统一解析中文名（设置 > 读取 lang > 交互提示；表单名=控制器名时共享同一键，自然复用已有中文）
        Lang::resolve(
            $this->io,
            $modulePath,
            "{$currentModule} module {$configName}",
            null,
            "请输入表单 '{$configName}' 的中文名称（留空使用 '{$className}'）：",
            $className
        );

        // 自动切换为表单模式
        $this->context->switchMode('form');
    }

    /**
     * 向【已存在】的表单交互式追加一个元素（xqkeji:form 的 -a/--add）。
     *
     * 流程：读取当前模块的表单类文件 → 列出 protected $el 现有元素（Tab 表单可进入某个 Tab 内部选择）
     * → 交互选择插入位置（某个元素之后 / 第一个元素之前）→ 交互确定新元素名（select_ 走 SelectModel 子类，
     * 其余复用 xqkeji:element 的类型/项目列表创建流程）→ 以文本插入方式把引用写回 $el。
     */
    public function addElementToForm(string $formName, $input = null, $output = null): void
    {
        if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $formName)) {
            $this->io->write('<error>表单名称格式无效，只能包含字母、数字和下划线，且以字母开头</error>');
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

        $className = $this->toCamelCase($formName);
        $formFile = $modulePath . DIRECTORY_SEPARATOR . 'form' . DIRECTORY_SEPARATOR . $className . '.php';
        if (!is_file($formFile)) {
            $this->io->write("<error>表单不存在: {$formFile}</error>");
            $this->io->write("<comment>  请先使用 composer xqkeji:form {$formName} 创建表单</comment>");
            return;
        }

        $content = file_get_contents($formFile);
        if ($content === false) {
            $this->io->write("<error>读取表单文件失败: {$formFile}</error>");
            return;
        }
        $open = $this->elArrayOpen($content);
        if ($open === null) {
            $this->io->write("<error>无法在表单中定位 protected \$el 数组: {$formFile}</error>");
            return;
        }
        $children = $this->elScanItems($content, $open);

        // 解析插入目标：targetOpen=目标数组的 '[' 下标，targetIndex=插入位置（0=最前），defaultIndent=空数组展开缩进
        $targetOpen = $open;
        $targetIndex = 0;
        $defaultIndent = '        ';
        $positionDesc = '第一个元素之前';

        if (empty($children)) {
            $this->io->write('<comment>当前表单暂无元素，新元素将作为第一个元素。</comment>');
        } else {
            if (!$this->io->isInteractive()) {
                $this->io->write('<error>交互模式不可用，无法选择插入位置（请在终端下运行）</error>');
                return;
            }

            $this->io->write("<info>表单 '{$className}' 当前元素列表：</info>");
            $topInfo = [];
            foreach ($children as $idx => $item) {
                $text = $this->elItemText($content, $item);
                $innerOpen = $this->elTabInnerOpen($content, $item);
                if ($innerOpen !== null) {
                    $cnt = count($this->elScanItems($content, $innerOpen));
                    $label = $this->elTabLabel($text, $cnt);
                    $topInfo[$idx] = ['tab' => true, 'innerOpen' => $innerOpen, 'label' => $label];
                } else {
                    $ref = $this->elRefName($text);
                    $label = ($ref !== null) ? $ref : $text;
                    $topInfo[$idx] = ['tab' => false, 'label' => $label];
                }
                $this->io->write('  [' . ($idx + 1) . "] {$label}");
            }
            $this->io->write('');

            $max = count($children);
            $answer = trim((string)$this->io->ask(
                "<question>请选择在哪个元素【后面】添加（编号 1-{$max}；0 或直接回车 = 插入到第一个元素前面）:</question> ",
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

            if ($chosen === 0) {
                // 已在默认值中（最前面）
            } elseif (!$topInfo[$chosen - 1]['tab']) {
                $targetIndex = $chosen;
                $positionDesc = "元素 {$topInfo[$chosen - 1]['label']} 之后";
            } else {
                $tab = $topInfo[$chosen - 1];
                $innerChildren = $this->elScanItems($content, $tab['innerOpen']);
                $targetOpen = $tab['innerOpen'];
                $defaultIndent = '                ';

                if (empty($innerChildren)) {
                    $targetIndex = 0;
                    $positionDesc = "Tab『{$tab['label']}』的第一个元素位置";
                } else {
                    $this->io->write('');
                    $this->io->write("<info>Tab『{$tab['label']}』内的元素：</info>");
                    foreach ($innerChildren as $j => $it) {
                        $txt = $this->elItemText($content, $it);
                        $rn = $this->elRefName($txt);
                        $this->io->write('  [' . ($j + 1) . '] ' . ($rn !== null ? $rn : $txt));
                    }
                    $imax = count($innerChildren);
                    $sub = trim((string)$this->io->ask(
                        "<question>在该 Tab 内哪个元素后插入（编号 1-{$imax}；0 或直接回车 = Tab 内第一个元素前面）:</question> ",
                        '0'
                    ));
                    if ($sub === '') {
                        $sub = '0';
                    }
                    if (!ctype_digit($sub) || (int)$sub < 0 || (int)$sub > $imax) {
                        $this->io->write('<error>无效编号，已取消</error>');
                        return;
                    }
                    $targetIndex = (int)$sub;
                    $positionDesc = $targetIndex === 0
                        ? "Tab『{$tab['label']}』第一个元素之前"
                        : "Tab『{$tab['label']}』内第 {$targetIndex} 个元素之后";
                }
            }
        }

        // 新元素名称
        $elName = trim((string)$this->io->ask(
            '<question>请输入要添加的元素名称（如 Status 或 select_dept，留空取消）:</question> ',
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

        $ref = $this->resolveOrAddFormElement($modulePath, $elName, $currentModule);
        if ($ref === null) {
            return;
        }

        $newContent = $this->elInsertRef($content, $targetOpen, $targetIndex, $ref, $defaultIndent);
        if (file_put_contents($formFile, $newContent) === false) {
            $this->io->write("<error>写入表单文件失败: {$formFile}</error>");
            return;
        }
        $this->io->write("<info>✓ 已将元素 '{$ref}' 添加到表单 {$className}（{$positionDesc}）</info>");
        $this->io->write("  文件: {$formFile}");
    }

    /**
     * 交互式追加元素专用：查找或创建表单元素，返回其在 $el 中的引用（@X / ~X）；创建失败返回 null。
     *
     * 与 createForm 的 processElement 不同：新元素走 xqkeji:element 的完整创建流程（可选类型 / 项目列表），
     * 而非仅生成默认 Text 元素；select_ 前缀沿用 SelectModel 空子类的约定。
     */
    private function resolveOrAddFormElement(string $modulePath, string $elementName, string $currentModule): ?string
    {
        $className = $this->toCamelCase($elementName);
        $configName = $this->toSnakeCase($elementName);

        if ($this->findElementInModule('base', $className) !== null) {
            $this->io->write("<info>✓ 复用 base 模块元素: $className</info>");
            return '@' . $className;
        }
        if ($this->findElementInModule($currentModule, $className) !== null) {
            $this->io->write("<info>✓ 复用当前模块元素: $className</info>");
            return '~' . $className;
        }

        $elementPath = $modulePath . DIRECTORY_SEPARATOR . 'form' . DIRECTORY_SEPARATOR . 'element';
        if (!is_dir($elementPath)) {
            mkdir($elementPath, 0755, true);
        }

        // select_ 前缀：生成 SelectModel 空子类，不询问类型
        if (strpos($configName, 'select_') === 0) {
            $this->createElementFile($elementPath, $className, $configName, '', true);
            $this->io->write("<info>✓ 已创建表单元素（SelectModel 子类）: $className</info>");
            return '~' . $className;
        }

        // 其余：复用 xqkeji:element 创建流程（交互模式弹类型选择、Select/Check/Radio 弹项目列表）
        $this->context->switchMode('form');
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
     * 处理表单元素（查找或创建）
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

        // 3. 创建新元素
        $elementPath = $modulePath . DIRECTORY_SEPARATOR . 'form' . DIRECTORY_SEPARATOR . 'element';
        if (!is_dir($elementPath)) {
            mkdir($elementPath, 0755, true);
        }

        // select_ 开头的元素（蛇形 select_dept 或驼峰 SelectDept 均可，先统一转蛇形再判前缀）：
        // 生成 SelectModel 的空子类（仅继承、类体为空，不写属性、不询问中文名），如 → 类 SelectDept
        if (strpos($configName, 'select_') === 0) {
            $this->createElementFile($elementPath, $className, $configName, '', true);
            $this->io->write("<info>✓ 已创建表单元素（SelectModel 子类）: $className</info>");
            return '~' . $className;
        }

        // 普通元素 - 解析中文名称（统一优先级：设置 > 读取 lang > 交互提示）
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
        $this->io->write("<info>✓ 已创建表单元素: $className</info>");
        return '~' . $className;
    }

    /**
     * 在指定模块中查找表单元素
     */
    private function findElementInModule(string $moduleName, string $className): ?string
    {
        $rootPath = $this->getRootPath();

        // 1. 检查 app 目录下的模块
        $localModulePath = $rootPath . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . $moduleName;
        if (is_dir($localModulePath)) {
            $elementFile = $localModulePath . DIRECTORY_SEPARATOR . 'form' . DIRECTORY_SEPARATOR . 'element' . DIRECTORY_SEPARATOR . $className . '.php';
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
            // 检查 src/form/element/
            $srcPath = $vendorPackagePath . DIRECTORY_SEPARATOR . 'src';
            if (is_dir($srcPath)) {
                $elementFile = $srcPath . DIRECTORY_SEPARATOR . 'form' . DIRECTORY_SEPARATOR . 'element' . DIRECTORY_SEPARATOR . $className . '.php';
                if (is_file($elementFile)) {
                    return $elementFile;
                }
            }

            // 检查包根目录的 form/element/
            $elementFile = $vendorPackagePath . DIRECTORY_SEPARATOR . 'form' . DIRECTORY_SEPARATOR . 'element' . DIRECTORY_SEPARATOR . $className . '.php';
            if (is_file($elementFile)) {
                return $elementFile;
            }
        }

        return null;
    }

    /**
     * 创建表单元素文件
     */
    private function createElementFile(string $elementPath, string $className, string $configName, string $elementText, bool $isSelect = false): void
    {
        $filePath = $elementPath . DIRECTORY_SEPARATOR . $className . '.php';

        if (is_file($filePath)) {
            $this->io->write("<comment>⚠ 表单元素已存在: $filePath</comment>");
            return;
        }

        $currentModule = $this->context->getCurrentModule();
        $content = $this->generateElementContent($currentModule, $className, $configName, $elementText, $isSelect);
        file_put_contents($filePath, $content);
    }

    /**
     * 生成表单元素类内容
     *
     * 默认生成继承 Text 的完整元素类；$isSelect 为 true 时生成继承 SelectModel 的空类
     * （select_ 开头元素的约定：类体为空，直接继承，由 SelectModel 提供行为）。
     */
    private function generateElementContent(string $moduleName, string $className, string $configName, string $elementText, bool $isSelect = false): string
    {
        $namespace = "xqkeji\\app\\{$moduleName}\\form\\element";

        if ($isSelect) {
            $useSelectModel = 'xqkeji' . '\\' . 'form' . '\\' . 'element' . '\\' . 'SelectModel';
            return "<?php\nnamespace {$namespace};\n\nuse {$useSelectModel};\n\nclass {$className} extends SelectModel\n{\n}\n";
        }

        $useText = 'xqkeji' . '\\' . 'form' . '\\' . 'element' . '\\' . 'Text';

        return "<?php\nnamespace {$namespace};\n\nuse {$useText};\n\nclass {$className} extends Text\n{\n    protected \$name = '{$configName}';\n    protected \$text = '{$elementText}';\n    protected \$attrs = [\n        'required' => 'true',\n        'class' => 'form-control',\n    ];\n    protected \$filters = ['string'];\n    protected \$vt = [['required']];\n    protected \$template = '@row';\n}\n";
    }

    /**
     * 创建表单文件
     */
    private function createFormFile(string $formPath, string $className, string $configName, array $elementRefs, string $moduleName): void
    {
        $filePath = $formPath . DIRECTORY_SEPARATOR . $className . '.php';

        if (is_file($filePath)) {
            $this->io->write("<error>表单已存在: $filePath</error>");
            return;
        }

        $content = $this->generateFormContent($moduleName, $className, $configName, $elementRefs);
        file_put_contents($filePath, $content);

        $this->io->write("<info>✓ 表单已创建: $filePath</info>");
    }

    /**
     * 创建Tab表单文件
     */
    private function createTabFormFile(string $formPath, string $className, string $configName, array $tabGroups, array $globalRefs, string $moduleName): void
    {
        $filePath = $formPath . DIRECTORY_SEPARATOR . $className . '.php';

        if (is_file($filePath)) {
            $this->io->write("<error>表单已存在: $filePath</error>");
            return;
        }

        $content = $this->generateTabFormContent($moduleName, $className, $configName, $tabGroups, $globalRefs);
        file_put_contents($filePath, $content);

        $this->io->write("<info>✓ Tab表单已创建: $filePath</info>");
    }

    /**
     * 生成表单类内容
     */
    private function generateFormContent(string $moduleName, string $className, string $configName, array $elementRefs): string
    {
        $namespace = "xqkeji\\app\\{$moduleName}\\form";

        // 构建元素列表字符串
        $elementsStr = '';
        if (!empty($elementRefs)) {
            $elementsStr = "\n";
            foreach ($elementRefs as $ref) {
                $elementsStr .= "        '{$ref}',\n";
            }
            $elementsStr .= "    ";
        }

        $useForm = 'xqkeji' . '\\' . 'form' . '\\' . 'Form';

        return "<?php\nnamespace {$namespace};\n\nuse {$useForm};\n\nclass {$className} extends Form\n{\n    protected \$name = '{$configName}';\n\n    // 表单元素列表\n    protected \$el = [{$elementsStr}];\n}\n";
    }

    /**
     * 生成Tab表单类内容
     */
    private function generateTabFormContent(string $moduleName, string $className, string $configName, array $tabGroups, array $globalRefs): string
    {
        $namespace = "xqkeji\\app\\{$moduleName}\\form";

        // 构建Tab结构
        $tabLines = [];
        foreach ($tabGroups as $tab) {
            $tabName = $tab['name'];
            $tabText = $tab['text'];
            $tabElements = $tab['elements'];

            $elStr = '';
            foreach ($tabElements as $ref) {
                $elStr .= "\n                '{$ref}',";
            }
            if (!empty($tabElements)) {
                $elStr .= "\n            ";
            }

            $tabLines[] = "        [\n            '\$Tab',\n            'text' => '{$tabText}',\n            'name' => '{$tabName}',\n            'el' => [{$elStr}],\n        ],";
        }

        // 构建全局元素
        $globalLines = [];
        foreach ($globalRefs as $ref) {
            $globalLines[] = "        '{$ref}',";
        }

        // 合并所有行
        $allLines = array_merge($tabLines, $globalLines);
        $elContent = "\n" . implode("\n", $allLines) . "\n    ";

        $useTabForm = 'xqkeji' . '\\' . 'form' . '\\' . 'TabForm';

        return "<?php\nnamespace {$namespace};\n\nuse {$useTabForm};\n\nclass {$className} extends TabForm\n{\n    protected \$name = '{$configName}';\n    protected \$el = [{$elContent}];\n}\n";
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
