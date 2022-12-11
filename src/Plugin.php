<?php
namespace xqkeji\composer;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Plugin\PluginEvents;
use Composer\Script\ScriptEvents;
use Composer\Plugin\CommandEvent;
use Composer\Script\Event;
use Composer\Installer\PackageEvents;
use  Composer\Installer\PackageEvent;
if(!defined('CP_XQ_ROOT_DIR'))
{
    define('CP_XQ_ROOT_DIR',dirname(__DIR__,4));
    define('CP_XQ_DS',DIRECTORY_SEPARATOR);
    define('CP_XQ_RUNTIME_DIR',CP_XQ_ROOT_DIR.CP_XQ_DS.'runtime');
    define('CP_XQ_VENDOR_DIR',CP_XQ_ROOT_DIR.CP_XQ_DS.'vendor');
}


class Plugin implements PluginInterface, EventSubscriberInterface 
{
    protected $composer;
    protected $io;
    protected static $name=null;
    protected static $packages=null;
    protected static $commandName=null;

    public function activate(Composer $composer, IOInterface $io)
    {
        $this->composer = $composer;
        $this->io = $io;
        
    }
    public function deactivate(Composer $composer, IOInterface $io)
    {
        
    }
    public function uninstall(Composer $composer, IOInterface $io)
    {
    }
    public static function getSubscribedEvents()
    {
        return [
            PluginEvents::COMMAND => [
                ['command',0]
            ],
            PackageEvents::POST_PACKAGE_INSTALL =>[
                ['executePackage',0]
            ],
            PackageEvents::POST_PACKAGE_UPDATE =>[
                ['executePackage',0]
            ],
            PackageEvents::POST_PACKAGE_UNINSTALL =>[
                ['executePackage',0]
            ],
        ]; 
    }
    public function command(CommandEvent $event){
        self::$name=$event->getName();
        self::$commandName=$event->getCommandName();
        self::$packages=$event->getInput()->getArgument('packages');
    }
    public static function executePackage(PackageEvent $event){
        $operation=$event->getOperation();
        $type=$operation->getType();
        if($type=='update')
        {
            $package=$operation->getTargetPackage();
        }
        else
        {
            $package=$operation->getPackage();
        }
        
        $packageName = $package->getName();
        $path=CP_XQ_VENDOR_DIR.CP_XQ_DS.str_replace('/',CP_XQ_DS,$packageName);
        $name=basename($path);
        if(str_starts_with($name,'xq-app-'))
        {
            $extra=$package->getExtra();
            self::execute($extra,PackageEvents::POST_PACKAGE_INSTALL);
        }
        
    }
    
    public static function execute(array $extra,string $scriptName)
    {
        if(isset($extra[$scriptName]))
        {
            $data=$extra[$scriptName];
            if(!empty($data))
            {
                foreach($data as $execute)
                {
                    if(isset($execute['cmd']))
                    {
                        $cmd=$execute['cmd'];
                        $param=isset($execute['param'])?$execute['param']:null;
                        $params=[];
                        if(!empty($param))
                        {
                            foreach($param as $val)
                            {
                                if(strpos($val,'/')!==false)
                                {
                                    $params[]=CP_XQ_ROOT_DIR.CP_XQ_DS.str_replace('/',CP_XQ_DS,$val);
                                }
                                else
                                {
                                    $params[]=$val;
                                }
                            }
                        }
                        call_user_func_array($cmd,$params);
                    }
                }
            }
            
        }

    }

}