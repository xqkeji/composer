<?php
namespace xqkeji\composer;
if(!defined('CP_XQ_ROOT_DIR'))
{
    define('CP_XQ_ROOT_DIR',dirname(__DIR__,4));
    define('CP_XQ_DS',DIRECTORY_SEPARATOR);
    define('CP_XQ_RUNTIME_DIR',CP_XQ_ROOT_DIR.CP_XQ_DS.'runtime');
    define('CP_XQ_VENDOR_DIR',CP_XQ_ROOT_DIR.CP_XQ_DS.'vendor');
}
class App
{
    public static function addModule($name,$path)
    {
        $configFile=CP_XQ_ROOT_DIR.CP_XQ_DS.'config'.CP_XQ_DS.'composer.php';
        if(is_file($configFile))
        {
            $config=include($configFile);
        }
        else
        {
            $config=[];
        }
        $config[$name]=$path;
        file_put_contents($configFile,"<?php\r\n return ".var_export($config,true).';');
    }
    public static function removeModule($name)
    {
        $configFile=CP_XQ_ROOT_DIR.CP_XQ_DS.'config'.CP_XQ_DS.'composer.php';
        if(is_file($configFile))
        {
            $config=include($configFile);
            unset($config[$name]);
            file_put_contents($configFile,"<?php\r\n return ".var_export($config,true).';');
        }
    }
}