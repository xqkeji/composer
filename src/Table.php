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

        // 转换为大驼峰类名
        $className = $this->toCamelCase($tableName);
        // 转换为小写下划线格式，用于配置文件
        $configName = $this->toSnakeCase($tableName);

        // 创建 table 目录
        $tablePath = $modulePath . DIRECTORY_SEPARATOR . 'table';
        if (!is_dir($tablePath)) {
            mkdir($tablePath, 0755, true);
        }

        // 创建表格元素
        $elementRefs = [];
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

        // 创建表格类
        $this->createTableFile($tablePath, $className, $configName, $elementRefs, $currentModule, $isTree);

        // 树形表格：自动检查并复制对应的树状控制器动作类（controller/{表格名大驼峰}/）
        if ($isTree) {
            $this->ensureTreeController($modulePath, $currentModule, $className);
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

        // 3. 创建新元素 - 只在创建时询问中文名称
        $elementPath = $modulePath . DIRECTORY_SEPARATOR . 'table' . DIRECTORY_SEPARATOR . 'element';
        if (!is_dir($elementPath)) {
            mkdir($elementPath, 0755, true);
        }

        // 交互式询问中文名称
        $elementText = '';
        if ($this->io->isInteractive()) {
            $elementText = $this->io->ask(
                "<question>请输入元素 '$configName' 的中文名称（留空使用默认值 '$className'）:</question> ",
                $className
            );
        }

        // 如果没有提供中文名称，使用默认值
        if (empty($elementText)) {
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

        // 根据 $isTree 选择基类
        if ($isTree) {
            $useTable = 'xqkeji' . '\\' . 'form' . '\\' . 'TreegridTable';
            $baseClass = 'TreegridTable';
        } else {
            $useTable = 'xqkeji' . '\\' . 'form' . '\\' . 'Table';
            $baseClass = 'Table';
        }
        
        return "<?php\nnamespace {$namespace};\n\nuse {$useTable};\n\nclass {$className} extends {$baseClass}\n{\n    protected \$name = '{$configName}';\n    protected \$foot = '@Foot';\n    \n    // 表格元素列表\n    protected \$el = [{$elementsStr}];\n}\n";
    }

    /**
     * 树形表格：自动检查并复制对应的树状控制器动作类
     *
     * 目标目录：{模块路径}/controller/{表格名大驼峰}/
     * 源模板：插件自身的 src/example/src/controller/tree/
     * 复制时替换命名空间占位符 {MODULE_NAME} -> 当前模块、{CONTROLLER_NAME} -> 表格名大驼峰
     */
    private function ensureTreeController(string $modulePath, string $currentModule, string $className): void
    {
        $controllerDir = $modulePath . DIRECTORY_SEPARATOR . 'controller' . DIRECTORY_SEPARATOR . $className;

        if (is_dir($controllerDir)) {
            $this->io->write("<comment>⚠ 树状控制器目录已存在，跳过复制: {$controllerDir}</comment>");
            return;
        }

        // 插件自身的 example 树状控制器模板目录（与 Table.php 同级的 src 下）
        $treeTemplateDir = __DIR__ . DIRECTORY_SEPARATOR . 'example' . DIRECTORY_SEPARATOR
            . 'src' . DIRECTORY_SEPARATOR . 'controller' . DIRECTORY_SEPARATOR . 'tree';

        if (!is_dir($treeTemplateDir)) {
            $this->io->write("<error>未找到树状控制器模板目录: {$treeTemplateDir}</error>");
            return;
        }

        if (!mkdir($controllerDir, 0755, true) && !is_dir($controllerDir)) {
            $this->io->write("<error>创建树状控制器目录失败: {$controllerDir}</error>");
            return;
        }

        $files = glob($treeTemplateDir . DIRECTORY_SEPARATOR . '*.php') ?: [];
        if (empty($files)) {
            $this->io->write("<comment>⚠ 树状控制器模板目录为空，未复制任何文件: {$treeTemplateDir}</comment>");
            return;
        }

        foreach ($files as $templateFile) {
            $content = file_get_contents($templateFile);
            if ($content === false) {
                $this->io->write("<error>读取树状控制器模板失败: {$templateFile}</error>");
                continue;
            }
            // 替换命名空间占位符
            $content = str_replace(
                ['{MODULE_NAME}', '{CONTROLLER_NAME}'],
                [$currentModule, $className],
                $content
            );
            $target = $controllerDir . DIRECTORY_SEPARATOR . basename($templateFile);
            file_put_contents($target, $content);
            $this->io->write("<info>✓ 已复制树状控制器动作类: {$target}</info>");
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
