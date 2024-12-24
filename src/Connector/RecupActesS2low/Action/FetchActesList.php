<?php

declare(strict_types=1);

namespace Pastell\Connector\RecupActesS2low\Action;

use Pastell\Connector\RecupActesS2low\RecupActesS2lowConnector;

final class FetchActesList extends \ConnecteurTypeActionExecutor
{
    /**
     * @throws \Exception
     */
    public function go()
    {
        /** @var RecupActesS2lowConnector $connector */
        $connector = $this->getMyConnecteur();

        $list = $connector->listActes(10);
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
        $message .= '<li>…</li></ul>';

        $this->setLastMessage($message);
        return true;
    }
}
