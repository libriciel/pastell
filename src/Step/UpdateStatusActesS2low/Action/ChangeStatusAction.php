<?php

declare(strict_types=1);

namespace Pastell\Step\UpdateStatusActesS2low\Action;

use ConnecteurTypeActionExecutor;
use NotFoundException;
use Pastell\Client\S2low\S2lowClientException;
use Pastell\Connector\RecupActesS2low\RecupActesS2lowConnector;
use Psr\Http\Client\ClientExceptionInterface;
use UnrecoverableException;

final class ChangeStatusAction extends ConnecteurTypeActionExecutor
{
    /**
     * @throws UnrecoverableException
     * @throws ClientExceptionInterface
     * @throws NotFoundException
     */
    public function go(): bool
    {
        $statusField = $this->getMappingValue('status');
        $transactionField = $this->getMappingValue('transaction');
        $changeStatutErrorState = $this->getMappingValue('change-status-error');

        /** @var RecupActesS2lowConnector $connector */
        $connector = $this->getConnecteur('recup-actes-s2low');

        try {
            $connector->changeStatus(
                $this->getDonneesFormulaire()->get($transactionField),
                $this->getDonneesFormulaire()->get($statusField),
            );
        } catch (S2lowClientException $e) {
            $this->changeAction($changeStatutErrorState, $e->getMessage());
            $this->notify($changeStatutErrorState, $this->type, $e->getMessage());
            return false;
        }

        $message = 'Le statut de la transaction a été modifié';
        $this->addActionOK($message);
        return true;
    }
}
