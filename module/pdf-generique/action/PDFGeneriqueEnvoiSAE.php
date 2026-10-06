<?php

use Pastell\Service\Journal\JournalConsultationService;

class PDFGeneriqueEnvoiSAE extends ActionExecutor
{
    /**
     * @throws UnrecoverableException
     * @throws NotFoundException
     * @throws DonneesFormulaireException
     * @throws Exception
     */
    public function go()
    {
        $tmpFolder = $this->objectInstancier->getInstance(TmpFolder::class);
        $tmp_folder = $tmpFolder->create();

        try {
            $result = $this->goThrow($tmp_folder);
        } finally {
            $tmpFolder->delete($tmp_folder);
        }
        return $result;
    }

    /**
     * @throws DonneesFormulaireException
     * @throws UnrecoverableException
     * @throws NotFoundException
     * @throws Exception
     */
    public function goThrow($tmp_folder)
    {
        $this->getDonneesFormulaire()->setData("sae_show", true);

        $this->createJournal();

        /** @var SEDAConnecteur $sedaNG */
        $sedaNG = $this->getConnecteurOrFail('Bordereau SEDA');

        /** @var SAEConnecteur $sae */
        $sae = $this->getConnecteur('SAE');

        $fluxData = new FluxDataSedaPDFGenerique(
            $this->getDonneesFormulaire()
        );

        $metadata = json_decode($this->getDonneesFormulaire()->getFileContent('sae_config'), true) ?: [];
        $fluxData->setMetadata($metadata);

        $bordereau = $sedaNG->getBordereau($fluxData);
        $this->getDonneesFormulaire()->addFileFromData('sae_bordereau', "bordereau.xml", $bordereau);

        try {
            $sedaNG->validateBordereau($bordereau);
        } catch (Exception $e) {
            $message = $e->getMessage() . " : <br/><br/>";
            foreach ($sedaNG->getLastValidationError() as $erreur) {
                $message .= $erreur->message . "<br/>";
            }
            throw new Exception($message);
        }

        $archive_path = $tmp_folder . "/archive.tar.gz";
        $sedaNG->generateArchive($fluxData, $archive_path);

        $this->getDonneesFormulaire()->addFileFromData('sae_bordereau', "bordereau.xml", $bordereau);
        $this->getDonneesFormulaire()->addFileFromCopy('sae_archive', "archive.tar.gz", $archive_path);

        $result = $sae->sendSIP($bordereau, $archive_path);

        if (! $result) {
            $this->setLastMessage("L'envoi du bordereau a échoué : " . $sae->getLastError());
            return false;
        }

        $this->getDonneesFormulaire()->setData('sae_transfert_id', $result);
        $this->addActionOK("Le document a été envoyé au SAE");
        $this->notify($this->action, $this->type, "Le document a été envoyé au SAE");
        return true;
    }

    private function createJournal(): void
    {
        $journal = $this->objectInstancier->getInstance(JournalConsultationService::class)->getList($this->id_e, false, $this->id_d, 0, 0, 10000);
        foreach ($journal as $i => $journal_item) {
            $journal[$i]['preuve'] = base64_encode($journal[$i]['preuve']);
        }

        $date_journal_debut = $journal[count($journal) - 1]['date'];
        $date_cloture_journal = $journal[0]['date'];
        $journal = json_encode($journal);

        $this->getDonneesFormulaire()->addFileFromData('journal', 'journal.json', $journal);
        $this->getDonneesFormulaire()->setData('date_journal_debut', date("Y-m-d", strtotime($date_journal_debut)));
        $this->getDonneesFormulaire()->setData('date_cloture_journal', date("Y-m-d", strtotime($date_cloture_journal)));
        $this->getDonneesFormulaire()->setData('date_cloture_journal_iso8601', date('c', strtotime($date_cloture_journal)));
    }
}
