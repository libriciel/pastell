<?php

declare(strict_types=1);

namespace Pastell\Connector\RecupActesS2low\Action;

use Pastell\Client\S2low\S2lowClientException;
use Pastell\Connector\RecupActesS2low\RecupActesS2lowConnector;
use Psr\Http\Client\ClientExceptionInterface;

final class FetchActes extends \ConnecteurTypeActionExecutor
{
    /**
     * @throws \UnrecoverableException
     * @throws ClientExceptionInterface
     * @throws \NotFoundException
     * @throws S2lowClientException
     * @throws \JsonException
     * @throws \Exception
     */
    public function go(): bool
    {
        /** @var RecupActesS2lowConnector $connector */
        $connector = $this->getMyConnecteur();

        $results = $connector->fetchActes();

        $message = '<ul>';

        foreach ($results as $result) {
            $message .= "<li>$result</li>";
        }
        $message .= '</ul>';

        $this->setLastMessage($message);
        return true;
    }
}
