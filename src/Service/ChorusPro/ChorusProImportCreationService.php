<?php

namespace Pastell\Service\ChorusPro;

use FluxEntiteSQL;
use DocumentIndexSQL;
use ActionExecutorFactory;
use DocumentTypeFactory;
use DocumentEntite;
use CPPException;
use Exception;
use NotFoundException;
use UnrecoverableException;
use DocumentCreationService;

class ChorusProImportCreationService
{
    /**
     * @var FluxEntiteSQL
     */
    private $fluxEntiteSQL;

    /**
     * @var DocumentIndexSQL
     */
    private $documentIndexSQL;

    /**
     * @var ActionExecutorFactory
     */
    private $actionExecutorFactory;

    /**
     * @var DocumentTypeFactory
     */
    private $documentTypeFactory;

    /**
     * @var DocumentEntite
     */
    private $documentEntite;

    /**
     * @var DocumentCreationService
     */
    private $documentCreationService;

    /** @var  int */
    private $id_e;
    /** @var  int */
    private $id_u;
    /** @var  int */
    private $id_ce;

    public function setChorusProConfigService(int $id_e, int $id_u, int $id_ce)
    {
        $this->id_e = $id_e;
        $this->id_u = $id_u;
        $this->id_ce = $id_ce;
    }

    public function __construct(
        FluxEntiteSQL $fluxEntiteSQL,
        DocumentIndexSQL $documentIndexSQL,
        ActionExecutorFactory $actionExecutorFactory,
        DocumentTypeFactory $documentTypeFactory,
        DocumentEntite $documentEntite,
        DocumentCreationService $documentCreationService
    ) {
        $this->fluxEntiteSQL = $fluxEntiteSQL;
        $this->documentIndexSQL = $documentIndexSQL;
        $this->actionExecutorFactory = $actionExecutorFactory;
        $this->documentTypeFactory = $documentTypeFactory;
        $this->documentEntite = $documentEntite;
        $this->documentCreationService = $documentCreationService;
    }

    public function analyseOneFactureCreation(array $facture_a_creer): array
    {
        $id_d = $this->getDocumentFacture($facture_a_creer['id_facture_cpp']);

        $result = [];
        if ($id_d) {
            // La facture existe. Il faut charger l'id_e pour le message d'erreur.
            $donneesEntite = $this->documentEntite->getEntite($id_d);
            $result['id_d'] = $id_d;
            $result['message'] = "ERREUR: La facture " . $facture_a_creer['id_facture_cpp'] .
                ", id_d = " . $id_d . " est déja présente sur l'entité id_e = " .
                $donneesEntite[0]['id_e'];
            $result['resultat'] = false;
        } else {
            $result = $this->creerFacture($facture_a_creer);
        }
        $result['type'] = ChorusProImportUtilService::TYPE_SYNCHRONISATION_CREATION;

        return $result;
    }

    // Fonction appelée par la fonction métier pour la création de documents CPP
    // Contrat de retour :
    // array(
    //  'resultat' => true/false
    //  'id_d' => false si absent
    //  'id_facture_cpp' => false si absent
    //  'message' => texte libre en cas de succes ou message de l'exception
    /**
     * @param array $facture_cpp
     * @return array
     */
    private function creerFacture(array $facture_cpp): array
    {
        try {
            $result_creation = $this->creerDocumentFacture($facture_cpp, $this->getFluxName());
            $result['id_d'] = $result_creation['id_d'];
            $result['message'] = $result_creation['message'];
            $result['resultat'] = true;
        } catch (Exception $ex) {
            $result['id_d'] = '';
            $result['resultat'] = false;
            $result['message']  = $ex->getMessage();
        }
        return $result;
    }


    /**
     * @param array $factureCPP
     * @param string $authorized_flux
     * @return array
     * @throws CPPException
     * @throws UnrecoverableException
     * @throws NotFoundException
     */
    private function creerDocumentFacture(array $factureCPP, string $authorized_flux): array
    {
        $actionExecutorFactory = $this->actionExecutorFactory;
        if (!$this->documentTypeFactory->isTypePresent($authorized_flux)) {
            throw new CPPException("Le type $authorized_flux n'existe pas sur cette plateforme Pastell");
        }

        $id_d = $this->documentCreationService->createDocumentWithoutAuthorizationChecking($this->id_e, $authorized_flux);

        $actionExecutorFactory->executeOnDocumentThrow(
            $id_d,
            $this->id_e,
            $this->id_u,
            'create-facture',
            [],
            false,
            ['factureCPP' => $factureCPP],
            0
        );
        return  ['id_d' => $id_d, 'message' => $actionExecutorFactory->getLastMessage()];
    }

    /**
     * @param string $id_facture_cpp
     * @return string
     */
    private function getDocumentFacture(string $id_facture_cpp): string
    {
        return $this->documentIndexSQL->getByFieldValue('id_facture_cpp', $id_facture_cpp);
    }

    /**
     * @return string
     * @throws CPPException
     */
    public function getFluxName(): string
    {
        $all = $this->fluxEntiteSQL->getFluxByConnecteur($this->id_ce);

        $authorizedFlux = $this->removeExcludedFlux($all);

        if (empty($authorizedFlux)) {
            throw new CPPException(
                "Le connecteur n'est associé à aucun type de dossier pour la récupération des factures..."
            );
        }
        if (count($authorizedFlux) > 1) {
            throw new CPPException(
                'Le connecteur est associé à plusieurs type de dossier pour la récupération des factures...'
            );
        }
        return $authorizedFlux[0];
    }

    /**
     * Remove unauthorized flux to handle invoices
     *
     * @param array $fluxList
     * @return array
     */
    private function removeExcludedFlux(array $fluxList): array
    {
        $exclusionList = [
            'statut-facture-cpp',
            'facture-chorus-fournisseur'
        ];
        return array_diff($fluxList, $exclusionList);
    }
}
