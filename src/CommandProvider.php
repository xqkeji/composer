<?php
namespace xqkeji\composer;

use Composer\Plugin\Capability\CommandProvider as CommandProviderCapability;
use xqkeji\composer\command\ModuleCommand;
use xqkeji\composer\command\ControllerCommand;
use xqkeji\composer\command\UseCommand;
use xqkeji\composer\command\ActionCommand;
use xqkeji\composer\command\RemoveCommand;
use xqkeji\composer\command\ModelCommand;
use xqkeji\composer\command\FormCommand;
use xqkeji\composer\command\TableCommand;
use xqkeji\composer\command\ElementCommand;

class CommandProvider implements CommandProviderCapability
{
    public function getCommands()
    {
        return [
            new ModuleCommand(),
            new ControllerCommand(),
            new UseCommand(),
            new ActionCommand(),
            new RemoveCommand(),
            new ModelCommand(),
            new FormCommand(),
            new TableCommand(),
            new ElementCommand(),
        ];
    }
}
