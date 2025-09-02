<?php

class SignatureRemord extends ConnecteurTypeActionExecutor
{
    /**
     * @return bool
     * @throws NotFoundException
     * @throws RecoverableException
     * @throws UnrecoverableException
     */
    public function go(): bool
    {
        /** @var SignatureConnecteur $signature */
        $signature = $this->getConnecteurOrFail('signature');

        $donneesFormulaire = $this->getDonneesFormulaire();

        $iparapheur_dossier_id = $this->getMappingValue('iparapheur_dossier_id');

        $dossierID = $donneesFormulaire->get($iparapheur_dossier_id);

        $result = $signature->exercerDroitRemordDossier($dossierID);
        if (!$result) {
            $this->setLastMessage("La connexion avec le parapheur a échoué : " . $signature->getLastError());
            return false;
        }
        if (!$signature->archiver($dossierID)) {
            throw new RecoverableException(
                "Impossible d'archiver la transaction sur le parapheur : " . $signature->getLastError()
            );
        }

        $this->addActionOK("Le droit de remord a été exercé sur le dossier");
        $this->notify($this->action, $this->type, "Le droit de remord a été exercé sur le dossier");
        return true;
    }
}
