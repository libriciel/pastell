<?php

declare(strict_types=1);

class HeliosRecupPESRetour extends ActionExecutor
{
    /**
     * @throws NotFoundException
     * @throws S2lowException
     * @throws UnrecoverableException
     */
    public function go(): bool
    {
        /** @var S2low $tdT */
        $tdT = $this->getConnecteur('TdT');

        $id_retour = $this->getDonneesFormulaire()->get('id_retour');

        if (!$id_retour) {
            $this->setLastMessage("Le document ne dispose pas d'identifiant id_retour");
            return false;
        }
        $tdT->getPESRetourLu($this->getDonneesFormulaire());
        $this->addActionOK('Le fichier PES Retour a été importé à nouveau');
        $this->setLastMessage('Le fichier PES Retour a été importé à nouveau');
        return true;
    }
}
