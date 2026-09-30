<?php
namespace xqkeji\composer;

/**
 * 在【已存在】的表单/表格类文件中，从 protected $el = [...] 数组删除指定子项所需的文本工具。
 *
 * 与 ElInsertTrait 同理：采用纯文本区间删除（而非整段重序列化），完整保留用户手工编辑过的其它内容与注释。
 * Form 与 Table 均 use 本 trait，方法统一带 el 前缀避免与两个类各自的私有方法命名冲突。
 */
trait ElRemoveTrait
{
    /**
     * 为若干子项（elScanItems 结果）计算“整行删除区间”：从条目所在行的行首（含缩进）删到行尾换行符，
     * 并吞掉条目后可能的尾随逗号与空白。多行数组项（搜索表单条目）整体作为一条区间删除。
     * 返回按 start 降序的 ['start','len'] 列表（倒序便于 elApplyDeletes 直接应用）。
     *
     * @param array<int, array{start:int,end:int}> $items
     * @return array<int, array{start:int,len:int}>
     */
    private function elDeleteRegions(string $content, array $items): array
    {
        $n = strlen($content);
        $regions = [];
        foreach ($items as $item) {
            $before = substr($content, 0, $item['start']);
            $nl = strrpos($before, "\n");
            $lineStart = ($nl === false) ? 0 : $nl + 1;

            // 行首到条目之间必须只有缩进空白，才可按整行删除（防止同行前面还挂着别的内容）
            $prefix = substr($content, $lineStart, $item['start'] - $lineStart);
            if ($prefix !== '' && trim($prefix) === '') {
                $end = $item['end'];
                // 跳过空格/制表符后的尾随逗号
                $p = $end;
                while ($p < $n && ($content[$p] === ' ' || $content[$p] === "\t")) {
                    $p++;
                }
                if ($p < $n && $content[$p] === ',') {
                    $end = $p + 1;
                }
                // 吞掉行尾的 \r\n / \n（仅当其后直到换行再无其他内容）
                $p = $end;
                while ($p < $n && ($content[$p] === ' ' || $content[$p] === "\t")) {
                    $p++;
                }
                if ($p < $n && ($content[$p] === "\n" || $content[$p] === "\r")) {
                    if ($content[$p] === "\r" && $p + 1 < $n && $content[$p + 1] === "\n") {
                        $p++;
                    }
                    $end = $p + 1;
                }
                $regions[] = ['start' => $lineStart, 'len' => $end - $lineStart];
            } else {
                // 同行前有内容：只删条目本身（+尾随逗号与随后空白），不吞换行
                $end = $item['end'];
                $p = $end;
                while ($p < $n && ($content[$p] === ' ' || $content[$p] === "\t")) {
                    $p++;
                }
                if ($p < $n && $content[$p] === ',') {
                    $end = $p + 1;
                    while ($end < $n && ($content[$end] === ' ' || $content[$end] === "\t")) {
                        $end++;
                    }
                }
                $regions[] = ['start' => $item['start'], 'len' => $end - $item['start']];
            }
        }
        usort($regions, static function (array $a, array $b): int {
            return $b['start'] <=> $a['start'];
        });
        return $regions;
    }

    /**
     * 按倒序应用删除区间（区间互不重叠，倒序保证前段偏移不受后段删除影响）。
     */
    private function elApplyDeletes(string $content, array $regions): string
    {
        foreach ($regions as $r) {
            $content = substr_replace($content, '', $r['start'], $r['len']);
        }
        return $content;
    }

    /**
     * 收集 el 数组展示/删除时用的引用名（'@X' / '~X' 字符串项返回内部引用；数组项取首元素引用）。
     */
    private function elItemRef(string $itemText): ?string
    {
        $rn = $this->elRefName($itemText);
        if ($rn !== null) {
            return $rn;
        }
        // 搜索表单数组项：[ '@X', 'name' => '...' ] → 取 '@X'
        if ($itemText !== '' && $itemText[0] === '['
            && preg_match("/^\\[\s*'((?:[^'\\\\]|\\\\.)*)'/", $itemText, $m)) {
            return $m[1];
        }
        return null;
    }

    /**
     * 删除选择列表里的展示文本：普通引用显示 @X/~X，搜索数组项附带 name 规格，其余压缩成单行。
     */
    private function elRemovalLabel(string $itemText): string
    {
        if ($itemText !== '' && $itemText[0] === '['
            && preg_match("/^\\[\\s*'((?:[^'\\\\]|\\\\.)*)'\\s*,\\s*'name'\\s*=>\\s*'([^']*)'/", $itemText, $m)) {
            return $m[1] . " name='{$m[2]}'";
        }
        $ref = $this->elItemRef($itemText);
        if ($ref !== null) {
            return $ref;
        }
        return trim(preg_replace('/\s+/', ' ', $itemText));
    }

    /**
     * 递归列出可删除的 $el 条目：顶层编号 1、2…；Tab 分组内的元素编号为 父.子（如 2.1）。
     *
     * @return array{labels:array<string,string>,targets:array<string,array{item:array{start:int,end:int},tab:bool,innerOpen?:int}>}
     */
    private function elRemovalListing(string $content, int $open): array
    {
        $labels = [];
        $targets = [];
        foreach ($this->elScanItems($content, $open) as $idx => $item) {
            $num = (string) ($idx + 1);
            $text = $this->elItemText($content, $item);
            $innerOpen = $this->elTabInnerOpen($content, $item);
            if ($innerOpen !== null) {
                $children = $this->elScanItems($content, $innerOpen);
                $labels[$num] = $this->elTabLabel($text, count($children));
                $targets[$num] = ['item' => $item, 'tab' => true, 'innerOpen' => $innerOpen];
                foreach ($children as $j => $child) {
                    $childNum = $num . '.' . ($j + 1);
                    $labels[$childNum] = '    └ ' . $this->elRemovalLabel($this->elItemText($content, $child));
                    $targets[$childNum] = ['item' => $child, 'tab' => false];
                }
                continue;
            }
            $labels[$num] = $this->elRemovalLabel($text);
            $targets[$num] = ['item' => $item, 'tab' => false];
        }
        return ['labels' => $labels, 'targets' => $targets];
    }

    /**
     * 丢弃被其它区间完全包含的区间（同时选中 Tab 分组与其内部元素时，只删分组整块）。
     *
     * @param array<int, array{start:int,len:int}> $regions
     * @return array<int, array{start:int,len:int}>
     */
    private function elDropNestedRegions(array $regions): array
    {
        $drop = [];
        foreach ($regions as $i => $a) {
            foreach ($regions as $j => $b) {
                if ($i === $j || isset($drop[$j])) {
                    continue;
                }
                if ($b['start'] >= $a['start'] && $b['start'] + $b['len'] <= $a['start'] + $a['len'] && $b['len'] < $a['len']) {
                    $drop[$j] = true;
                }
            }
        }
        foreach ($drop as $j => $_) {
            unset($regions[$j]);
        }
        return array_values($regions);
    }

    /**
     * 交互式删除 $el 条目（xqkeji:form / xqkeji:table 的 -r/--remove 共用）。
     *
     * 列出带编号的元素（Tab 分组递归为 父.子）→ 接受逗号分隔的多个编号 → 按整行文本区间删除引用行。
     * 返回 ['content' => 新内容, 'refs' => 被删除的元素引用列表, 'count' => 选中的编号数]；
     * 用户取消、无元素、编号非法时返回 null（调用方不落盘）。
     * Tab 分组本身不是元素，不计入 refs，但其内部元素的引用会计入（供后续元素类文件清理判断）。
     */
    private function elRemoveFlow(string $content, int $open, string $listTitle, string $typeWord): ?array
    {
        if (!$this->io->isInteractive()) {
            $this->io->write("<error>交互模式不可用，无法选择要删除的{$typeWord}（请在终端下运行）</error>");
            return null;
        }

        $listing = $this->elRemovalListing($content, $open);
        if (empty($listing['labels'])) {
            $this->io->write("<comment>当前暂无{$typeWord}可删除。</comment>");
            return null;
        }

        $this->io->write("<info>{$listTitle}</info>");
        foreach ($listing['labels'] as $num => $label) {
            $this->io->write("  [{$num}] {$label}");
        }
        $this->io->write('');

        $answer = trim((string) $this->io->ask(
            "<question>请输入要删除的{$typeWord}编号（可多选，逗号分隔，如 2 或 3,5.1；留空取消）:</question> ",
            ''
        ));
        if ($answer === '') {
            $this->io->write('<comment>已取消</comment>');
            return null;
        }

        $nums = [];
        foreach (preg_split('/[,，\s]+/u', $answer, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $num) {
            if (!isset($listing['targets'][$num]) || !preg_match('/^\d+(\.\d+)?$/', $num)) {
                $this->io->write("<error>无效编号：{$num}（可选编号：" . implode('、', array_keys($listing['labels'])) . '）</error>');
                return null;
            }
            if (!in_array($num, $nums, true)) {
                $nums[] = $num;
            }
        }
        if (empty($nums)) {
            $this->io->write('<error>未识别到有效编号，已取消</error>');
            return null;
        }

        $items = [];
        $refs = [];
        $collectRef = function (array $item) use (&$refs, $content): void {
            $ref = $this->elItemRef($this->elItemText($content, $item));
            if ($ref !== null && !in_array($ref, $refs, true)) {
                $refs[] = $ref;
            }
        };
        foreach ($nums as $num) {
            $target = $listing['targets'][$num];
            $items[] = $target['item'];
            if ($target['tab']) {
                // 删掉整个 Tab 分组：其内部元素的引用一并计入清理判断
                foreach ($this->elScanItems($content, $target['innerOpen']) as $child) {
                    if ($this->elTabInnerOpen($content, $child) === null) {
                        $collectRef($child);
                    }
                }
                continue;
            }
            $collectRef($target['item']);
        }

        $regions = $this->elDropNestedRegions($this->elDeleteRegions($content, $items));
        return ['content' => $this->elApplyDeletes($content, $regions), 'refs' => $refs, 'count' => count($nums)];
    }

    /**
     * 删除引用后，对被移除的 ~本模块元素做可选清理：在本模块所有表单/表格类文件里已无任何引用时，
     * 询问是否顺带删除该元素类文件（默认不删）。@base 元素、仍被引用的元素、元素类文件不存在的都跳过。
     */
    private function elOfferElementCleanup(string $modulePath, string $elementDir, array $refs): void
    {
        $names = [];
        foreach ($refs as $ref) {
            if (isset($ref[1]) && $ref[0] === '~' && preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', substr($ref, 1))) {
                $names[substr($ref, 1)] = true;
            }
        }
        if (empty($names)) {
            return;
        }

        $scanFiles = [];
        foreach (['form', 'table'] as $dir) {
            $found = glob($modulePath . DIRECTORY_SEPARATOR . $dir . DIRECTORY_SEPARATOR . '*.php');
            if ($found !== false) {
                $scanFiles = array_merge($scanFiles, $found);
            }
        }

        foreach (array_keys($names) as $name) {
            foreach ($scanFiles as $file) {
                $fileContent = @file_get_contents($file);
                if ($fileContent !== false && preg_match('/~' . preg_quote($name, '/') . '(?![a-zA-Z0-9_])/', $fileContent)) {
                    $this->io->write("<comment>  元素 ~{$name} 仍被其它表单/表格引用，保留其元素类文件</comment>");
                    continue 2;
                }
            }

            $elementFile = $modulePath . DIRECTORY_SEPARATOR . $elementDir . DIRECTORY_SEPARATOR . $name . '.php';
            if (!is_file($elementFile)) {
                continue;
            }
            if (!$this->confirmIO($this->io, "<question>元素 {$name} 已无任何表单/表格引用，是否顺带删除其元素类文件？</question> ", false)) {
                $this->io->write("  已保留元素类文件: {$elementFile}");
                continue;
            }
            if (@unlink($elementFile) === false) {
                $this->io->write("<error>删除元素类文件失败: {$elementFile}</error>");
                continue;
            }
            $this->io->write("<info>✓ 已删除元素类文件: {$elementFile}</info>");
        }
    }
}
