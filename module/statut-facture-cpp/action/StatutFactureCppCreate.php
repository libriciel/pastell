<?php

use Pastell\Service\ChorusPro\ChorusProXSDStatutPivot;

class StatutFactureCppCreate extends ActionExecutor
{
    /**
     * @throws Exception
     */
    public function go(): bool
    {
        $donneesFormulaire = $this->getDonneesFormulaire();

        /** @var CPP $portailFature */
        $portailFature = $this->getConnecteur("PortailFacture");

        $filePath = $donneesFormulaire->getFilePath('fichier_statut_facture');

        $schemaPath = $this->objectInstancier->getInstance(ChorusProXSDStatutPivot::class)->getSchemaPath();
        $xsdValidator = new XSDValidator();
        try {
            $xsdValidator->schemaValidate($schemaPath, $filePath);
        } catch (Exception $e) {
            $errorMessage = "Le fichier CPPStatutPivot est incorrect: " . $e->getMessage();
            $this->getActionCreator()
                ->addAction($this->id_e, $this->id_u, 'create-statut-facture-cpp-error', $errorMessage);
            $this->notify('create-statut-facture-cpp-error', $this->type, $errorMessage);
            throw new Exception($errorMessage);
        }

        $xmlWrapper = new SimpleXMLWrapper();
        $content = $xmlWrapper->loadFile($filePath);

        if (!$content->CPPFactureStatuts) {
            $errorMessage = "Le fichier CPPStatutPivot est incorrect : Il ne présente pas l'élément CPPFactureStatuts";
            $this->getActionCreator()
                ->addAction($this->id_e, $this->id_u, 'create-statut-facture-cpp-error', $errorMessage);
            $this->notify('create-statut-facture-cpp-error', $this->type, $errorMessage);

            throw new Exception($errorMessage);
        }
        $supplier = $content->CPPFactureStatuts->CPPFactureStatutUnitaire->Fournisseur;
        $supplierIdentifier = (string)$supplier->Identifiant;

        $donneesFormulaire->setData('fournisseur', $supplierIdentifier);

        $supplierCppId = $portailFature->getIdentifiantStructureCPPByIdentifiantStructure($supplierIdentifier, "false");

        if (!$supplierCppId) {
            $donneesFormulaire->setData('identifiant_cpp_fournisseur', "1-IDENTIFIANT NON TROUVE");
            $errorMessage = "L'identifiant de structure $supplierIdentifier n'a pas été trouvé. L'identifiant CPP est invalide";
            $this->getActionCreator()
                ->addAction($this->id_e, $this->id_u, 'create-statut-facture-cpp-error', $errorMessage);
            $this->notify('create-statut-facture-cpp-error', $this->type, $errorMessage);
            throw new Exception($errorMessage);
        }

        $donneesFormulaire->setData('identifiant_cpp_fournisseur', $supplierCppId);

        $donneesFormulaire->setData('fournisseur_raison_sociale', (string)$supplier->RaisonSociale);

        $recipient = $content->CPPFactureStatuts->CPPFactureStatutUnitaire->Debiteur;
        $donneesFormulaire->setData('destinataire', (string)$recipient->Identifiant);

        $invoiceStatusData = $content->CPPFactureStatuts->CPPFactureStatutUnitaire->DonneesStatut;

        $donneesFormulaire->setData('numero_facture', (string)$invoiceStatusData->IdFacture);

        try {
            $invoiceCppId = $portailFature->getInvoicePerSupplier($supplierCppId, (string)$invoiceStatusData->IdFacture);
            $donneesFormulaire->setData('identifiant_facture_cpp', $invoiceCppId);
        } catch (Exception $e) {
            $errorMessage = $e->getMessage();
            $this->getActionCreator()
                ->addAction($this->id_e, $this->id_u, 'create-statut-facture-cpp-error', $errorMessage);
            $this->notify('create-statut-facture-cpp-error', $this->type, $errorMessage);
            throw new Exception($errorMessage);
        }

        $targetStatusId = (string)$invoiceStatusData->IdStatut;
        $donneesFormulaire->setData('identifiant_statut_cible', $targetStatusId);
        try {
            $statutCible = $this->getStatusFromId($targetStatusId);
            $donneesFormulaire->setData('statut_cible', $statutCible);
        } catch (Exception $e) {
            $errorMessage = $e->getMessage();
            $this->getActionCreator()
                ->addAction($this->id_e, $this->id_u, 'create-statut-facture-cpp-error', $errorMessage);
            $this->notify('create-statut-facture-cpp-error', $this->type, $errorMessage);
            throw new Exception($errorMessage);
        }
        $donneesFormulaire->setData('commentaire', (string)$invoiceStatusData->Commentaire);
        $this->addActionOK("Importation du document Pastell");

        return true;
    }

    /**
     * @param string $statusId
     * @return string
     * @throws Exception when the status id doesn't exist
     */
    private function getStatusFromId(string $statusId): string
    {
        $statusesList = [
            '01' => PortailFactureConnecteur::STATUT_DEPOSEE,
            '02' => PortailFactureConnecteur::STATUT_ACHEMINEMENT,
            '03' => PortailFactureConnecteur::STATUT_MISE_A_DISPOSITION,
            '04' => PortailFactureConnecteur::STATUT_A_RECYCLER,
            '05' => PortailFactureConnecteur::STATUT_REJETEE,
            '06' => PortailFactureConnecteur::STATUT_SUSPENDUE,
            '07' => PortailFactureConnecteur::STATUT_SERVICE_FAIT,
            '08' => PortailFactureConnecteur::STATUT_MANDATEE,
            '09' => PortailFactureConnecteur::STATUT_MISE_A_DISPOSITION_COMPTABLE,
            '10' => PortailFactureConnecteur::STATUT_COMPTABILISEE,
            '11' => PortailFactureConnecteur::STATUT_MISE_EN_PAIEMENT,
            '12' => PortailFactureConnecteur::STATUT_COMPLETEE,
        ];

        if (!array_key_exists($statusId, $statusesList)) {
            throw new Exception("L'identifiant $statusId est inconnu");
        }

        return $statusesList[$statusId];
    }
}
