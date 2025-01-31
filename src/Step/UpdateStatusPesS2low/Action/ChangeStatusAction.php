<?php

declare(strict_types=1);

namespace Pastell\Step\UpdateStatusPesS2low\Action;

use Pastell\Client\S2low\S2lowClientException;
use Pastell\Connector\RecupPesS2low\RecupPesS2lowConnector;
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

        /** @var RecupPesS2lowConnector $connector */
        $connector = $this->getConnecteur('recup-pes-s2low');

        $connector->changeStatus(
            $this->getDonneesFormulaire()->get($transactionField),
            $this->getDonneesFormulaire()->get($statusField),
        );

        $message = 'Le statut de la transaction a été modifié';

        $this->addActionOK($message);

        return true;
    }
}
