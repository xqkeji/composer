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
if(!defined('_XQ_ROOT_DIR'))
{
    define('_XQ_ROOT_DIR',dirname(__DIR__,4));
}


class Plugin implements PluginInterface, EventSubscriberInterface 
{
    protected $composer;
    protected $io;
    protected static $name=null;
    protected static $packages=null;
    protected static $commandName=null;
    
    const ROOT_DIR = _XQ_ROOT_DIR;
    const DS = DIRECTORY_SEPARATOR;
    const RUNTIME_DIR = self::ROOT_DIR.self::DS.'runtime';
    const VENDOR_DIR = self::ROOT_DIR.self::DS.'vendor';

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
        $package=$event->getOperation()->getPackage();
        $packageName = $package->getName();
        $path=self::VENDOR_DIR.self::DS.str_replace($packageName,'/',self::DS);
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
                                $params[]=self::ROOT_DIR.self::DS.$val;
                            }
                        }
                        call_user_func_array($cmd,$params);
                    }
                }
            }
            
        }

    }

}