<?php

declare(strict_types=1);

use IparapheurV5Client\Exception\IparapheurV5Exception;
use Symfony\Component\Serializer\Exception\ExceptionInterface;

class RecupFinParapheurGetFinishedFolders extends ActionExecutor
{
    /**
     * @throws ExceptionInterface
     * @throws \Http\Client\Exception
     * @throws IparapheurV5Exception
     * @throws Exception
     */
    public function go(): bool
    {
        /** @var RecupFinParapheur $recupParapheur */
        $recupParapheur = $this->getMyConnecteur();
        $listDossier = $recupParapheur->getFinishedFolders();

        $message = 'Nombre de dossiers : ' . count($listDossier) . '<br/><ul>';
        foreach ($listDossier as $dossierId => $dossierName) {
            $message .= "<li>$dossierName ($dossierId)</li>";
        }
        $message .= '</ul>';

        $this->setLastMessage($message);
        return true;
    }
}
