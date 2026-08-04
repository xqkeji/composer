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
        file_put_contents($filename,"<?php\r\n return ".var_export($data,true).';');
    }
}