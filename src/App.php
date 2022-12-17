<?php
namespace xqkeji\composer;

class App
{
    use PathTrait;
    public static function addModule($name,$path) : void
    {
        $configFile=self::getRootPath().DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR.'composer.php';
        if(is_file($configFile))
        {
            $config=include($configFile);
        }
        else
        {
            $config=[];
        }
        $config[$name]=$path;
        self::filePutContents($configFile,$config);
        
    }
    public static function updateModule($name,$path) : void
    {
        self::addModule($name,$path);
    }
    public static function removeModule($name,$path) : void
    {
        $configFile=self::getRootPath().DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR.'composer.php';
        if(is_file($configFile))
        {
            $config=include($configFile);
            if(isset($config[$name]))
            {
                unset($config[$name]);
                self::filePutContents($configFile,$config);
            }
        }
    }
}