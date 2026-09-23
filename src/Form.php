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
    public function createForm(string $formName, array $elements = [], $input = null, $output = null, array $tabGroups = [], array $globalElements = [], bool $isSearchForm = false): void
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
                    // 搜索表单支持内联规格免交互：-e "SearchKey=字段|字段,op"（xq-s- 前缀可省略）
                    $inlineSpec = null;
                    $eqPos = strpos($element, '=');
                    if ($eqPos !== false) {
                        $inlineSpec = trim(substr($element, $eqPos + 1));
                        $element = substr($element, 0, $eqPos);
                        if (!$isSearchForm) {
                            $this->io->write('<comment>⚠ 内联搜索规格（元素=字段,操作）仅搜索表单 -s 支持，已忽略</comment>');
                            $inlineSpec = null;
                        }
                    }
                    $element = trim($element);
                    if ($element === '') {
                        continue;
                    }
                    $freshClass = false;
                    $elementRef = $this->processElement($modulePath, $element, $currentModule, $input, $output, $isSearchForm, $freshClass);
                    if ($elementRef !== null) {
                        $elementRefs[] = $isSearchForm
                            ? $this->buildSearchEntry($elementRef, $inlineSpec, $currentModule, $freshClass)
                            : $elementRef;
                    }
                }
            }

            // 带了 -e：元素统一规范化后，若最后一个元素名不含 submit，询问是否自动追加 @SubmitReset
            if (!empty($elements) && !empty($elementRefs)) {
                $last = $elementRefs[count($elementRefs) - 1];
                $lastName = ltrim((is_array($last) ? $last['ref'] : $last), '@~');
                if (stripos($lastName, 'submit') === false) {
                    $add = true;
                    if ($this->io->isInteractive()) {
                        $add = $this->io->confirm(
                            "<question>最后一个元素 '{$lastName}' 不含 submit，是否自动追加提交/重置按钮 @SubmitReset 作为最后一个元素？</question>",
                            true
                        );
                    } else {
                        $this->io->write('<comment>非交互模式：最后一个元素不含 submit，默认自动追加 @SubmitReset（不想要请在 -e 末尾自带提交类元素）</comment>');
                    }
                    if ($add) {
                        $elementRefs[] = '@SubmitReset';
                        $this->io->write('<info>✓ 已在表单末尾追加 @SubmitReset</info>');
                    }
                }
            }

            // 创建表单类
            $this->createFormFile($formPath, $className, $configName, $elementRefs, $currentModule, $isSearchForm);
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
     * 搜索操作符全集（词别名，GET 安全；符号 = < > 等会污染 URL）
     */
    private const SEARCH_OPS = ['like', 'eq', 'ne', 'gt', 'gte', 'lt', 'lte', 'in', 'nin', 'regex'];

    /**
     * 无输入控件类名特征（词尾匹配，含继承链）：提交/重置/按钮/隐藏域不加搜索规格
     */
    private const SEARCH_NON_INPUT = '/(submit|reset|button|hidden)$/i';

    /**
     * 文本类元素（默认操作符 like，其余默认 eq）
     */
    private const SEARCH_TEXTISH = '/^(text|textarea|searchkey)$/i';

    /**
     * 搜索表单元素统一使用的模板名（与 base SearchKey 一致，小写）
     */
    private const SEARCH_TEMPLATE = '@search';

    /**
     * 为搜索表单元素构建 $el 条目：
     *   - 无输入控件（Submit/Reset/Button/Hidden，含继承链）→ 返回纯引用字符串
     *   - 其余 → 返回 ['ref' => '@X', 'name' => 'xq-s-字段|字段,操作'] 数组条目
     * 规格来源优先级：内联（-e "元素=字段,op"）> 交互两问（字段 / 操作符）> 默认值（字段=元素名蛇形，op 文本类 like 其余 eq）。
     * 模板规则：搜索表单元素统一使用 '@search' 模板——本次新建的元素类已在类体内写入
     * protected \$template = '@search'（$freshClass=true），或元素继承链中已有该声明时，$el 不再内联；
     * 否则在数组条目内联 'template' => '@search'（不改动被复用的既有类文件）。
     */
    private function buildSearchEntry(string $ref, ?string $inlineSpec, string $currentModule, bool $freshClass = false)
    {
        $className = substr($ref, 1);
        $chain = $this->elementClassChain($ref, $currentModule);
        foreach ($chain as $link) {
            if (preg_match(self::SEARCH_NON_INPUT, $link['name'])) {
                return $ref;
            }
        }

        // 是否需要内联 template：新建类已带属性；继承链任一文件已声明 '@search' 也无需重复
        $needInlineTemplate = !$freshClass;
        if ($needInlineTemplate) {
            foreach ($chain as $link) {
                if ($link['file'] !== null
                    && preg_match("/protected\s+\\\$template\s*=\s*'@search'/", (string) @file_get_contents($link['file']))) {
                    $needInlineTemplate = false;
                    break;
                }
            }
        }

        $defaultFields = $this->toSnakeCase($className);
        $textish = false;
        foreach ($chain as $link) {
            if (preg_match(self::SEARCH_TEXTISH, $link['name'])) {
                $textish = true;
                break;
            }
        }
        $defaultOp = $textish ? 'like' : 'eq';

        $finish = function (string $fields, string $op) use ($ref, $needInlineTemplate) {
            $entry = ['ref' => $ref, 'name' => "xq-s-{$fields},{$op}"];
            if ($needInlineTemplate) {
                $entry['template'] = '@search';
            }
            return $entry;
        };

        if ($inlineSpec !== null && $inlineSpec !== '') {
            $parsed = $this->parseSearchSpec($inlineSpec, $defaultOp);
            if ($parsed !== null) {
                $pos = strrpos($parsed, ',');
                return $finish(substr($parsed, 0, $pos), substr($parsed, $pos + 1));
            }
            $this->io->write("<error>内联搜索规格无效：\"{$inlineSpec}\"（格式 字段[|字段][,操作]，操作 ∈ " . implode('/', self::SEARCH_OPS) . "），改为交互/默认</error>");
        }

        $fields = $defaultFields;
        $op = $defaultOp;

        if ($this->io->isInteractive()) {
            $ans = $this->io->ask(
                "<question>元素 {$className} 的搜索字段（多字段用 | 表示“或”，默认 {$defaultFields}）:</question> ",
                $defaultFields
            );
            $ans = trim((string) $ans);
            if ($ans !== '' && preg_match('/^[A-Za-z_][A-Za-z0-9_]*(\|[A-Za-z_][A-Za-z0-9_]*)*$/', $ans)) {
                $fields = $ans;
            } elseif ($ans !== '') {
                $this->io->write("<error>搜索字段格式无效（字母/数字/下划线，| 分隔），使用默认 {$defaultFields}</error>");
            }

            $ans = $this->io->ask(
                "<question>搜索操作（" . implode('/', self::SEARCH_OPS) . "，默认 {$defaultOp}）:</question> ",
                $defaultOp
            );
            $ans = strtolower(trim((string) $ans));
            if ($ans === '') {
                $op = $defaultOp;
            } elseif (in_array($ans, self::SEARCH_OPS, true)) {
                $op = $ans;
            } else {
                $this->io->write("<error>无效操作 \"{$ans}\"（仅支持 " . implode('/', self::SEARCH_OPS) . "），使用默认 {$defaultOp}</error>");
            }
        } else {
            $this->io->write("<comment>非交互模式：{$className} 使用默认搜索规格 {$fields},{$op}</comment>");
        }

        return $finish($fields, $op);
    }

    /**
     * 解析搜索规格（可带/不带 xq-s- 前缀）：字段[|字段][,操作] → 规范化 "字段,操作"；无效返回 null
     */
    private function parseSearchSpec(string $spec, string $defaultOp): ?string
    {
        $spec = trim($spec);
        if (str_starts_with($spec, 'xq-s-')) {
            $spec = substr($spec, strlen('xq-s-'));
        }
        if (!preg_match('/^([A-Za-z_][A-Za-z0-9_]*(?:\|[A-Za-z_][A-Za-z0-9_]*)*)(?:,([A-Za-z]+))?$/', $spec, $m)) {
            return null;
        }
        $op = isset($m[2]) && $m[2] !== '' ? strtolower($m[2]) : $defaultOp;
        if (!in_array($op, self::SEARCH_OPS, true)) {
            return null;
        }
        return $m[1] . ',' . $op;
    }

    /**
     * 元素类继承链：每项 ['name' => 类名, 'file' => ?string 源文件]。
     * 从元素引用文件起，沿 `class X extends Y` 逐级上溯，父类文件在当前模块与 base 模块的
     * form/element/ 中查找，找不到即止（框架基类名收入链尾、file 为 null）。
     */
    private function elementClassChain(string $ref, string $currentModule): array
    {
        $className = substr($ref, 1);
        $isBase = ($ref[0] ?? '') === '@';
        $chain = [];
        for ($i = 0; $i < 6 && $className !== ''; $i++) {
            $file = $isBase
                ? $this->findElementInModule('base', $className)
                : ($this->findElementInModule($currentModule, $className) ?? $this->findElementInModule('base', $className));
            $chain[] = ['name' => $className, 'file' => $file];
            if ($file === null) {
                break;
            }
            $src = (string) file_get_contents($file);
            if (!preg_match('/class\s+\w+\s+extends\s+([\\\\\w]+)/i', $src, $m)) {
                break;
            }
            $parent = str_replace('\\', '/', $m[1]);
            $className = substr($parent, (int) strrpos($parent, '/') + 1);
            $isBase = false;
        }
        return $chain;
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
        $isSearchFormFile = (bool) preg_match('/class\s+\w+\s+extends\s+SearchForm\b/', $content);

        // 元素列表展示标签：数组项压缩为 "@X name='xq-s-…'"
        $itemLabel = function (string $text): string {
            if (preg_match("/^\[\s*'([^']+)'\s*,\s*'name'\s*=>\s*'([^']+)'/", $text, $m)) {
                return $m[1] . " name='{$m[2]}'";
            }
            return trim(preg_replace('/\s+/', ' ', $text));
        };

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
                    $label = $this->elRefName($text) ?? $itemLabel($text);
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

        // 搜索表单（extends SearchForm）：追加时同样询问/生成 xq-s- 搜索规格
        $entry = $isSearchFormFile ? $this->buildSearchEntry($ref, null, $currentModule) : $ref;

        $newContent = $this->elInsertRef($content, $targetOpen, $targetIndex, $entry, $defaultIndent);
        if (file_put_contents($formFile, $newContent) === false) {
            $this->io->write("<error>写入表单文件失败: {$formFile}</error>");
            return;
        }
        $desc = is_array($entry)
            ? "{$entry['ref']}（name='{$entry['name']}" . (isset($entry['template']) ? "', template='{$entry['template']}" : '') . "'）"
            : "'{$ref}'";
        $this->io->write("<info>✓ 已将元素 {$desc} 添加到表单 {$className}（{$positionDesc}）</info>");
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
     *
     * $isSearch=true 时新建的元素类模板写 '@search'（搜索表单）；
     * $freshClass 出参：true=本次新建了元素类文件，false=复用已有（搜索表单据此决定是否内联 template）。
     */
    private function processElement(string $modulePath, string $elementName, string $currentModule, $input = null, $output = null, bool $isSearch = false, ?bool &$freshClass = null): ?string
    {
        // 转换为大驼峰类名
        $className = $this->toCamelCase($elementName);
        // 转换为小写下划线格式
        $configName = $this->toSnakeCase($elementName);

        // 1. 先在 base 模块查找
        $baseElementPath = $this->findElementInModule('base', $className);
        if ($baseElementPath !== null) {
            $this->io->write("<info>✓ 在 base 模块找到元素: $className</info>");
            $freshClass = false;
            return '@' . $className;
        }

        // 2. 在当前模块查找
        $currentElementPath = $this->findElementInModule($currentModule, $className);
        if ($currentElementPath !== null) {
            $this->io->write("<info>✓ 在当前模块找到元素: $className</info>");
            $freshClass = false;
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
            $wrote = $this->createElementFile($elementPath, $className, $configName, '', true, $isSearch);
            $this->io->write("<info>✓ 已创建表单元素（SelectModel 子类）: $className</info>");
            $freshClass = $wrote;
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

        $wrote = $this->createElementFile($elementPath, $className, $configName, $elementText, false, $isSearch);
        $this->io->write("<info>✓ 已创建表单元素: $className</info>");
        $freshClass = $wrote;
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
     * 创建表单元素文件；返回是否实际写入（false=文件已存在，仅提示）
     */
    private function createElementFile(string $elementPath, string $className, string $configName, string $elementText, bool $isSelect = false, bool $isSearch = false): bool
    {
        $filePath = $elementPath . DIRECTORY_SEPARATOR . $className . '.php';

        if (is_file($filePath)) {
            $this->io->write("<comment>⚠ 表单元素已存在: $filePath</comment>");
            return false;
        }

        $currentModule = $this->context->getCurrentModule();
        $content = $this->generateElementContent($currentModule, $className, $configName, $elementText, $isSelect, $isSearch);
        file_put_contents($filePath, $content);
        return true;
    }

    /**
     * 生成表单元素类内容
     *
     * 默认生成继承 Text 的完整元素类；$isSelect 为 true 时生成继承 SelectModel 的空类
     * （select_ 开头元素的约定：类体为空，直接继承，由 SelectModel 提供行为）。
     * $isSearch 为 true（搜索表单新建的元素类）时模板用 '@search'：Text 类的 $template 改写，
     * SelectModel 空子类则补写该属性。
     */
    private function generateElementContent(string $moduleName, string $className, string $configName, string $elementText, bool $isSelect = false, bool $isSearch = false): string
    {
        $namespace = "xqkeji\\app\\{$moduleName}\\form\\element";

        if ($isSelect) {
            $useSelectModel = 'xqkeji' . '\\' . 'form' . '\\' . 'element' . '\\' . 'SelectModel';
            $body = $isSearch ? "    protected \$template = '@search';\n" : '';
            return "<?php\nnamespace {$namespace};\n\nuse {$useSelectModel};\n\nclass {$className} extends SelectModel\n{\n{$body}}\n";
        }

        $useText = 'xqkeji' . '\\' . 'form' . '\\' . 'element' . '\\' . 'Text';
        $template = $isSearch ? '@search' : '@row';

        return "<?php\nnamespace {$namespace};\n\nuse {$useText};\n\nclass {$className} extends Text\n{\n    protected \$name = '{$configName}';\n    protected \$text = '{$elementText}';\n    protected \$attrs = [\n        'required' => 'true',\n        'class' => 'form-control',\n    ];\n    protected \$filters = ['string'];\n    protected \$vt = [['required']];\n    protected \$template = '{$template}';\n}\n";
    }

    /**
     * 创建表单文件（$isSearchForm=true 时生成继承 SearchForm 的搜索表单）
     */
    private function createFormFile(string $formPath, string $className, string $configName, array $elementRefs, string $moduleName, bool $isSearchForm = false): void
    {
        $filePath = $formPath . DIRECTORY_SEPARATOR . $className . '.php';

        if (is_file($filePath)) {
            $this->io->write("<error>表单已存在: $filePath</error>");
            return;
        }

        $content = $this->generateFormContent($moduleName, $className, $configName, $elementRefs, $isSearchForm);
        file_put_contents($filePath, $content);

        $this->io->write("<info>✓ " . ($isSearchForm ? '搜索表单' : '表单') . "已创建: $filePath</info>");
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
     * 生成表单类内容（$isSearchForm=true 时继承 SearchForm）
     */
    private function generateFormContent(string $moduleName, string $className, string $configName, array $elementRefs, bool $isSearchForm = false): string
    {
        $namespace = "xqkeji\\app\\{$moduleName}\\form";

        // 构建元素列表字符串（$item 为字符串=纯引用；为 ['ref','name'] 数组=搜索表单数组项）
        $elementsStr = '';
        if (!empty($elementRefs)) {
            $elementsStr = "\n";
            foreach ($elementRefs as $item) {
                if (is_array($item)) {
                    $elementsStr .= "        [\n            '{$item['ref']}',\n            'name' => '{$item['name']}',";
                    if (isset($item['template'])) {
                        $elementsStr .= "\n            'template' => '{$item['template']}',";
                    }
                    $elementsStr .= "\n        ],\n";
                } else {
                    $elementsStr .= "        '{$item}',\n";
                }
            }
            $elementsStr .= "    ";
        }

        $baseClass = $isSearchForm ? 'SearchForm' : 'Form';
        $useForm = 'xqkeji' . '\\' . 'form' . '\\' . $baseClass;

        // 搜索表单：与手写范例一致，自带 method=get 与行内排版 attrs
        $attrsProp = $isSearchForm
            ? "    protected \$attrs = [\n        'method' => 'get',\n        'class' => 'd-flex flex-wrap justify-content-end gap-2',\n    ];\n\n"
            : '';

        return "<?php\nnamespace {$namespace};\n\nuse {$useForm};\n\nclass {$className} extends {$baseClass}\n{\n    protected \$name = '{$configName}';\n\n{$attrsProp}    // 表单元素列表\n    protected \$el = [{$elementsStr}];\n}\n";
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
