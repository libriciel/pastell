<?php

use Pastell\Service\Module\ModuleListService;
use Pastell\Service\Droit\DroitType;

class FluxAPIController extends BaseAPIController
{
    public function __construct(
        private readonly DocumentTypeFactory $documentTypeFactory,
        private readonly ModuleListService $moduleListService,
    ) {
    }

    /**
     * @throws NotFoundException
     * @throws ForbiddenException
     */
    public function get(): array
    {
        $idFlux = $this->getFromQueryArgs(0);
        $action = $this->getFromQueryArgs(1);
        if (! $idFlux) {
            return $this->moduleListService->getModuleListOrderByNom($this->getUtilisateurId(), $this->hasAllDroit());
        }

        if (! $this->documentTypeFactory->isTypePresent($idFlux)) {
            throw new NotFoundException("Le flux $idFlux n'existe pas sur cette plateforme");
        }
        $this->checkOneDroitFor($idFlux, DroitType::LECTURE);

        if ($action === 'action') {
            return $this->listAction($idFlux);
        }

        return $this->getFlux($idFlux);
    }

    /**
     * @deprecated 4.1.24 Use ModuleListService::getModuleListOrderByNom() instead
     */
    public function listFlux()
    {
        return $this->moduleListService->getModuleListOrderByNom($this->getUtilisateurId(), $this->hasAllDroit());
    }

    public function getFlux(string $idFlux): array
    {
        $documentType = $this->documentTypeFactory->getFluxDocumentType($idFlux);
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

    public function listAction(string $idFlux): array
    {
        return $this->documentTypeFactory->getFluxDocumentType($idFlux)->getTabAction();
    }
}
