<?php

declare(strict_types=1);

namespace Pastell\Connector\IparapheurRest\Action;

use ConnecteurTypeActionExecutor;
use Exception;
use Pastell\Connector\IparapheurRest\IparapheurRestConnector;

final class TestConnexion extends ConnecteurTypeActionExecutor
{
    /**
     * @throws Exception
     */
    public function go(): bool
    {
        /** @var IparapheurRestConnector $connector */
        $connector = $this->getMyConnecteur();
        $message = $connector->testConnexion();
        $this->setLastMessage($message);
        return true;
    }
}
