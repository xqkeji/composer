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
use Composer\Installer\PackageEvent;

use xqkeji\composer\AutoLoad;
use xqkeji\composer\App;
use xqkeji\composer\Asset;

class Plugin implements PluginInterface, EventSubscriberInterface 
{
    use PathTrait;
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
        $eventName=$event->getName();
        $operation=$event->getOperation();
        $type=$operation->getOperationType();
        if($type=='update')
        {
            $package=$operation->getTargetPackage();
        }
        else
        {
            $package=$operation->getPackage();
        }
        
        $packageName = $package->getName();
        $path=self::getVendorPath().DIRECTORY_SEPARATOR.str_replace('/',DIRECTORY_SEPARATOR,$packageName);
        $name=basename($path);
        if(str_starts_with($name,'xq-app-'))
        {
            $moduleName=str_replace('xq-app-','',$name);
            $ns='xqkeji\\app\\'.$moduleName;
            self::processModule($ns,$path.DIRECTORY_SEPARATOR.'src',$type);
            $extra=$package->getExtra();
            self::execute($extra,$eventName);
        }
        $autoload=$package->getAutoload();
        self::processAutoLoad($autoload,$type);
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
                                    $params[]=self::getRootPath().DIRECTORY_SEPARATOR.str_replace('/',DIRECTORY_SEPARATOR,$val);
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
    public static function processModule(string $moduleName,string $path,string $type)
    {
        if($type=='install')
        {
            App::addModule($moduleName,$path);
        }
        elseif($type=='uninstall')
        {
            App::removeModule($moduleName,$path);
        }
        elseif($type=='update')
        {
            App::updateModule($moduleName,$path);
        }

    }
    public static function processAutoLoad(array $autoload,string $type)
    {
        if($type=='install')
        {
            AutoLoad::addLoad($autoload,$type);
        }
        elseif($type=='uninstall')
        {
            AutoLoad::removeLoad($autoload,$type);
        }
        elseif($type=='update')
        {
            AutoLoad::updateLoad($autoload,$type);
        }

    }

}