<?php
namespace xqkeji\composer\command;

/**
 * -e/--element 只给出选项、未给出任何元素值时，进入交互式循环逐个收集元素名称：
 * 每次询问元素名（留空结束）→ 加入列表 → 询问是否继续（否结束）。
 * 收集到的名称原样返回，交由 Form/Table 既有的元素流程（复用/创建、中文名、首尾规范化）处理。
 * FormCommand 与 TableCommand 共用。
 */
trait ElementLoopTrait
{
    /**
     * @param \Composer\IO\IOInterface $io    命令 IO
     * @param string $typeCn                  称呼（“元素”/“列元素”），用于提示文案
     * @return string[] 收集到的元素名称列表（可能为空：用户直接结束）
     */
    private function collectElementsLoop($io, string $typeCn): array
    {
        if (!$io->isInteractive()) {
            $io->write("<error>-e 未指定{$typeCn}需要终端交互逐个输入（非交互模式请显式传入：-e {$typeCn}1,{$typeCn}2）</error>");
            return [];
        }

        $io->write("<comment>-e 未指定{$typeCn}，进入交互式逐个添加模式（名称留空或选择停止即结束，结束时按规则自动处理首尾{$typeCn}）</comment>");

        $names = [];
        while (true) {
            $n = trim((string) $io->ask(
                sprintf('<question>请输入第 %d 个%s名称（如 username 或 select_dept，留空结束）:</question> ', count($names) + 1, $typeCn),
                ''
            ));
            if ($n === '') {
                break;
            }
            if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_\- ]*$/', $n)) {
                $io->write('<error>名称无效：需以字母开头，仅可包含字母、数字、下划线、- 和空格，请重新输入</error>');
                continue;
            }
            $names[] = $n;
            if (!$io->confirm("<question>已加入 {$typeCn} '{$n}'，是否继续添加下一个{$typeCn}？</question>", true)) {
                break;
            }
        }

        if (empty($names)) {
            $io->write('<comment>未输入任何元素，将创建空列表</comment>');
        }
        return $names;
    }
}
