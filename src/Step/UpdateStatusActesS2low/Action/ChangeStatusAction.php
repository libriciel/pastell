<?php

declare(strict_types=1);

namespace Pastell\Step\UpdateStatusActesS2low\Action;

use Pastell\Client\S2low\S2lowClientException;
use Pastell\Connector\RecupActesS2low\RecupActesS2lowConnector;
use Psr\Http\Client\ClientExceptionInterface;

final class ChangeStatusAction extends \ConnecteurTypeActionExecutor
{
    /**
     * @throws \UnrecoverableException
     * @throws ClientExceptionInterface
     * @throws \NotFoundException
     * @throws S2lowClientException
     */
    public function go(): bool
    {
        $statusField = $this->getMappingValue('status');
        $transactionField = $this->getMappingValue('transaction');

        /** @var RecupActesS2lowConnector $connector */
        $connector = $this->getConnecteur('recup-actes-s2low');

        $connector->changeStatus(
            $this->getDonneesFormulaire()->get($transactionField),
            $this->getDonneesFormulaire()->get($statusField),
        );

        $message = 'Le statut de la transaction a été modifié';

        $this->addActionOK($message);

        return true;
    }
}
