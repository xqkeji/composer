<?php
namespace xqkeji\composer;

class AutoLoad
{
    use PathTrait;
    public static function addLoad(string $packageName,array $autoload)
    {
        $configPath=self::getRootConfigPath();
        $configFile=$configPath.DIRECTORY_SEPARATOR.'loader.php';
        if(is_dir($configPath))
        {
            if(is_file($configFile))
            {
                $data=include($configFile);
            }
            else
            {
                $data=[];
            }
            
            if(isset($autoload['psr-4']))
            {
                self::processAdd($configFile,$data,$packageName,$autoload,'psr-4');
            }
            elseif(isset($autoload['psr-0']))
            {
                self::processAdd($configFile,$data,$packageName,$autoload,'psr-0');
            }
        }
        
    }
    public static function removeLoad(array $autoload)
    {
        $configPath=self::getRootConfigPath();
        $configFile=$configPath.DIRECTORY_SEPARATOR.'loader.php';
        if(is_dir($configPath))
        {
            if(is_file($configFile))
            {
                $data=include($configFile);
                if(isset($autoload['psr-4']))
                {
                    self::processRemove($configFile,$data,$autoload,'psr-4');
                }
                elseif(isset($autoload['psr-0']))
                {
                    self::processRemove($configFile,$data,$autoload,'psr-0');
                }
            }
        }
    }
    public static function updateLoad(string $packageName,array $autoload)
    {
        $configPath=self::getRootConfigPath();
        $configFile=$configPath.DIRECTORY_SEPARATOR.'loader.php';
        if(is_dir($configPath))
        {
            if(is_file($configFile))
            {
                $data=include($configFile);
            }
            else
            {
                $data=[];
            }

            if(isset($autoload['psr-4']))
            {
                self::processRemove($configFile,$data,$autoload,'psr-4');
                $data=include($configFile);
                self::processAdd($configFile,$data,$packageName,$autoload,'psr-4');
            }
            elseif(isset($autoload['psr-0']))
            {
                self::processRemove($configFile,$data,$autoload,'psr-0');
                $data=include($configFile);
                self::processAdd($configFile,$data,$packageName,$autoload,'psr-0');
            }
        }
    }
    private static function processAdd(string $configFile,array $data,string $packageName,array $autoload,string $type) : void
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
                if(empty($val))
                {
                    continue;
                }
                if(is_array($val))
                {
                    if(isset($psrData[$key]))
                    {
                        $arr=$psrData[$key];
                        foreach($val as $v)
                        {
                            if(!in_array($v,$arr))
                            {
                                $psrData[$key][]=$packageName.'/'.$v;
                            }
                            else
                            {
                                $psrData[$key][]=$packageName.'/'.$v;
                            }
                        }
                    }
                    else
                    {
                        $psrData[$key]=$packageName.'/'.$val;
                    }
                }
                elseif(is_string($val))
                {
                    if(isset($psrData[$key]))
                    {
                        $arr=$psrData[$key];
                        if(!in_array($val,$arr))
                        {
                            $psrData[$key][]=$packageName.'/'.$val;
                        }
                    }
                    else
                    {
                        $psrData[$key]=[$packageName.'/'.$val];
                    }
                }
            }
        }
        $data[$type]=$psrData;
        self::filePutContents($configFile,$data);
    }
    private static function processRemove(string $configFile,array $data,array $autoload,string $type) : void
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