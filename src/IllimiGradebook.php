<?php

namespace Illimi\Gradebook;

use Illimi\Gradebook\Managers\GradebookModuleManager;

class IllimiGradebook
{
    public function ping(): string
    {
        return 'illimi-gradebook installed';
    }

    public function moduleManager(): GradebookModuleManager
    {
        return new GradebookModuleManager();
    }

    public function menu(): array
    {
        return $this->moduleManager()->sideMenu();
    }
}
