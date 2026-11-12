<?php

use Pastell\Service\Connecteur\Frequence\ConnecteurFrequenceData;
use Pastell\Service\Connecteur\Frequence\ConnecteurFrequenceService;
use Pastell\Service\Droit\DroitService;
use Pastell\Service\Droit\DroitType;
use Pastell\Service\Entite\RootEntity;

class FrequenceConnecteurAPIController extends BaseAPIController
{
    public function __construct(
        private readonly ConnecteurFrequenceSQL $connecteurFrequenceSQL,
        private readonly ConnecteurFrequenceService $connecteurFrequenceService,
    ) {
    }

    /**
     * @throws ForbiddenException
     * @throws NotFoundException
     */
    public function get(): array
    {
        $this->checkDroitFor(RootEntity::ID_E, DroitService::DROIT_SYSTEM, DroitType::LECTURE);

        $id_cf = $this->getFromQueryArgs(0);
        if ($id_cf) {
            return $this->detail($id_cf);
        }

        $frequencies = $this->connecteurFrequenceSQL->getAllArray();
        foreach ($frequencies as &$frequency) {
            $frequency['id_cf'] = (string)$frequency['id_cf'];
            $frequency['id_ce'] = (string)$frequency['id_ce'];
        }
        return $frequencies;
    }


    /**
     * @throws ForbiddenException
     * @throws NotFoundException
     */
    public function detail($id_cf): array
    {
        $this->checkDroitFor(RootEntity::ID_E, DroitService::DROIT_SYSTEM, DroitType::LECTURE);
        $result = $this->connecteurFrequenceSQL->getInfo($id_cf);
        if (!$result) {
            throw new NotFoundException("Cette fréquence de connecteur n'existe pas");
        }
        $result['id_cf'] = (string)$result['id_cf'];
        $result['id_ce'] = (string)$result['id_ce'];
        return $result;
    }

    /**
     * @throws ForbiddenException
     * @throws NotFoundException
     */
    public function post(): array
    {
        $this->checkDroitFor(RootEntity::ID_E, DroitService::DROIT_SYSTEM, DroitType::EDITION);
        $recuperateur = new Recuperateur($this->getRequest());
        $id_cf = $this->connecteurFrequenceService->save(ConnecteurFrequenceData::fromArray($recuperateur->getAll()));
        return $this->detail($id_cf);
    }

    /**
     * @throws ForbiddenException
     * @throws NotFoundException
     */
    public function patch(): array
    {
        $this->checkDroitFor(RootEntity::ID_E, DroitService::DROIT_SYSTEM, DroitType::EDITION);
        $id_cf = $this->getFromQueryArgs(0);
        $recuperateur = new Recuperateur($this->getRequest());
        $this->connecteurFrequenceService->save(
            ConnecteurFrequenceData::fromArray(['id_cf' => $id_cf] + $recuperateur->getAll())
        );
        return $this->detail($id_cf);
    }

    /**
     * @throws ForbiddenException
     * @throws NotFoundException
     */
    public function delete(): array
    {
        $this->checkDroitFor(RootEntity::ID_E, DroitService::DROIT_SYSTEM, DroitType::EDITION);
        $id_cf = $this->getFromQueryArgs(0);
        $this->connecteurFrequenceService->delete((int)$id_cf);
        $result['result'] = BaseAPIController::RESULT_OK;
        return $result;
    }
}
