<?php

final class ExtensionTestAction extends \ActionExecutor
{
    public function go()
    {
        $this->setLastMessage('Action done');
        return true;
    }
}
