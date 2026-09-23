<?php
namespace xqkeji\composer;

/**
 * 在【已存在】的表单/表格类文件中，向 protected $el = [...] 数组交互式追加元素所需的文本解析工具。
 *
 * 生成器只会新建文件、从不回读已有的 $el 数组；本 trait 补齐“读取—定位—在指定位置插入一行引用—写回”
 * 的能力，采用纯文本偏移插入（而非整段重序列化），从而完整保留用户手工编辑过的其它内容与注释。
 *
 * Form 与 Table 均 use 本 trait。为避免与两个类各自的私有方法产生命名冲突，方法统一带 el 前缀。
 */
trait ElInsertTrait
{
    /**
     * 定位类文件中 `... $el = [` 数组的起始 '[' 的绝对下标；找不到返回 null。
     */
    private function elArrayOpen(string $content): ?int
    {
        if (preg_match('/\$el\s*=\s*\[/', $content, $m, PREG_OFFSET_CAPTURE)) {
            // 匹配串末尾即 '['
            return $m[0][1] + strlen($m[0][0]) - 1;
        }
        return null;
    }

    /**
     * 扫描以 $open（'[' 下标）开始的数组，返回其【直接子项】的字符区间列表，每项 ['start','end']
     * （end 为值结束后的下标，不含尾随逗号）。正确处理嵌套 [] ()、带转义的字符串，仅在深度 1 按逗号切分。
     *
     * @return array<int, array{start:int,end:int}>
     */
    private function elScanItems(string $content, int $open): array
    {
        $n = strlen($content);
        $items = [];
        $depth = 1;
        $i = $open + 1;
        $start = null;
        $end = null;

        while ($i < $n) {
            $c = $content[$i];

            // 字符串字面量（含转义）
            if ($c === "'" || $c === '"') {
                if ($start === null) {
                    $start = $i;
                }
                $quote = $c;
                $i++;
                while ($i < $n) {
                    if ($content[$i] === '\\') {
                        $i += 2;
                        continue;
                    }
                    if ($content[$i] === $quote) {
                        $i++;
                        break;
                    }
                    $i++;
                }
                $end = $i;
                continue;
            }

            if ($c === '[' || $c === '(') {
                if ($start === null) {
                    $start = $i;
                }
                $end = $i + 1;
                $depth++;
                $i++;
                continue;
            }

            if ($c === ')') {
                $depth--;
                $end = $i + 1;
                $i++;
                continue;
            }

            if ($c === ']') {
                $depth--;
                if ($depth === 0) {
                    if ($start !== null) {
                        $items[] = ['start' => $start, 'end' => $end];
                    }
                    return $items;
                }
                $end = $i + 1;
                $i++;
                continue;
            }

            if ($c === ',' && $depth === 1) {
                if ($start !== null) {
                    $items[] = ['start' => $start, 'end' => $end];
                }
                $start = null;
                $end = null;
                $i++;
                continue;
            }

            if ($c === ' ' || $c === "\t" || $c === "\n" || $c === "\r") {
                $i++;
                continue;
            }

            if ($start === null) {
                $start = $i;
            }
            $end = $i + 1;
            $i++;
        }

        // 理论上不会到达（数组必然以 ']' 闭合）；容错返回已收集项
        if ($start !== null) {
            $items[] = ['start' => $start, 'end' => $end];
        }
        return $items;
    }

    /**
     * 取某个子项的原文文本（去首尾空白）。
     */
    private function elItemText(string $content, array $item): string
    {
        return trim(substr($content, $item['start'], $item['end'] - $item['start']));
    }

    /**
     * 若子项是一个引用（带引号的字符串，如 '@Id' / '~UserName'），返回其内部名称；否则 null。
     */
    private function elRefName(string $itemText): ?string
    {
        if (preg_match("/^'((?:[^'\\\\]|\\\\.)*)'$/", $itemText, $m)) {
            return $m[1];
        }
        if (preg_match('/^"((?:[^"\\\\]|\\\\.)*)"$/', $itemText, $m)) {
            return $m[1];
        }
        return null;
    }

    /**
     * 若子项是 Tab 分组数组（以 '[' 开头），返回其内部 `'el' => [` 的 '[' 绝对下标；否则 null。
     */
    private function elTabInnerOpen(string $content, array $item): ?int
    {
        $text = $this->elItemText($content, $item);
        if ($text === '' || $text[0] !== '[') {
            return null;
        }
        if (preg_match("/'el'\s*=>\s*(\[)/", $text, $m, PREG_OFFSET_CAPTURE)) {
            return $item['start'] + $m[1][1];
        }
        return null;
    }

    /**
     * 为 Tab 分组项生成交互展示标签。
     */
    private function elTabLabel(string $itemText, int $childCount): string
    {
        $label = 'Tab';
        if (preg_match("/'text'\s*=>\s*'([^']*)'/", $itemText, $m) && $m[1] !== '') {
            $label = $m[1];
        } elseif (preg_match("/'name'\s*=>\s*'([^']*)'/", $itemText, $m) && $m[1] !== '') {
            $label = $m[1];
        }
        return "[Tab] {$label}（{$childCount} 个元素）";
    }

    /**
     * 返回 $childStart 所在行的前导缩进（空格/制表符）。
     */
    private function elLineIndent(string $content, int $childStart): string
    {
        $before = substr($content, 0, $childStart);
        $nl = strrpos($before, "\n");
        $lineStart = ($nl === false) ? 0 : $nl + 1;
        $prefix = substr($content, $lineStart, $childStart - $lineStart);
        if (preg_match('/^([ \t]*)/', $prefix, $m)) {
            return $m[1];
        }
        return $prefix;
    }

    /**
     * 在 $open 开始的数组中，把引用 $ref 插入到位置 $index：
     *   - 空数组：展开为 [ \n{defaultIndent}'ref', \n{closeIndent} ]
     *   - $index <= 0：第一个元素之前
     *   - $index >= 1：第 $index 个元素之后（越界按末尾处理）
     * $ref 为字符串时插入 `'ref',`；为 ['ref'=>..,'name'=>..] 数组时插入多行数组项
     * （搜索表单元素：[ '@X', 'name' => 'xq-s-…' ],）。返回修改后的完整文件内容。
     */
    private function elInsertRef(string $content, int $open, int $index, $ref, string $defaultIndent): string
    {
        $renderLine = static function (string $indent) use ($ref): string {
            if (is_array($ref)) {
                $line = "[\n"
                    . $indent . "    '" . $ref['ref'] . "',\n"
                    . $indent . "    'name' => '" . $ref['name'] . "'";
                if (isset($ref['template'])) {
                    $line .= ",\n" . $indent . "    'template' => '" . $ref['template'] . "'";
                }
                return $line . ",\n" . $indent . "],";
            }
            return "'" . $ref . "',";
        };

        $children = $this->elScanItems($content, $open);

        if (empty($children)) {
            // '[' 之后到 ']' 之前只可能是纯空白。保留这段空白（文件里 ']' 通常已独占一行带缩进），
            // 仅在其前填入条目行；若这段空白里没有换行（如 "[ ]" 同行写法），再补一行闭合缩进。
            $gapEnd = $open + 1;
            $n = strlen($content);
            while ($gapEnd < $n && ($content[$gapEnd] === ' ' || $content[$gapEnd] === "\t" || $content[$gapEnd] === "\n" || $content[$gapEnd] === "\r")) {
                $gapEnd++;
            }
            $gap = substr($content, $open + 1, $gapEnd - $open - 1);
            $insert = "\n" . $defaultIndent . $renderLine($defaultIndent);
            if (strpbrk($gap, "\r\n") === false) {
                $closeIndent = (substr_count($defaultIndent, ' ') >= 4)
                    ? substr($defaultIndent, 0, -4)
                    : '';
                $insert .= "\n" . $closeIndent;
            }
            return substr_replace($content, $insert, $open + 1, 0);
        }

        if ($index <= 0) {
            $indent = $this->elLineIndent($content, $children[0]['start']);
            return substr_replace($content, "\n" . $indent . $renderLine($indent), $open + 1, 0);
        }

        if ($index > count($children)) {
            $index = count($children);
        }
        $anchor = $children[$index - 1];
        $indent = $this->elLineIndent($content, $anchor['start']);

        // 定位锚点后紧跟的逗号（跳过空白），插入到逗号之后
        $pos = $anchor['end'];
        $n = strlen($content);
        while ($pos < $n && ($content[$pos] === ' ' || $content[$pos] === "\t")) {
            $pos++;
        }
        if ($pos < $n && $content[$pos] === ',') {
            $pos++;
        }
        return substr_replace($content, "\n" . $indent . $renderLine($indent), $pos, 0);
    }
}
