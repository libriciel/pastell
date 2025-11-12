<?php

use Pastell\Service\ChorusPro\ChorusProXSDPivot;

/**
 * @deprecated 5.0.0 Chorus
 */
class CppIntegrationParGlaneurPivot extends ActionExecutor
{
    /**
     * @return bool
     * @throws Exception
     */
    public function go()
    {

        $donneesFormulaire = $this->getDonneesFormulaire();

        $fichier_pivot = $donneesFormulaire->getFilePath('fichier_facture');

        try {
            $this->objectInstancier->getInstance(ChorusProXSDPivot::class)->checkIsFormatPivot($fichier_pivot);
        } catch (Exception $e) {
            $message = 'Le fichier FacturePivot est incorrect: ' . $e->getMessage();
            $this->changeAction('integration-glaneur-pivot-error', $message);
            $this->notify('integration-glaneur-pivot-error', $this->type, $message);
            return false;
        }

        $docInfo = [
            'id_u' => 0,
            'id_facture_cpp' => date('YmdHis') . '_' . mt_rand(0, mt_getrandmax()),

            'destinataire' => '', // Identifiant CPP du destinataire
            'service_destinataire' => '', // Identifiant CPP du service destinataire

            'type_integration' => 'GLANEUR',
            'statut' => 'MISE_A_DISPOSITION',
            'commentaire' => 'Facture issue du glaneur pivot',
            'date_depot' => '',
            'date_statut_courant' => '',

            'fichier_facture' => $fichier_pivot,
        ];

        $classCreationFactureCPP = $this->objectInstancier->getInstance(CreationFactureCPP::class);

        $docInfoComplete = $classCreationFactureCPP->integrerPivot($this->id_d, $docInfo);
        $classCreationFactureCPP->alimenter($this->id_d, $docInfoComplete);
        $classCreationFactureCPP->historiser($this->id_d, $docInfo) ;

        // Valorisation du cheminement d'après les valeurs par défaut définit dans le connecteur PortailFacture associé au flux Facture CPP
        $parametrageFluxFactureCPP = $this->objectInstancier->getInstance(ConnecteurFactory::class)->getConnecteurByType($this->id_e, $this->type, 'ParametrageFlux');
        if ($parametrageFluxFactureCPP) {
            /** @var ParametrageFluxFactureCPP $parametrageFluxFactureCPP */
            $classCreationFactureCPP->parametrer($this->id_d, $parametrageFluxFactureCPP);
        }

        $message = 'Intégration du dossier via le fichier PIVOT';
        $this->addActionOK($message);
        $this->setLastMessage($message);
        $this->notify($this->action, $this->type, $message);

        return true;
    }
}
