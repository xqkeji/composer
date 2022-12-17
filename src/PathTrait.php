<?php
namespace xqkeji\composer;

trait PathTrait{
    public static function getRootPath() : string
    {
        return dirname(__DIR__,4);
    }
    public static function getRootConfigPath() : string
    {
        return dirname(__DIR__,4).DIRECTORY_SEPARATOR.'config';
    }
    public static function getDs() : string
    {
        return DIRECTORY_SEPARATOR;
    }
    public static function getRuntimePath() : string
    {
        return dirname(__DIR__,4).DIRECTORY_SEPARATOR.'runtime';
    }
    public static function getVendorPath() : string
    {
        return dirname(__DIR__,4).DIRECTORY_SEPARATOR.'vendor';
    }
    public static function filePutContents(string $filename,array $data) : void
    {
        file_put_contents($filename,"<?php\r\n return ".var_export($data,true).';');
    }
}