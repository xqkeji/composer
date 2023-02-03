<?php
namespace xqkeji\composer;

class App
{
    use PathTrait;
    public static function addModule($name,string $packageName) : void
    {
        $configPath=self::getRootConfigPath();
        $configFile=$configPath.DIRECTORY_SEPARATOR.'composer.php';
        if(is_dir($configPath))
        {
            if(is_file($configFile))
            {
                $config=include($configFile);
            }
            else
            {
                $config=[];
            }
            $config[$name]=$packageName;
            self::filePutContents($configFile,$config);
        }
    }
    public static function updateModule($name,string $packageName) : void
    {
        self::addModule($name,$packageName);
    }
    public static function removeModule($name) : void
    {
        $configPath=self::getRootConfigPath();
        $configFile=$configPath.DIRECTORY_SEPARATOR.'composer.php';
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