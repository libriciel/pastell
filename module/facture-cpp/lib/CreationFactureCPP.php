<?php

class CreationFactureCPP
{
    protected $objectInstancier;

    public function __construct(ObjectInstancier $objectInstancier)
    {
        $this->objectInstancier = $objectInstancier;
    }

    /**
     * @throws NotFoundException
     */
    public function alimenter($id_d, array $cpp_array): string
    {
        /** @var DonneesFormulaire $donneesFormulaire */
        $donneesFormulaire = $this->objectInstancier->getInstance(DonneesFormulaireFactory::class)->get($id_d);

        // Alimentation des attributs simples disponibles dans $factureCPP
        $donneesFormulaire->setData(AttrFactureCPP::ATTR_ID_FACTURE_CPP, $cpp_array['id_facture_cpp']);
        $donneesFormulaire->setData(AttrFactureCPP::ATTR_STATUT_CPP, $cpp_array['statut']);
        $donneesFormulaire->setData(AttrFactureCPP::ATTR_FOURNISSEUR, $cpp_array['fournisseur']);
        $donneesFormulaire->setData(AttrFactureCPP::ATTR_DESTINATAIRE, $cpp_array['destinataire']);
        $donneesFormulaire->setData(AttrFactureCPP::ATTR_SIRET, $cpp_array['siret']);
        $donneesFormulaire->setData(AttrFactureCPP::ATTR_SERVICE_DESTINATAIRE, $cpp_array['service_destinataire']);
        $donneesFormulaire->setData(AttrFactureCPP::ATTR_SERVICE_DESTINATAIRE_CODE, $cpp_array['service_destinataire_code']);
        $donneesFormulaire->setData(AttrFactureCPP::ATTR_TYPE_FACTURE, $cpp_array['type_facture']);
        $donneesFormulaire->setData(AttrFactureCPP::ATTR_NO_FACTURE, $cpp_array['no_facture']);
        $donneesFormulaire->setData(AttrFactureCPP::ATTR_DATE_FACTURE, $cpp_array['date_facture']);
        $donneesFormulaire->setData(AttrFactureCPP::ATTR_DATE_DEPOT, $cpp_array['date_depot']);
        $donneesFormulaire->setData(AttrFactureCPP::ATTR_DATE_STATUT_COURANT, $cpp_array['date_statut_courant']);
        $donneesFormulaire->setData(AttrFactureCPP::ATTR_MONTANT_TTC, $cpp_array['montant_ttc']);
        $donneesFormulaire->setData(AttrFactureCPP::ATTR_TYPE_IDENTIFIANT, $cpp_array['type_identifiant']);
        $donneesFormulaire->setData(AttrFactureCPP::ATTR_FOURNISSEUR_RAISON_SOCIALE, $cpp_array['fournisseur_raison_sociale']);
        $donneesFormulaire->setData(AttrFactureCPP::ATTR_TYPE_INTEGRATION, $cpp_array['type_integration']);

        $titre = $donneesFormulaire->getTitre();
        $this->objectInstancier->getInstance(DocumentSQL::class)->setTitre($id_d, $titre);
        return $titre;
    }

    /**
     * @param $id_d
     * @param $parametrageFluxFactureCPP
     * @throws NotFoundException
     */
    public function parametrer($id_d, $parametrageFluxFactureCPP)
    {
        /** @var DonneesFormulaire $donneesFormulaire */
        $donneesFormulaire = $this->objectInstancier->getInstance(DonneesFormulaireFactory::class)->get($id_d);

        $statut = $donneesFormulaire->get(AttrFactureCPP::ATTR_STATUT_CPP);
        $tabParam = $parametrageFluxFactureCPP->getParametres();
        if (($statut == "MISE_A_DISPOSITION") || ($statut == "COMPLETEE")) {
            $donneesFormulaire->setData('envoi_visa', $tabParam['envoi_visa']);
            $donneesFormulaire->setData('iparapheur_type', $tabParam['iparapheur_type']);
            $donneesFormulaire->setData('iparapheur_sous_type', $tabParam['iparapheur_sous_type']);
        }
        $donneesFormulaire->setData('envoi_ged', $tabParam['envoi_ged']);
        $donneesFormulaire->setData('envoi_sae', $tabParam['envoi_sae']);
        $donneesFormulaire->setData('check_mise_a_dispo_gf', $tabParam['check_mise_a_dispo_gf']);
        $donneesFormulaire->setData('envoi_auto', $tabParam['envoi_auto']);
    }
}
