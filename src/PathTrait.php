<?php
namespace xqkeji\composer;

use Composer\Factory;

trait PathTrait{
    public static function getRootPath() : string
    {
        // 使用 Composer 获取项目根目录
        $composerFile = Factory::getComposerFile();
        return dirname(realpath($composerFile));
    }
    public static function getRootConfigPath() : string
    {
        return self::getRootPath().DIRECTORY_SEPARATOR.'config';
    }
    public static function getDs() : string
    {
        return DIRECTORY_SEPARATOR;
    }
    public static function getRuntimePath() : string
    {
        return self::getRootPath().DIRECTORY_SEPARATOR.'runtime';
    }
    public static function getVendorPath() : string
    {
        return self::getRootPath().DIRECTORY_SEPARATOR.'vendor';
    }
    public static function filePutContents(string $filename,array $data) : void
    {
        file_put_contents($filename,"<?php\r\n return ".self::exportArray($data).';');
    }

    /**
     * 将数组导出为短数组语法格式
     */
    public static function exportArray(array $data, int $indent = 1): string
    {
        $isAssoc = array_keys($data) !== range(0, count($data) - 1);
        $spaces = str_repeat('    ', $indent);
        $spacesClose = str_repeat('    ', $indent - 1);
        $lines = [];

        foreach ($data as $key => $value) {
            $exportedValue = is_array($value) ? self::exportArray($value, $indent + 1) : var_export($value, true);
            if ($isAssoc) {
                $lines[] = $spaces . var_export($key, true) . ' => ' . $exportedValue . ',';
            } else {
                $lines[] = $spaces . $exportedValue . ',';
            }
        }

        return "[\n" . implode("\n", $lines) . "\n" . $spacesClose . ']';
    }
}