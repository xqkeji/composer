<?php
namespace xqkeji\composer;

use Composer\Composer;
use Composer\Factory;
use Composer\IO\IOInterface;

class Form
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

        // 表单显示名复用控制器名中文（共享键 {模块} module {表单名蛇形}）；
        // 表单名=控制器名时该键与控制器显示名键一致，自然复用已有中文、不重复
        Lang::ensureName($this->io, $modulePath, "{$currentModule} module {$configName}", $className);

        // 自动切换为表单模式
        $this->context->switchMode('form');
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

        // 3. 创建新元素 - 只在创建时询问中文名称
        $elementPath = $modulePath . DIRECTORY_SEPARATOR . 'form' . DIRECTORY_SEPARATOR . 'element';
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
        // 元素中文名统一留存到 lang/zh_cn.php（去重：已有翻译不覆盖；键全小写蛇形）
        Lang::ensureName($this->io, $modulePath, "{$currentModule} {$configName} name", $elementText);
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
    private function createElementFile(string $elementPath, string $className, string $configName, string $elementText): void
    {
        $filePath = $elementPath . DIRECTORY_SEPARATOR . $className . '.php';

        if (is_file($filePath)) {
            $this->io->write("<comment>⚠ 表单元素已存在: $filePath</comment>");
            return;
        }

        $currentModule = $this->context->getCurrentModule();
        $content = $this->generateElementContent($currentModule, $className, $configName, $elementText);
        file_put_contents($filePath, $content);
    }

    /**
     * 生成表单元素类内容
     */
    private function generateElementContent(string $moduleName, string $className, string $configName, string $elementText): string
    {
        $namespace = "xqkeji\\app\\{$moduleName}\\form\\element";
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
