<?php
namespace xqkeji\composer;
if(!defined('_XQ_ROOT_DIR'))
{
    define('_XQ_ROOT_DIR',dirname(__DIR__,4));
}
class App
{
    const ROOT_DIR = _XQ_ROOT_DIR;
    const DS = DIRECTORY_SEPARATOR;
    public static function addModule($name,$path)
    {
        $configFile=self::ROOT_DIR.self::DS.'config'.self::DS.'composer.php';
        if(is_file($configFile))
        {
            $config=include($configFile);
        }
        else
        {
            $config=[];
        }
        $config[$name]=$path;
        file_put_contents($configFile,'<?php\n return '.var_export($config,true));
    }
    public static function removeModule($name)
    {
        $configFile=self::ROOT_DIR.self::DS.'config'.self::DS.'composer.php';
        if(is_file($configFile))
        {
            $config=include($configFile);
            unset($config[$name]);
            file_put_contents($configFile,'<?php\n return '.var_export($config,true));
        }
    }
}