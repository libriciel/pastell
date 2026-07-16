<?php

use Pastell\Step\AnnexeList;

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
            $annexes_envoyees = AnnexeList::getAll(
                $this->getMappingValueList(AnnexeList::MAPPING_KEY),
                $this->getDonneesFormulaire()
            );
            $file_number = 0;
            foreach ($annexes_tamponnees_list as $i => $annexe_tamponnee) {
                if (empty($annexe_tamponnee)) {
                    continue;
                }
                if (!isset($annexes_envoyees[$i])) {
                    $message = 'Une erreur est survenue lors de la récupération des annexes tamponnées de ' . $tdT->getLogicielName() .
                        " L'annexe tamponée " . $annexe_tamponnee['filename'] .
                        " ne correspond à aucune des " . count($annexes_envoyees) . ' annexe(s) envoyée(s)';
                    $this->changeOrUpdateAction($tdt_error, $message);
                    $this->notify($tdt_error, $this->type, $message);
                    return false;
                }
                $annexe_filename_send = $tdT->getFilenameTransformation(
                    $this->getDonneesFormulaire()->getFileName(
                        $annexes_envoyees[$i]['element'],
                        $annexes_envoyees[$i]['num']
                    )
                );
                if (strcmp($annexe_filename_send, $annexe_tamponnee['filename']) !== 0) {
                    $message = 'Une erreur est survenue lors de la récupération des annexes tamponnées de ' . $tdT->getLogicielName() .
                        " L'annexe tamponée " . $annexe_tamponnee['filename'] . ' ne correspond pas avec ' . $annexe_filename_send;
                    $this->changeOrUpdateAction($tdt_error, $message);
                    $this->notify($tdt_error, $this->type, $message);
                    return false;
                }
                $annexe_filename = $donneesFormulaire->getFileNameWithoutExtension(
                    $annexes_envoyees[$i]['element'],
                    $annexes_envoyees[$i]['num']
                );
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
