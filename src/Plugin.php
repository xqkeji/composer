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
            self::processModule($moduleName,$packageName,$type);
            $extra=$package->getExtra();
            self::execute($extra,$eventName,$packageName);
        }
        $autoload=$package->getAutoload();
        self::processAutoLoad($packageName,$autoload,$type);
    }
    
    public static function execute(array $extra,string $scriptName,string $packageName='')
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
                        $className=$cmd[0];
                        //判断类是否存在，不存在得自己加载
                        if(!class_exists($className))
                        {
                            if(str_starts_with($className,"xqkeji\\app\\"))
                            {
                                $fileName=str_replace(["xqkeji\\app\\","\\"],['',DIRECTORY_SEPARATOR],$className);
                                $fileName=strstr($fileName,DIRECTORY_SEPARATOR).'.php';
                                $filePath=self::getVendorPath().DIRECTORY_SEPARATOR.str_replace('/',DIRECTORY_SEPARATOR,$packageName).DIRECTORY_SEPARATOR."src".$fileName;
                                if(is_file($filePath))
                                {
                                    include($filePath);
                                }
                                else
                                {
                                    throw new \Exception("the class \"$className\" not exists and the class filename \"$filePath\" not exists too!" , 500);
                                }
                            }
                            else
                            {
                                throw new \Exception("the class \"$className\" not exists and the class name not start with \"xqkeji\\app\\\" too!" ,500);
                            }
                        }
                        
                        $param=$execute['param'] ?? null;
                        $params=[];
                        if(!empty($param))
                        {
                            foreach($param as $val)
                            {
                                $params[]=self::processParams($val);
                            }
                        }
                        call_user_func_array($cmd,$params);
                    }
                }
            }
            
        }
    }
    public static function processParams(mixed $data) : mixed
    {
        if(is_string($data))
        {
            if(strpos($data,'/')!==false)
            {
                $data=self::getRootPath().DIRECTORY_SEPARATOR.str_replace('/',DIRECTORY_SEPARATOR,$data);
            }
        }
        elseif(is_array($data))
        {
            if(!empty($data))
            {
                $arr=[];
                foreach($data as $key=>$val)
                {
                    if(is_string($key))
                    {
                        if(strpos($key,'/')!==false)
                        {
                            $new_key=self::getRootPath().DIRECTORY_SEPARATOR.str_replace('/',DIRECTORY_SEPARATOR,$key);
                        }
                        else
                        {
                            $new_key=$key;
                        }
                    }
                    else
                    {
                        $new_key=$key;
                    }
                    if(is_string($val))
                    {
                        if(strpos($val,'/')!==false)
                        {
                            $new_val=self::getRootPath().DIRECTORY_SEPARATOR.str_replace('/',DIRECTORY_SEPARATOR,$val);
                        }
                        else
                        {
                            $new_val=$val;
                        }
                    }
                    else
                    {
                        $new_val=$val;
                    }
                    $arr[$new_key]=$new_val;
                }
                $data=$arr;
            }
        }
        return $data;
    }
    public static function processModule(string $moduleName,string $packageName,string $type)
    {
        if($type=='install')
        {
            App::addModule($moduleName,$packageName);
        }
        elseif($type=='uninstall')
        {
            App::removeModule($moduleName);
        }
        elseif($type=='update')
        {
            App::updateModule($moduleName,$packageName);
        }

    }
    public static function processAutoLoad(string $packageName,array $autoload,string $type)
    {
        if($type=='install')
        {
            AutoLoad::addLoad($packageName,$autoload);
        }
        elseif($type=='uninstall')
        {
            AutoLoad::removeLoad($autoload);
        }
        elseif($type=='update')
        {
            AutoLoad::updateLoad($packageName,$autoload);
        }

    }

}