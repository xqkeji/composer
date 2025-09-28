<?php
namespace xqkeji\composer;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Script\Event;
use Composer\Util\Filesystem;

class Gen implements EventSubscriberInterface
{
    private IOInterface $io;
    private Composer $composer;
    private Filesystem $filesystem;

    public function __construct(IOInterface $io, Composer $composer)
    {
        $this->io = $io;
        $this->composer = $composer;
        $this->filesystem = new Filesystem();
    }

    /**
     * 注册要监听的事件
     */
    public static function getSubscribedEvents(): array
    {
        return [
            // 自定义事件
            'xqkeji:gen' => 'gen',
        ];
    }
 
    /**
     * 自定义代码生成器
     */
    public function gen(Event $event): void
    {
		$cwd=getcwd();
        $this->io->write('<info>当前项目路径</info>');

        $this->io->write("当前目录: {$cwd}");
		$this->io->write("当前目录: {__DIR__}");
    }

}
    