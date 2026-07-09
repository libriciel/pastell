<?php

use Pastell\Service\Droit\DroitService;
use Pastell\Service\Droit\DroitType;

class DocumentCreationService
{
    private $documentSQL;
    private $actionExecutorFactory;
    private $droitService;

    public function __construct(
        private readonly EntiteSQL $entiteSQL,
        DocumentSQL $documentSQL,
        ActionExecutorFactory $actionExecutorFactory,
        DroitService $droitService
    ) {
        $this->documentSQL = $documentSQL;
        $this->actionExecutorFactory = $actionExecutorFactory;
        $this->droitService = $droitService;
    }

    /**
     * @param int $id_e
     * @param int $id_u - -1 si pas d'utilisateur
     * @param string $type
     * @return string
     * @throws UnrecoverableException
     * @throws ForbiddenException
     * @throws NotFoundException
     */
    public function createDocument(int $id_e, int $id_u, string $type): string
    {
        if (!$this->entiteSQL->isActive($id_e)) {
            throw new ForbiddenException("L'entité $id_e est désactivée");
        }
        $droit = DroitService::getDroitFor($type, DroitType::EDITION);
        if (! $this->droitService->hasDroitFor($id_u, $id_e, $type, DroitType::EDITION)) {
            throw new ForbiddenException("Acces interdit id_e=$id_e, droit=$droit,id_u=$id_u");
        }
        return $this->_createDocument($id_e, $id_u, $type);
    }

    /**
     * @param $id_e
     * @param $type
     * @return string
     * @throws UnrecoverableException
     */
    public function createDocumentWithoutAuthorizationChecking($id_e, $type)
    {
        return $this->_createDocument($id_e, 0, $type);
    }

    /**
     * @param int $id_e
     * @param int $id_u
     * @param string $type
     * @return string
     * @throws UnrecoverableException
     */
    private function _createDocument(int $id_e, int $id_u, string $type): string
    {
        $id_d = $this->documentSQL->getNewId();
        $this->documentSQL->save($id_d, $type);

        $result = $this->actionExecutorFactory->executeOnDocument(
            $id_e,
            $id_u,
            $id_d,
            CreationAction::ACTION_ID,
            [],
            true
        );

        if (! $result) {
            $this->documentSQL->delete($id_d);
            throw new UnrecoverableException(
                "Impossible d'executer l'action de création sur le document : " .
                $this->actionExecutorFactory->getLastMessage()
            );
        }
        return $id_d;
    }
}
