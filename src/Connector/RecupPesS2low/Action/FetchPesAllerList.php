<?php

declare(strict_types=1);

namespace Pastell\Connector\RecupPesS2low\Action;

use Pastell\Connector\RecupPesS2low\RecupPesS2lowConnector;
use Psr\Http\Client\ClientExceptionInterface;

final class FetchPesAllerList extends \ConnecteurTypeActionExecutor
{
    /**
     * @throws \Exception
     * @throws ClientExceptionInterface
     */
    public function go(): bool
    {
        /** @var RecupPesS2lowConnector $connector */
        $connector = $this->getMyConnecteur();

        $numberOfTransactions = 10;
        $list = $connector->listPes($numberOfTransactions);
        $message = \sprintf(
            'Transactions entre le %s et le %s<br />',
            $connector->getStartDate(),
            $connector->getEndDate(),
        );
        $message .= 'Prochaines transactions à être récupérées : <br /><ul>';
        foreach ($list->transactions as $transaction) {
            $message .= \sprintf(
                '<li> Transaction %s </li>',
                $transaction['id'],
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
