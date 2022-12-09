<?php
namespace xqkeji\composer;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Plugin\PluginEvents;
use Composer\Script\ScriptEvents;

class AssetPlugin implements PluginInterface, EventSubscriberInterface
{
    protected $composer;
    protected $io;

    public function activate(Composer $composer, IOInterface $io)
    {
        $this->composer = $composer;
        $this->io = $io;
        
    }

    public static function getSubscribedEvents()
    {
        return array(
            ScriptEvents::POST_INSTALL_CMD => array(
                array('excute', 0)
            ),
        );
    }

    public static function excute(Event $event){
        $t=$this->composer;
        var_export($t);
        $name=$event->getName();
        $composer=$event->getComposer();
        var_export($composer);
        exit;
        $cmds=$composer->getConfig()->get('xq-cmds');
        if(!empty($cmds))
        {
            if(isset($cmds[$name]))
            {
                $data=$cmds[$name];
                foreach($data as $excute){
                    if(isset($excute['cmd']))
                    {
                        $cmd=$excute['cmd'];
                        $param=isset($excute['param'])?$excute['param']:null;
                        $params=[];
                        if(!empty($param))
                        {
                            foreach($param as $val)
                            {
                                $params[]=XQ_COMPOSER_ROOT_DIR.DIRECTORY_SEPARATOR.$val;
                            }
                        }
                        call_user_func_array([get_called_class(),$cmd],$params);
                    }
                }
            }
        }
        
    }
    public static function createDir($path, $mode = 0775, $recursive = true)
    {
        if (is_dir($path)) {
            return true;
        }
        $parentDir = dirname($path);
        if ($recursive && !is_dir($parentDir) && $parentDir !== $path) {
            static::createDir($parentDir, $mode, true);
        }
        try {
            if (!mkdir($path, $mode)) {
                return false;
            }
        } catch (\Exception $e) {
            if (!is_dir($path)) {
                throw new \Exception("Failed to create directory \"$path\": " . $e->getMessage(), $e->getCode());
            }
        }
        try {
            return chmod($path, $mode);
        } catch (\Exception $e) {
            throw new \Exception("Failed to change permissions for directory \"$path\": " . $e->getMessage(), $e->getCode());
        }
    }
    public static function copyDir($src, $dst)
    {
        $dstExists = is_dir($dst);
        if (!$dstExists) {
            self::createDir($dst);
        }

        $handle = opendir($src);
        if ($handle === false) {
            throw new \InvalidArgumentException("Unable to open directory: $src");
        }
        while (($file = readdir($handle)) !== false) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            $from = $src . DIRECTORY_SEPARATOR . $file;
            $to = $dst . DIRECTORY_SEPARATOR . $file;
            if (is_file($from)) {
                copy($from, $to);
                @chmod($to, 0775);
                
            } else {
                static::copyDir($from, $to);
            }
        }
        closedir($handle);
    }
    public static function rmDir($dir)
    {
        if (!is_dir($dir)) {
            return;
        }
        if (!($handle = opendir($dir))) {
            return;
        }
        while (($file = readdir($handle)) !== false) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            if (is_dir($path)) {
                static::rmDir($path);
            } else {
                static::unlink($path);
            }
        }
        closedir($handle);
        if (is_link($dir)) {
            static::unlink($dir);
        } else {
            rmdir($dir);
        }
    }
    public static function unlink($path)
    {
        $isWindows = DIRECTORY_SEPARATOR === '\\';

        if (!$isWindows) {
            return unlink($path);
        }

        if (is_link($path) && is_dir($path)) {
            return rmdir($path);
        }

        try {
            return unlink($path);
        } catch (\ErrorException $e) {
            return false;
        }
    }
}
