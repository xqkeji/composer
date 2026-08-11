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
     */
    public function createElement(string $elementName, ?string $specifiedType = null): void
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

        // 生成并写入
        $useBaseClass = 'xqkeji' . '\\' . 'form' . '\\' . 'element' . '\\' . $elementType;
        $namespace = $this->getElementNamespace($currentModule, $currentMode);
        $content = $this->generateElementContent($namespace, $className, $configName, $elementText, $elementType, $useBaseClass, $currentMode);
        file_put_contents($filePath, $content);

        $this->io->write("<info>✓ {$modeLabel}元素已创建: $filePath</info>");
        $this->io->write("  类型: {$elementType}");
    }

    /**
     * 修改元素（公开方法）
     * @param string $elementName 元素名称
     * @param string|null $specifiedType 指定类型（null=默认类型，''=交互选择，string=指定类型）
     */
    public function editElement(string $elementName, ?string $specifiedType = null): void
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

        $useBaseClass = 'xqkeji' . '\\' . 'form' . '\\' . 'element' . '\\' . $elementType;
        $namespace = $this->getElementNamespace($currentModule, $currentMode);
        $content = $this->generateElementContent($namespace, $className, $configName, $elementText, $elementType, $useBaseClass, $currentMode);
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

        // 指定了类型：验证是否在列表中
        if ($specifiedType !== null) {
            if (!in_array($specifiedType, $types)) {
                $this->io->write("<error>无效的类型 '{$specifiedType}'，可选类型：" . implode(', ', $types) . "</error>");
                return null;
            }
            return $specifiedType;
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
    private function generateElementContent(string $namespace, string $className, string $configName, string $elementText, string $baseClass, string $useBaseClass, string $mode): string
    {
        if ($mode === 'form') {
            return "<?php\nnamespace {$namespace};\n\nuse {$useBaseClass};\n\nclass {$className} extends {$baseClass}\n{\n    protected \$name = '{$configName}';\n    protected \$text = '{$elementText}';\n    protected \$attrs = [\n        'required' => 'true',\n        'class' => 'form-control',\n    ];\n    protected \$filters = ['string'];\n    protected \$vt = [['required']];\n    protected \$template = '@row';\n}\n";
        } else {
            $useModel = 'xqkeji' . '\\' . 'mvc' . '\\' . 'builder' . '\\' . 'Model';
            return "<?php\nnamespace {$namespace};\n\nuse {$useBaseClass};\nuse {$useModel};\n\nclass {$className} extends {$baseClass}\n{\n    protected \$name = '{$configName}';\n    protected \$text = '{$elementText}';\n    protected \$attrs = [\n        'style' => 'min-width:200px;',\n    ];\n}\n";
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
        $result = preg_replace('/([A-Z]+)([A-Z][a-z])/', '$1_$2', $string);
        $result = preg_replace('/([a-z\d])([A-Z])/', '$1_$2', $result);
        return strtolower($result);
    }
}
