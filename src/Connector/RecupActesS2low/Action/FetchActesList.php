<?php

declare(strict_types=1);

namespace Pastell\Connector\RecupActesS2low\Action;

use Pastell\Connector\RecupActesS2low\RecupActesS2lowConnector;
use Psr\Http\Client\ClientExceptionInterface;

final class FetchActesList extends \ConnecteurTypeActionExecutor
{
    /**
     * @throws \Exception
     * @throws ClientExceptionInterface
     */
    public function go(): bool
    {
        /** @var RecupActesS2lowConnector $connector */
        $connector = $this->getMyConnecteur();

        $numberOfTransactions = 10;
        $list = $connector->listActes($numberOfTransactions);
        $message = \sprintf(
            'Transactions entre le %s et le %s<br />',
            $connector->getStartDate(),
            $connector->getEndDate(),
        );
        $message .= 'Prochaines transactions à être récupérées : <br /><ul>';
        foreach ($list->transactions as $transaction) {
            $message .= \sprintf(
                '<li> Transaction %s - Acte %s</li>',
                $transaction->id,
                $transaction->number,
            );
        }
        if (\count($list->transactions) >= $numberOfTransactions) {
            $message .= '<li>…</li>';
        }
        $message .= '</ul>';

        $this->setLastMessage($message);
        return true;
    }
}
