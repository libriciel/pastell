<?php

class TdTRestamp extends ConnecteurTypeActionExecutor
{
    /**
     * @return bool
     * @throws Exception
     */
    public function go()
    {
        $tedetis_transaction_id_element = $this->getMappingValue('tedetis_transaction_id');
        $tdt_error = $this->getMappingValue('tdt-error');
        $arrete_element = $this->getMappingValue('arrete');
        $acte_tamponne_element = $this->getMappingValue('acte_tamponne');
        $autre_document_attache_element = $this->getMappingValue('autre_document_attache');
        $annexes_tamponnees_element = $this->getMappingValue('annexes_tamponnees');
        $acte_publication_date = $this->getMappingValue('acte_publication_date');

        /** @var TdtConnecteur $tdT */
        $tdT = $this->getConnecteurOrFail('Tdt');

        $tedetis_transaction_id = $this->getDonneesFormulaire()->get($tedetis_transaction_id_element);

        if (! $tedetis_transaction_id) {
            $message = "Une erreur est survenue lors de l'envoi à " . $tdT->getLogicielName() . ' (tedetis_transaction_id non disponible)';
            $this->changeOrUpdateAction($tdt_error, $message);
            $this->notify($tdt_error, $this->type, $message);
            return false;
        }

        $donneesFormulaire = $this->getDonneesFormulaire();
        $date_publication = $donneesFormulaire->get($acte_publication_date);


        $actes_tamponne = $tdT->getActeTamponne($tedetis_transaction_id, $date_publication);
        $annexes_tamponnees_list = $tdT->getAnnexesTamponnees($tedetis_transaction_id, $date_publication);

        $donneesFormulaire = $this->getDonneesFormulaire();

        if ($actes_tamponne) {
            $actes_original_filename = $donneesFormulaire->getFileNameWithoutExtension($arrete_element);
            $donneesFormulaire->addFileFromData($acte_tamponne_element, $actes_original_filename . '-tampon.pdf', $actes_tamponne);
        }
        if ($annexes_tamponnees_list) {
            $file_number = 0;
            foreach ($annexes_tamponnees_list as $i => $annexe_tamponnee) {
                if (empty($annexe_tamponnee)) {
                    continue;
                }
                $annexe_filename_send = $tdT->getFilenameTransformation($this->getDonneesFormulaire()->getFileName($autre_document_attache_element, $i));
                if (strcmp($annexe_filename_send, $annexe_tamponnee['filename']) !== 0) {
                    $message = 'Une erreur est survenue lors de la récupération des annexes tamponnées de ' . $tdT->getLogicielName() .
                        " L'annexe tamponée " . $annexe_tamponnee['filename'] . ' ne correspond pas avec ' . $annexe_filename_send;
                    $this->changeOrUpdateAction($tdt_error, $message);
                    $this->notify($tdt_error, $this->type, $message);
                    return false;
                }
                $annexe_filename = $donneesFormulaire->getFileNameWithoutExtension($autre_document_attache_element, $i);
                $donneesFormulaire->addFileFromData(
                    $annexes_tamponnees_element,
                    $annexe_filename . '-tampon.pdf',
                    $annexe_tamponnee['content'],
                    $file_number++
                );
            }
        }

        $this->setLastMessage("L'acte et les annexes ont été re-tamponnés");
        return true;
    }

    public function goLot(array $all_id_d)
    {
        foreach ($all_id_d as $id_d) {
            $donneesFormulaire = $this->getDonneesFormulaireFactory()->get($id_d);
            if (! $donneesFormulaire->get('acte_publication_date')) {
                $donneesFormulaire->setData('acte_publication_date', date('Y-m-d'));
            }
        }
        parent::goLot($all_id_d);
    }
}
