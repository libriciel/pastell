<?php

class FatalError extends ActionExecutor
{
    public const ACTION_ID = 'fatal-error';

    public function go()
    {
        $message = 'Le document est en erreur fatale.';
        $this->addActionOK($message);
        $this->notify(ActionPossible::FATAL_ERROR_ACTION, $this->type, $message);
        return true;
    }
}
