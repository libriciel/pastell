<?php

class FactureCPPIparapheurRecup extends SignatureRecuperation
{
    public function go()
    {

        // Dans le cas de l'héritage on fait le mapping ici (il n'est pas dans le definition.yml)
        $this->setMapping([
            "document" => "fichier_facture_pdf",
            "titre" => "id_facture_cpp",
            "autre_document_attache" => "facture_pj_02",
            "has_signature" => "has_visa"
        ]);

        $result_parapheur = parent::go();

        if ($result_parapheur) {
            $donneesFormulaire = $this->getDonneesFormulaire();
            $metadata = json_decode(
                $donneesFormulaire->getFileContent('iparapheur_metadata_sortie'),
                true,
                512,
                JSON_THROW_ON_ERROR
            );
            if ($this->getActionName() === self::ACTION_NAME_RECU) {
                $donneesFormulaire->setData(
                    AttrFactureCPP::ATTR_STATUT_CIBLE_LISTE,
                    PortailFactureConnecteur::STATUT_SERVICE_FAIT
                );
                $codeService = array_key_exists('CodeService', $metadata) ? $metadata['CodeService'] : '';
                $donneesFormulaire->setData(AttrFactureCPP::ATTR_SERVICE_DESTINATAIRE_CODE, $codeService);
            }
            if ($this->getActionName() === self::ACTION_NAME_REJET) {
                $statutCible = array_key_exists('chorusproStatutRejet', $metadata) ?
                    $metadata['chorusproStatutRejet'] : 'REJETEE';
                $donneesFormulaire->setData(AttrFactureCPP::ATTR_STATUT_CIBLE_LISTE, $statutCible);
                $lastState = $donneesFormulaire->get('parapheur_last_message');
                $donneesFormulaire->setData(AttrFactureCPP::ATTR_MOTIF_MAJ, $lastState);
            }
        }

        return $result_parapheur;
    }
}
