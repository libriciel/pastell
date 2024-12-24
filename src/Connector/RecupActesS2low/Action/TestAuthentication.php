<?php

declare(strict_types=1);

namespace Pastell\Connector\RecupActesS2low\Action;

use Pastell\Client\S2low\S2lowClientException;
use Pastell\Connector\RecupActesS2low\RecupActesS2lowConnector;
use Psr\Http\Client\ClientExceptionInterface;

final class TestAuthentication extends \ConnecteurTypeActionExecutor
{
    /**
     * @throws S2lowClientException
     * @throws ClientExceptionInterface
     * @throws \Exception
     */
    public function go()
    {
        /** @var RecupActesS2lowConnector $connector */
        $connector = $this->getMyConnecteur();
        $connector->testAuth();
        $this->setLastMessage('La connexion est réussie');
        return true;
    }
}
