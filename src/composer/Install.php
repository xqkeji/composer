<?php
namespace xqkeji\app\composer;
use MongoDB\Driver\Manager;
use MongoDB\Driver\Command;
class Install
{
    public static function random(int $length=24):string
    {
        $text='';
        $pool = array_merge(
            range(0, 9),
            range("a", "z"),
            range("A", "Z")
        );
        $end = count($pool) - 1;

        while(strlen($text) < $length) 
        {
            $text .= $pool[mt_rand(0, $end)];
        }

        return $text;
    }
    public static function getRootPath():string
    {
        return dirname(__DIR__,2);
    }
    public static function getRootConfigPath():string
    {
        return dirname(__DIR__,2).DIRECTORY_SEPARATOR.'config';
    }
    public static function postInstall() : void
    {
        $configPath=self::getRootConfigPath();
        $containerFile=$configPath.DIRECTORY_SEPARATOR.'container.php';
        if(is_dir($configPath))
        {
            //文件不存在，则创建
            if(!is_file($containerFile))
            {
                while(true)
                {
                    $hostname = readline("请输入数据库服务器地址[默认为：localhost]\r\n");
                    if(trim($hostname)=='')
                    {
                        $hostname='localhost';
                    }
                    $hostport = readline("请输入数据库服务器的端口号[默认为：27017]\r\n");
                    if(trim($hostport)=='')
                    {
                        $hostport='27017';
                    }
                    $database = readline("请输入数据库名称[默认为：xqkeji_db]\r\n");
                    if(trim($database)=='')
                    {
                        $database='xqkeji_db';
                    }
                    $username = readline("请输入数据库用户名[默认为：空]\r\n");
                    if(trim($username)=='')
                    {
                        $username='';
                    }
                    $password = readline("请输入数据库密码[默认为：空]\r\n");
                    if(trim($password)=='')
                    {
                        $password='';
                    }
                    if(!empty($username))
                    {
                        $uri='mongodb://'.$username.':'.$password.'@'.$hostname.':'.$hostport;
                    }
                    else
                    {
                        $uri='mongodb://'.$hostname.':'.$hostport;
                    }
                    $manager = new Manager($uri,['serverSelectionTryOnce'=>false,'serverSelectionTimeoutMS'=>500,'connectTimeoutMS'=>500]);
                    $command = new Command(['ping' => 1]);
                    try {
                        echo "正在检测数据库链接信息......\r\n";
                        $manager->executeCommand($database, $command);
                        break;   
                    } catch(\Exception $e) {
                        echo "数据库链接信息无法连接数据库，请重新设置！\r\n";
                    }
                }
                echo "数据库链接信息检测成功！\r\n";

                $key=self::random();
                $config=[
                    'db'  => [
                        'hostname'		=> $hostname,
                        'hostport'		=> $hostport,
                        'database'		=> $database,
                        'username' 		=> $username,
                        'password'      => $password,
                    ],
                    'crypt'     =>[
                        'key'=>$key,
                    ],
                ];
                file_put_contents($containerFile,"<?php\r\n return ".var_export($config,true).';');
            }
            
        }
        else
        {
            throw new \Exception("the config directory not exists: \"$configPath\"!" , 404);
        }
        $root_path=self::getRootPath();
        $runtime_path=$root_path.DIRECTORY_SEPARATOR.'runtime';
        $upload_path=$root_path.DIRECTORY_SEPARATOR.'www'.DIRECTORY_SEPARATOR.'upload';
        $assets_path=$root_path.DIRECTORY_SEPARATOR.'www'.DIRECTORY_SEPARATOR.'assets';
        chmod($runtime_path,0777);
        chmod($upload_path,0777);
        chmod($assets_path,0777);
    }
    
}