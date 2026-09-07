<?php
namespace xqkeji\composer\command;

use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * 命令行参数等号写法支持
 *
 * Symfony Console 原生支持：
 *   -a 值         （短参数 + 空格）
 *   -a值          （短参数连写）
 *   --actions=值  （长参数 + 等号）
 * 但不支持 -a=值，等号会被当成值的一部分（得到 "=值"）。
 *
 * 本 trait 在参数绑定前统一把 "-a=值" / "--actions=值" 展开为两个 token，
 * 使所有生成类命令同时支持上述四种写法：
 *   -a=值、-a 值、-a值、--actions=值
 *
 * 展开结果同步回 $_SERVER['argv']，因此直接读取原始参数的命令
 * （如 xqkeji:form 的 Tab 解析）也能拿到规范化后的参数。
 */
trait NormalizesShortOptions
{
    public function run(InputInterface $input, OutputInterface $output): int
    {
        return parent::run($this->normalizeShortOptionValue($input), $output);
    }

    /**
     * 把 argv 中 "-x=值" / "--name=值" 形式的参数展开为两个 token
     */
    private function normalizeShortOptionValue(InputInterface $input): InputInterface
    {
        // 仅在真实命令行调用（ArgvInput）且能取到 argv 时处理
        if (!$input instanceof ArgvInput) {
            return $input;
        }

        $argv = $_SERVER['argv'] ?? null;
        if (!is_array($argv) || count($argv) < 2) {
            return $input;
        }

        // 只处理本命令中「接受值」的参数，避免影响开关型选项
        $valueOptions = [];
        foreach ($this->getDefinition()->getOptions() as $option) {
            if (!$option->acceptValue()) {
                continue;
            }
            $valueOptions[$option->getName()] = true;
            $shortcut = $option->getShortcut();
            if ('' === (string) $shortcut) {
                continue;
            }
            foreach ((is_array($shortcut) ? $shortcut : explode('|', (string) $shortcut)) as $item) {
                if ('' !== $item) {
                    $valueOptions[$item] = true;
                }
            }
        }

        if (empty($valueOptions)) {
            return $input;
        }

        $tokens = array_slice($argv, 1);
        $normalized = [];
        $changed = false;
        $endOfOptions = false;

        foreach ($tokens as $token) {
            // "--" 之后一律按位置参数处理，不做改写
            if ($endOfOptions || '--' === $token) {
                if ('--' === $token) {
                    $endOfOptions = true;
                }
                $normalized[] = $token;
                continue;
            }

            $token = (string) $token;

            // -a=值 → -a 值
            if (preg_match('/^-(\w)=(.*)$/s', $token, $matches)
                && isset($valueOptions[$matches[1]])) {
                $normalized[] = '-' . $matches[1];
                $normalized[] = $matches[2];
                $changed = true;
                continue;
            }

            // --actions=值 → --actions 值
            if (preg_match('/^--([^\s=]+)=(.*)$/s', $token, $matches)
                && isset($valueOptions[$matches[1]])) {
                $normalized[] = '--' . $matches[1];
                $normalized[] = $matches[2];
                $changed = true;
                continue;
            }

            $normalized[] = $token;
        }

        if (!$changed) {
            return $input;
        }

        // ArgvInput 会丢弃第一个元素（脚本名），这里补回一个占位
        $replacedArgv = array_merge([(string) ($argv[0] ?? 'composer')], $normalized);

        // 同步回 $_SERVER['argv']，供直接读取原始参数的命令（如 xqkeji:form 的 Tab 解析）使用
        $_SERVER['argv'] = $replacedArgv;

        $replaced = new ArgvInput($replacedArgv);
        $replaced->setInteractive($input->isInteractive());

        return $replaced;
    }
}
