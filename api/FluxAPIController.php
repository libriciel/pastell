<?php

use Pastell\Service\Droit\DroitType;
use Pastell\Service\Droit\DroitService;

class FluxAPIController extends BaseAPIController
{
    /** @var  DocumentTypeFactory */
    private $documentTypeFactory;

    public function __construct(DocumentTypeFactory $documentTypeFactory)
    {
        $this->documentTypeFactory = $documentTypeFactory;
    }

    public function get()
    {
        $id_flux = $this->getFromQueryArgs(0);
        $action = $this->getFromQueryArgs(1);
        if (! $id_flux) {
            return $this->listFlux();
        }

        if (! $this->documentTypeFactory->isTypePresent($id_flux)) {
            throw new NotFoundException("Le flux $id_flux n'existe pas sur cette plateforme");
        }
        $this->checkOneDroit(DroitService::getDroitFor($id_flux, DroitType::LECTURE));

        if ($action == "action") {
            return $this->listAction($id_flux);
        }

        return $this->getFlux($id_flux);
    }

    public function listFlux()
    {
        $allDocType = $this->documentTypeFactory->getAllType();
        $allType = [];
        foreach ($allDocType as $type_flux => $les_flux) {
            foreach ($les_flux as $nom => $affichage) {
                if ($this->hasOneDroit(DroitService::getDroitFor($nom, DroitType::LECTURE))) {
                    $allType[$nom]  = ['type' => $type_flux,'nom' => $affichage];
                }
            }
        }
        uasort($allType, static function ($a, $b) {
            return strcmp($a['nom'], $b['nom']);
        });
        return $allType;
    }

    public function getFlux($id_flux)
    {
        $documentType = $this->documentTypeFactory->getFluxDocumentType($id_flux);
        $formulaire = $documentType->getFormulaire();
        $result = [];
        /**
         * @var Field $fields
         */
        foreach ($formulaire->getAllFields() as $key => $fields) {
            $result[$key] = $fields->getAllProperties();
        }
        return $result;
    }

    public function listAction($id_flux)
    {
        $documentType = $this->documentTypeFactory->getFluxDocumentType($id_flux);
        return $documentType->getTabAction();
    }
}
