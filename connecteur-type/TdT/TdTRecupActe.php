<?php

class TdTRecupActe extends ConnecteurTypeActionExecutor
{
    public const BORDEREAU_TDT_SUFFIX = '-bordereau-tdt.pdf';

    /**
     * @return bool
     * @throws Exception
     */
    public function go()
    {

        $tedetis_transaction_id_element = $this->getMappingValue('tedetis_transaction_id');
        $erreur_verif_tdt = $this->getMappingValue('erreur-verif-tdt');
        $tdt_error = $this->getMappingValue('tdt-error');
        $acquiter_tdt = $this->getMappingValue('acquiter-tdt');
        $send_tdt = $this->getMappingValue('send-tdt');
        $numero_de_lacte_element = $this->getMappingValue('numero_de_lacte');
        $has_bordereau_element = $this->getMappingValue('has_bordereau');
        $bordereau_element = $this->getMappingValue('bordereau');
        $aractes_element = $this->getMappingValue('aractes');
        $acteUniqueIdElement = $this->getMappingValue('acte_unique_id');
        $arrete_element = $this->getMappingValue('arrete');
        $acte_tamponne_element = $this->getMappingValue('acte_tamponne');
        $autre_document_attache_element = $this->getMappingValue('autre_document_attache');
        $annexes_tamponnees_element = $this->getMappingValue('annexes_tamponnees');
        $date_ar_element = $this->getMappingValue('date_ar');

        /** @var TdtConnecteur $tdT */
        $tdT = $this->getConnecteurOrFail('Tdt');
        $tedetis_transaction_id = $this->getDonneesFormulaire()->get($tedetis_transaction_id_element);

        if (! $tedetis_transaction_id) {
            $message = \sprintf(
                "Une erreur est survenue lors de l'envoi à %s (tedetis_transaction_id non disponible)",
                $tdT->getLogicielName()
            );
            $this->changeOrUpdateAction($tdt_error, $message);
            $this->notify($tdt_error, $this->type, $message);
            return false;
        }

        try {
            $status = $tdT->getStatus($tedetis_transaction_id);
        } catch (Exception $e) {
            $message = 'Echec de la récupération des informations : ' .  $e->getMessage();
            $this->setLastMessage($message);
            return false;
        }

        if ($status == TdtConnecteur::STATUS_ERREUR) {
            $message = 'Transaction en erreur sur le TdT : ' . $tdT->getLastError();
            $this->changeOrUpdateAction($erreur_verif_tdt, $message);
            $this->notify($erreur_verif_tdt, $this->type, $message);
            return false;
        }

        if ($status != TdtConnecteur::STATUS_ACQUITTEMENT_RECU) {
            $this->setLastMessage('La transaction a comme statut : ' . TdtConnecteur::getStatusString($status));
            return true;
        }

        $aractes = $tdT->getARActes();
        $bordereau_data = $tdT->getBordereau($tedetis_transaction_id);
        $actes_tamponne = $tdT->getActeTamponne($tedetis_transaction_id);
        $annexes_tamponnees_list = $tdT->getAnnexesTamponnees($tedetis_transaction_id);

        $this->changeAction($acquiter_tdt, "L'acte a été acquitté par le contrôle de légalité");

        $infoDocument = $this->getDocument()->getInfo($this->id_d);
        $documentActionEntite = $this->getDocumentActionEntite();
        $infoUser = $documentActionEntite->getUserFromAction($this->id_e, $this->id_d, $send_tdt);
        $message = \sprintf(
            "L'acte « %s » télétransmis par %s %s a été acquitté par le contrôle de légalité",
            $infoDocument['titre'],
            $infoUser['prenom'],
            $infoUser['nom']
        );

        $message .= \sprintf(
            "\n\nConsulter le détail de l'acte : %s/Document/detail?id_d=%s&id_e=%s",
            $this->getSiteBase(),
            $this->id_d,
            $this->id_e
        );

        $donneesFormulaire = $this->getDonneesFormulaire();
        $numero_de_lacte = $donneesFormulaire->get($numero_de_lacte_element);

        if ($bordereau_data) {
            $donneesFormulaire->setData($has_bordereau_element, true);
            $donneesFormulaire->addFileFromData(
                $bordereau_element,
                $numero_de_lacte . self::BORDEREAU_TDT_SUFFIX,
                $bordereau_data
            );
        }
        if ($aractes) {
            $donneesFormulaire->addFileFromData($aractes_element, "$numero_de_lacte-ar-actes.xml", $aractes);
            $simpleXMLWrapper = new SimpleXMLWrapper();
            $xmlDocument = $simpleXMLWrapper->loadString($aractes);
            $idActe = (string)$xmlDocument->xpath('//actes:ARActe/@actes:IDActe')[0];
            $donneesFormulaire->setData($acteUniqueIdElement, $idActe);
        }
        if ($actes_tamponne) {
            $actes_original_filename = $donneesFormulaire->getFileNameWithoutExtension($arrete_element);
            $donneesFormulaire->addFileFromData(
                $acte_tamponne_element,
                $actes_original_filename . '-tampon.pdf',
                $actes_tamponne
            );
        }
        if ($annexes_tamponnees_list) {
            $file_number = 0;
            foreach ($annexes_tamponnees_list as $i => $annexe_tamponnee) {
                if (empty($annexe_tamponnee)) {
                    continue;
                }
                $annexe_filename_send = $tdT->getFilenameTransformation(
                    $this->getDonneesFormulaire()->getFileName($autre_document_attache_element, $i)
                );
                if (strcmp($annexe_filename_send, $annexe_tamponnee['filename']) !== 0) {
                    $message = 'Une erreur est survenue lors de la récupération des annexes tamponnées de ' .
                        $tdT->getLogicielName() . " L'annexe tamponée " . $annexe_tamponnee['filename'] .
                        ' ne correspond pas avec ' . $annexe_filename_send;

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

        $donneesFormulaire->setData($date_ar_element, $tdT->getDateAR($tedetis_transaction_id));

        $this->notify($acquiter_tdt, $this->type, $message);

        $this->setLastMessage("L'acquittement du contrôle de légalité a été reçu.");
        return true;
    }
}
