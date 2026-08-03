<?php
namespace xqkeji\composer;

use Composer\Plugin\Capability\CommandProvider as CommandProviderCapability;
use xqkeji\composer\command\ModuleCommand;
use xqkeji\composer\command\ControllerCommand;
use xqkeji\composer\command\UseCommand;

class CommandProvider implements CommandProviderCapability
{
    public function getCommands()
    {
        return [
            new ModuleCommand(),
            new ControllerCommand(),
            new UseCommand(),
        ];
    }
}
