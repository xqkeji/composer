<?php
namespace xqkeji\composer;

use Composer\IO\IOInterface;

/**
 * y/n 确认交互兼容层：宿主 Composer 较旧的 ConsoleIO 没有 confirm() 方法
 * （直接调用会报 Call to undefined method），此时降级为 ask() 输入 y/n 解析。
 * 空回答取默认值；无法识别的回答也取默认值。
 */
trait ConfirmTrait
{
    protected function confirmIO(IOInterface $io, string $question, bool $default = false): bool
    {
        if (method_exists($io, 'confirm')) {
            return (bool) $io->confirm($question, $default);
        }

        $hint = $default ? ' [Y/n]' : ' [y/N]';
        $answer = strtolower(trim((string) $io->ask($question . $hint)));
        if ($answer === '') {
            return $default;
        }
        if (in_array($answer, ['y', 'yes', '是'], true)) {
            return true;
        }
        if (in_array($answer, ['n', 'no', '否'], true)) {
            return false;
        }
        return $default;
    }
}
