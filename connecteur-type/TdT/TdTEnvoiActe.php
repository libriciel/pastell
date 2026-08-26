<?php

use Pastell\Step\AnnexeList;

class TdTEnvoiActe extends ConnecteurTypeActionExecutor
{
    /**
     * @return bool
     * @throws Exception
     */
    public function go()
    {

        /** @var TdtConnecteur $tdT */
        $tdT = $this->getConnecteur("TdT");

        $send_tdt_erreur = $this->getMappingValue('send-tdt-erreur');
        $document_transmis_tdt = $this->getMappingValue('document-transmis-tdt');

        try {
            $tdtActes = new TdtActes();

            foreach (TdtActes::getStringAttributesList() as $attribute) {
                $tdtActes->$attribute = $this->getDonneesFormulaire()->get($this->getMappingValue($attribute));
            }

            $arrete_element = $this->getMappingValue('arrete');

            $tdtActes->arrete = new Fichier();
            $tdtActes->arrete->filepath = $this->getDonneesFormulaire()->getFilePath($arrete_element);
            $tdtActes->arrete->filename = $this->getDonneesFormulaire()->getFileName($arrete_element);
            $tdtActes->arrete->content = $this->getDonneesFormulaire()->getFileContent($arrete_element);
            $tdtActes->arrete->contentType = $this->getDonneesFormulaire()->getContentType($arrete_element);

            $annexe_list = AnnexeList::getAll(
                $this->getMappingValueList(AnnexeList::MAPPING_KEY),
                $this->getDonneesFormulaire()
            );
            foreach ($annexe_list as $i => $annexe) {
                $tdtActes->autre_document_attache[$i] = new Fichier();
                $tdtActes->autre_document_attache[$i]->filepath = $this->getDonneesFormulaire()->getFilePath($annexe['element'], $annexe['num']);
                $tdtActes->autre_document_attache[$i]->filename = $this->getDonneesFormulaire()->getFileName($annexe['element'], $annexe['num']);
                $tdtActes->autre_document_attache[$i]->content = $this->getDonneesFormulaire()->getFileContent($annexe['element'], $annexe['num']);
                $tdtActes->autre_document_attache[$i]->contentType = $this->getDonneesFormulaire()->getContentType($annexe['element'], $annexe['num']);
            }

            $id_transaction = $tdT->sendActes($tdtActes);

            $tedetis_transaction_id = $this->getMappingValue('tedetis_transaction_id');

            $this->getDonneesFormulaire()->setData($tedetis_transaction_id, $id_transaction);
        } catch (Exception $e) {
            if ($this->id_worker) {
                $message = "Erreur lors de l'envoi au Tdt : " . $e->getMessage();
                $this->changeAction($send_tdt_erreur, $message);
                $this->notify($send_tdt_erreur, $this->type, $message);
            }
            throw $e;
        }

        $tdtConfig = $this->getConnecteurConfigByType("TdT");
        if ($tdtConfig->get('authentication_for_teletransmisson')) {
            $message = "Le document a été envoyé au TdT, en attente du certificat RGS**";
            $this->changeAction($document_transmis_tdt, $message);
            $this->notify($document_transmis_tdt, $this->type, $message);
        } else {
            $this->addActionOK("Le document a été envoyé au contrôle de légalité");
            $this->notify($this->action, $this->type, "Le document a été envoyé au contrôle de légalité");
        }

        return true;
    }
}
