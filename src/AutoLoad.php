<?php
namespace xqkeji\composer;

class AutoLoad
{
    use PathTrait;
    public static function addLoad(array $autoload)
    {
        $rootPath=self::getRootPath();
        $configFile=$rootPath.DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR.'loader.php';
        $data=include($configFile);
        if(isset($autoload['psr-4']))
        {
            self::processAdd($data,$autoload,'psr-4');
        }
        elseif(isset($autoload['psr-0']))
        {
            self::processAdd($data,$autoload,'psr-0');
        }
    }
    public static function removeLoad(array $autoload)
    {
        $rootPath=self::getRootPath();
        $configFile=$rootPath.DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR.'loader.php';
        $data=include($configFile);
        if(isset($autoload['psr-4']))
        {
            self::processRemove($data,$autoload,'psr-4');
        }
        elseif(isset($autoload['psr-0']))
        {
            self::processRemove($data,$autoload,'psr-0');
        }
    }
    public static function updateLoad(array $autoload)
    {
        $rootPath=self::getRootPath();
        $configFile=$rootPath.DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR.'loader.php';
        $data=include($configFile);
        if(isset($autoload['psr-4']))
        {
            self::processRemove($data,$autoload,'psr-4');
            self::processAdd($data,$autoload,'psr-4');
        }
        elseif(isset($autoload['psr-0']))
        {
            self::processRemove($data,$autoload,'psr-0');
            self::processAdd($data,$autoload,'psr-0');
        }
    }
    private static function processAdd(array $data,array $autoload,string $type) : void
    {
        $psr=$autoload[$type];//composer包里自动加载的配置数据
        if(isset($data[$type]))
        {
            $psrData=$data[$type];//项目配置里的自动加载数据
        }
        else
        {
            $psrData=[];
        }

        if(!empty($psr)&&is_array($psr))
        {
            
            foreach($psr as $key=>$val)
            {
                if(is_array($val))
                {
                    if(isset($psrData[$key]))
                    {
                        $arr=$psrData[$key];
                        foreach($val as $v)
                        {
                            if(!in_array($v,$arr))
                            {
                                $psrData[$key][]=$v;
                            }
                            else
                            {
                                $psrData[$key][]=$v;
                            }
                        }
                    }
                    else
                    {
                        $psrData[$key]=$val;
                    }
                }
                elseif(is_string($val))
                {
                    if(isset($psrData[$key]))
                    {
                        $arr=$psrData[$key];
                        if(!in_array($val,$arr))
                        {
                            $psrData[$key][]=$val;
                        }
                    }
                    else
                    {
                        $psrData[$key]=[$val];
                    }
                }
            }
        }
        $data[$type]=$psrData;
        self::filePutContents($configFile,$data);
    }
    private static function processRemove(array $data,array $autoload,string $type) : void
    {
        $psr=$autoload[$type];//composer包里自动加载的配置数据
        if(isset($data[$type]))
        {
            $psrData=$data[$type];//项目配置里的自动加载数据
        }
        else
        {
            $psrData=[];
        }

        if(!empty($psr)&&is_array($psr))
        {
            foreach($psr as $key=>$val)
            {
                unset($psrData[$key]);
            }
        }
        $data[$type]=$psrData;
        self::filePutContents($configFile,$data);
    }
    
}