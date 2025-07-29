<?php

declare(strict_types=1);

use Symfony\Component\Serializer\Exception\ExceptionInterface;

class RecupFinParapheurGetFinishedFolders extends ActionExecutor
{
    /**
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
