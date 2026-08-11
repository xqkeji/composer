<?php
namespace xqkeji\composer;

use Composer\Composer;
use Composer\Factory;
use Composer\IO\IOInterface;

class Element
{
    use PathTrait;

    private IOInterface $io;
    private Composer $composer;
    private Context $context;

    // 表单元素类型列表
    private const FORM_TYPES = [
        'Auth', 'Button', 'Captcha', 'Check', 'Csrf', 'Date', 'DateTime',
        'Div', 'Email', 'Emailcode', 'File', 'Fileinput', 'Hidden', 'Html',
        'Image', 'Label', 'Number', 'Pagesize', 'Password', 'Radio', 'Reset',
        'Select', 'SelectPicker', 'Status', 'Submit', 'Text', 'TextArea',
        'Tinymce', 'Vary', 'ViewData',
    ];

    // 表格元素类型列表
    private const TABLE_TYPES = [
        'ListItem', 'TableDiv', 'TableHtml', 'Tbody', 'Td', 'Tfoot', 'Th', 'Thead', 'Tr',
    ];

    public function __construct(IOInterface $io, Composer $composer)
    {
        $this->io = $io;
        $this->composer = $composer;
        $this->context = new Context($io, $composer);
    }

    /**
     * 创建元素（公开方法）
     * @param string $elementName 元素名称
     * @param string|null $specifiedType 指定类型（null=默认类型，''=交互选择，string=指定类型）
     * @param string|null $items 项目列表（null=无，''=交互输入，string=值1|文本1,值2|文本2）
     * @param string|null $defaultValue 默认值（null=无，''=交互输入，string=默认值）
     * @param string|null $modelName 模型名（null=无，''=交互输入，string=模型名）
     */
    public function createElement(string $elementName, ?string $specifiedType = null, ?string $items = null, ?string $defaultValue = null, ?string $modelName = null): void
    {
        // 验证元素名称
        if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $elementName)) {
            $this->io->write('<error>元素名称格式无效，只能包含字母、数字和下划线，且以字母开头</error>');
            return;
        }

        // 获取当前模块
        $currentModule = $this->context->getCurrentModule();
        if ($currentModule === null) {
            $this->io->write('<error>未设置当前模块，请先使用 composer xqkeji:use -- module_name</error>');
            return;
        }

        // 获取当前模式
        $currentMode = $this->context->getCurrentMode();
        if ($currentMode === null) {
            $this->io->write('<error>未设置当前模式，请先使用 composer xqkeji:use -f（表单）或 -t（表格）</error>');
            return;
        }

        // 获取有效的模块路径
        $modulePath = $this->context->getValidModulePath();
        if ($modulePath === null) {
            $this->io->write("<error>模块 '{$currentModule}' 无效或不存在</error>");
            return;
        }

        $className = $this->toCamelCase($elementName);
        $configName = $this->toSnakeCase($elementName);

        // 确定目录和类型列表
        if ($currentMode === 'form') {
            $elementPath = $modulePath . DIRECTORY_SEPARATOR . 'form' . DIRECTORY_SEPARATOR . 'element';
            $types = self::FORM_TYPES;
            $defaultType = 'Text';
            $modeLabel = '表单';
        } else {
            $elementPath = $modulePath . DIRECTORY_SEPARATOR . 'table' . DIRECTORY_SEPARATOR . 'element';
            $types = self::TABLE_TYPES;
            $defaultType = 'ListItem';
            $modeLabel = '表格';
        }

        // 创建 element 目录
        if (!is_dir($elementPath)) {
            mkdir($elementPath, 0755, true);
        }

        // 检查元素是否已存在
        $filePath = $elementPath . DIRECTORY_SEPARATOR . $className . '.php';
        if (is_file($filePath)) {
            $this->io->write("<error>{$modeLabel}元素已存在: $filePath</error>");
            return;
        }

        // 确定元素类型
        $elementType = $this->resolveType($specifiedType, $types, $defaultType);
        if ($elementType === null) {
            return;
        }

        // 交互式询问中文名称
        $elementText = $this->askElementText($configName, $className);

        // 解析模型名（Select/Check/Radio/SelectPicker）
        $modelInfo = $this->resolveModel($modelName, $elementType);

        // 如果指定了模型，则不使用 items 属性（由 beforeRender 方法动态加载）
        if ($modelInfo !== null) {
            $parsedItems = null;
        } else {
            // 解析项目列表（Select/Check/Radio）
            $parsedItems = $this->resolveItems($items, $elementType);
        }

        // 解析默认值
        $parsedDefault = $this->resolveDefaultValue($defaultValue);

        // 生成并写入
        $useBaseClass = 'xqkeji' . '\\' . 'form' . '\\' . 'element' . '\\' . $elementType;
        $namespace = $this->getElementNamespace($currentModule, $currentMode);
        $content = $this->generateElementContent($namespace, $className, $configName, $elementText, $elementType, $useBaseClass, $currentMode, $parsedItems, $parsedDefault, $modelInfo);
        file_put_contents($filePath, $content);

        $this->io->write("<info>✓ {$modeLabel}元素已创建: $filePath</info>");
        $this->io->write("  类型: {$elementType}");
    }

    /**
     * 修改元素（公开方法）
     * @param string $elementName 元素名称
     * @param string|null $specifiedType 指定类型（null=默认类型，''=交互选择，string=指定类型）
     * @param string|null $items 项目列表
     * @param string|null $defaultValue 默认值
     * @param string|null $modelName 模型名
     */
    public function editElement(string $elementName, ?string $specifiedType = null, ?string $items = null, ?string $defaultValue = null, ?string $modelName = null): void
    {
        if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $elementName)) {
            $this->io->write('<error>元素名称格式无效，只能包含字母、数字和下划线，且以字母开头</error>');
            return;
        }

        $currentModule = $this->context->getCurrentModule();
        if ($currentModule === null) {
            $this->io->write('<error>未设置当前模块，请先使用 composer xqkeji:use -- module_name</error>');
            return;
        }

        $currentMode = $this->context->getCurrentMode();
        if ($currentMode === null) {
            $this->io->write('<error>未设置当前模式，请先使用 composer xqkeji:use -f（表单）或 -t（表格）</error>');
            return;
        }

        $modulePath = $this->context->getValidModulePath();
        if ($modulePath === null) {
            $this->io->write("<error>模块 '{$currentModule}' 无效或不存在</error>");
            return;
        }

        $className = $this->toCamelCase($elementName);
        $configName = $this->toSnakeCase($elementName);

        if ($currentMode === 'form') {
            $elementPath = $modulePath . DIRECTORY_SEPARATOR . 'form' . DIRECTORY_SEPARATOR . 'element';
            $types = self::FORM_TYPES;
            $defaultType = 'Text';
            $modeLabel = '表单';
        } else {
            $elementPath = $modulePath . DIRECTORY_SEPARATOR . 'table' . DIRECTORY_SEPARATOR . 'element';
            $types = self::TABLE_TYPES;
            $defaultType = 'ListItem';
            $modeLabel = '表格';
        }

        $filePath = $elementPath . DIRECTORY_SEPARATOR . $className . '.php';
        if (!is_file($filePath)) {
            $this->io->write("<error>{$modeLabel}元素不存在: $filePath</error>");
            return;
        }

        // 确定元素类型
        $elementType = $this->resolveType($specifiedType, $types, $defaultType);
        if ($elementType === null) {
            return;
        }

        // 交互式询问中文名称
        $elementText = $this->askElementText($configName, $className);

        // 解析模型名
        $modelInfo = $this->resolveModel($modelName, $elementType);

        // 如果指定了模型，则不使用 items 属性
        if ($modelInfo !== null) {
            $parsedItems = null;
        } else {
            $parsedItems = $this->resolveItems($items, $elementType);
        }

        // 解析默认值
        $parsedDefault = $this->resolveDefaultValue($defaultValue);

        $useBaseClass = 'xqkeji' . '\\' . 'form' . '\\' . 'element' . '\\' . $elementType;
        $namespace = $this->getElementNamespace($currentModule, $currentMode);
        $content = $this->generateElementContent($namespace, $className, $configName, $elementText, $elementType, $useBaseClass, $currentMode, $parsedItems, $parsedDefault, $modelInfo);
        file_put_contents($filePath, $content);

        $this->io->write("<info>✓ {$modeLabel}元素已修改: $filePath</info>");
        $this->io->write("  类型: {$elementType}");
    }

    /**
     * 删除元素（公开方法）
     */
    public function removeElement(string $elementName): void
    {
        if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $elementName)) {
            $this->io->write('<error>元素名称格式无效，只能包含字母、数字和下划线，且以字母开头</error>');
            return;
        }

        $currentModule = $this->context->getCurrentModule();
        if ($currentModule === null) {
            $this->io->write('<error>未设置当前模块，请先使用 composer xqkeji:use -- module_name</error>');
            return;
        }

        $currentMode = $this->context->getCurrentMode();
        if ($currentMode === null) {
            $this->io->write('<error>未设置当前模式，请先使用 composer xqkeji:use -f（表单）或 -t（表格）</error>');
            return;
        }

        $modulePath = $this->context->getValidModulePath();
        if ($modulePath === null) {
            $this->io->write("<error>模块 '{$currentModule}' 无效或不存在</error>");
            return;
        }

        $className = $this->toCamelCase($elementName);

        if ($currentMode === 'form') {
            $elementPath = $modulePath . DIRECTORY_SEPARATOR . 'form' . DIRECTORY_SEPARATOR . 'element';
            $modeLabel = '表单';
        } else {
            $elementPath = $modulePath . DIRECTORY_SEPARATOR . 'table' . DIRECTORY_SEPARATOR . 'element';
            $modeLabel = '表格';
        }

        $filePath = $elementPath . DIRECTORY_SEPARATOR . $className . '.php';
        if (!is_file($filePath)) {
            $this->io->write("<error>{$modeLabel}元素不存在: $filePath</error>");
            return;
        }

        // 确认删除
        if ($this->io->isInteractive()) {
            $confirm = $this->io->askConfirmation(
                "<question>确定要删除{$modeLabel}元素 '{$className}' 吗？此操作不可恢复 [y/N]</question> ",
                false
            );
            if (!$confirm) {
                $this->io->write('<comment>已取消删除</comment>');
                return;
            }
        }

        unlink($filePath);
        $this->io->write("<info>✓ {$modeLabel}元素已删除: $filePath</info>");
    }

    /**
     * 解析元素类型
     * @param string|null $specifiedType 指定类型（null=默认，''=交互选择，string=指定类型）
     * @param array $types 可用类型列表
     * @param string $defaultType 默认类型
     */
    private function resolveType(?string $specifiedType, array $types, string $defaultType): ?string
    {
        // 空字符串：强制交互选择
        if ($specifiedType === '') {
            if (!$this->io->isInteractive()) {
                $this->io->write('<error>交互模式不可用，请使用 -t=类型名 指定类型</error>');
                return null;
            }

            $this->io->write('<info>请选择元素类型：</info>');
            foreach ($types as $i => $type) {
                $num = $i + 1;
                $defaultMark = ($type === $defaultType) ? ' (默认)' : '';
                $this->io->write("  [{$num}] {$type}{$defaultMark}");
            }

            $choice = $this->io->ask(
                "<question>请输入类型编号（留空使用默认 '{$defaultType}'）:</question> ",
                ''
            );

            if (empty($choice)) {
                return $defaultType;
            }

            $index = (int)$choice - 1;
            if (isset($types[$index])) {
                return $types[$index];
            }

            $this->io->write("<error>无效的选择，使用默认类型: {$defaultType}</error>");
            return $defaultType;
        }

        // 指定了类型：先转为大驼峰，再验证是否在列表中
        if ($specifiedType !== null) {
            $normalizedType = $this->toCamelCase($specifiedType);
            if (!in_array($normalizedType, $types)) {
                $this->io->write("<error>无效的类型 '{$specifiedType}'，可选类型：" . implode(', ', $types) . "</error>");
                return null;
            }
            return $normalizedType;
        }

        // 默认类型
        return $defaultType;
    }

    /**
     * 交互式询问中文名称
     */
    private function askElementText(string $configName, string $className): string
    {
        $elementText = '';
        if ($this->io->isInteractive()) {
            $elementText = $this->io->ask(
                "<question>请输入元素 '{$configName}' 的中文名称（留空使用默认值 '{$className}'）:</question> ",
                $className
            );
        }
        return empty($elementText) ? $className : $elementText;
    }

    /**
     * 获取元素命名空间
     */
    private function getElementNamespace(string $moduleName, string $mode): string
    {
        if ($mode === 'form') {
            return "xqkeji\\app\\{$moduleName}\\form\\element";
        }
        return "xqkeji\\app\\{$moduleName}\\table\\element";
    }

    /**
     * 生成元素类内容
     */
    private function generateElementContent(string $namespace, string $className, string $configName, string $elementText, string $baseClass, string $useBaseClass, string $mode, ?array $items = null, ?string $defaultValue = null, ?array $modelInfo = null): string
    {
        if ($mode === 'form') {
            // 根据类型确定 attrs
            $attrs = $this->buildFormAttrs($baseClass);

            // 构建 items 属性（无模型时）
            $itemsStr = '';
            if ($items !== null && $modelInfo === null && in_array($baseClass, ['Select', 'Check', 'Radio', 'SelectPicker'])) {
                $itemsStr = $this->buildItemsProperty($items);
            }

            // 构建 defaultValue 属性
            $defaultStr = '';
            if ($defaultValue !== null) {
                $escapedDefault = str_replace("'", "\\'", $defaultValue);
                $defaultStr = "\n    protected \$defaultValue = '{$escapedDefault}';";
            }

            // 构建 template 属性
            $templateStr = '';
            if (in_array($baseClass, ['Check', 'Radio'])) {
                $templateStr = "\n    protected \$template = '@check';";
            } else {
                $templateStr = "\n    protected \$template = '@row';";
            }

            // 构建 beforeRender 方法（有模型时）
            $beforeRenderStr = '';
            if ($modelInfo !== null) {
                $beforeRenderStr = $this->buildBeforeRenderMethod($modelInfo);
            }

            return "<?php\nnamespace {$namespace};\n\nuse {$useBaseClass};\n\nclass {$className} extends {$baseClass}\n{\n    protected \$name = '{$configName}';\n    protected \$text = '{$elementText}';\n    protected \$attrs = {$attrs};{$itemsStr}{$defaultStr}{$templateStr}{$beforeRenderStr}\n}\n";
        } else {
            $useModel = 'xqkeji' . '\\' . 'mvc' . '\\' . 'builder' . '\\' . 'Model';
            return "<?php\nnamespace {$namespace};\n\nuse {$useBaseClass};\nuse {$useModel};\n\nclass {$className} extends {$baseClass}\n{\n    protected \$name = '{$configName}';\n    protected \$text = '{$elementText}';\n    protected \$attrs = [\n        'style' => 'min-width:200px;',\n    ];\n}\n";
        }
    }

    /**
     * 构建 beforeRender 方法（从模型动态加载项目列表）
     */
    private function buildBeforeRenderMethod(array $modelInfo): string
    {
        $modelName = $modelInfo['name'];
        $keyField = $modelInfo['keyField'];
        $valueField = $modelInfo['valueField'];

        // 下标字段为 id 或 _id 时使用 getKey()，否则使用 getAttr
        if ($keyField === 'id' || $keyField === '_id') {
            $keyExpr = '(string)$item->getKey()';
        } else {
            $keyExpr = "\$item->getAttr('{$keyField}')";
        }

        return "\n    public function beforeRender()\n    {\n        \$model=\\xqkeji\\mvc\\builder\\Model::getModel('{$modelName}');\n        \$type=\$model->where('status',1)->order('ordernum')->select();\n        \$items=\$type->all();\n        \$rows=[];\n        if(!empty(\$items))\n        {\n            foreach(\$items as \$item)\n            {\n                \$key={$keyExpr};\n                \$val=\$item->getAttr('{$valueField}');\n                \$rows[\$key]=\$val;\n            }\n        }\n        \$this->setItems(\$rows);\n    }";
    }

    /**
     * 根据类型构建表单 attrs
     */
    private function buildFormAttrs(string $baseClass): string
    {
        if ($baseClass === 'Select' || $baseClass === 'SelectPicker') {
            return "[\n        'class' => 'form-select',\n    ]";
        }
        return "[\n        'required' => 'true',\n        'class' => 'form-control',\n    ]";
    }

    /**
     * 构建 items 属性字符串
     */
    private function buildItemsProperty(array $items): string
    {
        $lines = [];
        foreach ($items as $value => $text) {
            $escapedValue = str_replace("'", "\\'", $value);
            $escapedText = str_replace("'", "\\'", $text);
            $lines[] = "        '{$escapedValue}' => '{$escapedText}'";
        }
        $itemsContent = implode(",\n", $lines);
        return "\n    protected \$items = [\n{$itemsContent},\n    ];";
    }

    /**
     * 解析项目列表
     * @param string|null $items null=无，''=交互输入，string=值1|文本1,值2|文本2
     * @param string $elementType 元素类型
     * @return array|null 解析后的 [值 => 文本] 数组，或 null
     */
    private function resolveItems(?string $items, string $elementType): ?array
    {
        // 只有 Select/Check/Radio/SelectPicker 类型支持 items
        if (!in_array($elementType, ['Select', 'Check', 'Radio', 'SelectPicker'])) {
            return null;
        }

        // 空字符串：交互输入
        if ($items === '') {
            if (!$this->io->isInteractive()) {
                $this->io->write('<comment>交互模式不可用，跳过项目列表设置</comment>');
                return null;
            }

            $this->io->write('<info>请输入项目列表（格式：值|文本 或 值=文本，每行一个，空行结束）：</info>');
            $result = [];
            while (true) {
                $line = $this->io->ask('<question>  项目（值|文本 或 值=文本）:</question> ', '');
                if (trim($line) === '') {
                    break;
                }
                // 支持 | 或 = 作为分隔符
                if (strpos($line, '|') !== false) {
                    $parts = explode('|', $line, 2);
                } elseif (strpos($line, '=') !== false) {
                    $parts = explode('=', $line, 2);
                } else {
                    $this->io->write("<comment>  格式无效，请使用 值|文本 或 值=文本 格式</comment>");
                    continue;
                }
                if (count($parts) === 2) {
                    $result[trim($parts[0])] = trim($parts[1]);
                } else {
                    $this->io->write("<comment>  格式无效，请使用 值|文本 或 值=文本 格式</comment>");
                }
            }

            if (empty($result)) {
                $this->io->write('<comment>未输入项目，跳过</comment>');
                return null;
            }
            return $result;
        }

        // 指定了值：解析 "值1|文本1,值2|文本2" 或 "值1=文本1,值2=文本2"
        if ($items !== null) {
            $result = [];
            $pairs = explode(',', $items);
            foreach ($pairs as $pair) {
                $pair = trim($pair);
                // 支持 | 或 = 作为分隔符
                if (strpos($pair, '|') !== false) {
                    $parts = explode('|', $pair, 2);
                } elseif (strpos($pair, '=') !== false) {
                    $parts = explode('=', $pair, 2);
                } else {
                    continue;
                }
                if (count($parts) === 2) {
                    $result[trim($parts[0])] = trim($parts[1]);
                }
            }
            if (empty($result)) {
                $this->io->write("<error>项目列表格式无效，请使用：值1|文本1,值2|文本2 或 值1=文本1,值2=文本2</error>");
                return null;
            }
            return $result;
        }

        return null;
    }

    /**
     * 解析默认值
     * @param string|null $defaultValue null=无，''=交互输入，string=默认值
     * @return string|null
     */
    private function resolveDefaultValue(?string $defaultValue): ?string
    {
        // 空字符串：交互输入
        if ($defaultValue === '') {
            if (!$this->io->isInteractive()) {
                return null;
            }
            return $this->io->ask(
                "<question>请输入默认值（留空不设置）:</question> ",
                ''
            ) ?: null;
        }

        return $defaultValue;
    }

    /**
     * 解析模型名（Select/Check/Radio/SelectPicker）
     * @param string|null $modelName null=无模型，''=交互输入，string=模型名
     * @param string $elementType 元素类型
     * @return array|null ['name' => 模型名, 'keyField' => 键字段, 'valueField' => 值字段] 或 null
     */
    private function resolveModel(?string $modelName, string $elementType): ?array
    {
        // 只有 Select/Check/Radio/SelectPicker 类型支持模型
        if (!in_array($elementType, ['Select', 'Check', 'Radio', 'SelectPicker'])) {
            if ($modelName !== null) {
                $this->io->write("<comment>模型参数仅支持 Select/Check/Radio/SelectPicker 类型，已忽略</comment>");
            }
            return null;
        }

        // 空字符串：交互输入
        if ($modelName === '') {
            if (!$this->io->isInteractive()) {
                $this->io->write('<comment>交互模式不可用，跳过模型设置</comment>');
                return null;
            }

            $modelName = $this->io->ask(
                "<question>请输入模型名称（留空不设置）:</question> ",
                ''
            );

            if (empty($modelName)) {
                return null;
            }
        }

        // 未指定模型
        if ($modelName === null) {
            return null;
        }

        // 转换为大驼峰
        $modelName = $this->toCamelCase($modelName);

        // 交互询问字段名
        $keyField = $this->io->ask(
            "<question>请输入列表项下标字段名（默认 'id'）:</question> ",
            'id'
        );

        $valueField = $this->io->ask(
            "<question>请输入列表项值字段名（默认 'name'）:</question> ",
            'name'
        );

        return [
            'name' => $modelName,
            'keyField' => $keyField ?: 'id',
            'valueField' => $valueField ?: 'name',
        ];
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
        $result = preg_replace('/([A-Z]+)([A-Z][a-z])/', '$1_$2', $string);
        $result = preg_replace('/([a-z\d])([A-Z])/', '$1_$2', $result);
        return strtolower($result);
    }
}
