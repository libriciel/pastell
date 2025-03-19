<?php

declare(strict_types=1);

namespace Pastell\Connector\IparapheurRest\Action;

use ConnecteurTypeActionExecutor;
use Exception;
use IparapheurRest;

final class IpRestTestConnexion extends ConnecteurTypeActionExecutor
{
    /**
     * @throws Exception
     */
    public function go(): bool
    {
        /** @var IparapheurRest $connector */
        $connector = $this->getMyConnecteur();
        $message = $connector->testConnexion();
        $this->setLastMessage($message);
        return true;
    }
}
