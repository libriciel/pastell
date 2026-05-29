<?php

declare(strict_types=1);

use Pastell\Service\Annuaire\AnnuaireGroupeService;
use Pastell\Service\Droit\DroitService;

final class AnnuaireGroupeAPIController extends BaseAPIController
{
    public function __construct(
        private readonly AnnuaireGroupeService $annuaireGroupeService,
    ) {
    }

    /**
     * @throws ForbiddenException
     * @throws NotFoundException
     */
    public function get(): array
    {
        $id_g = $this->getFromQueryArgs(0);
        if ($id_g !== false) {
            $info = $this->annuaireGroupeService->findGroupe((int)$id_g);
            $this->checkDroit((int)$info['id_e'], DroitService::getDroitLecture(DroitService::DROIT_ANNUAIRE));
            return $this->formatGroupe($info);
        }

        $id_e = (int)$this->getFromRequest('id_e', 0);
        $this->checkDroit($id_e, DroitService::getDroitLecture(DroitService::DROIT_ANNUAIRE));

        $result = [];
        foreach ($this->annuaireGroupeService->listGroupes($id_e) as $groupe) {
            $result[] = $this->formatGroupe($groupe);
        }
        return $result;
    }

    /**
     * @throws ForbiddenException
     * @throws ConflictException
     * @throws BadRequestException
     * @throws NotFoundException
     */
    public function post(): array
    {
        $id_g = $this->getFromQueryArgs(0);
        if ($id_g !== false) {
            $info = $this->annuaireGroupeService->findGroupe((int)$id_g);
            $this->checkDroit((int)$info['id_e'], DroitService::getDroitEdition(DroitService::DROIT_ANNUAIRE));
            $id_a = (int)$this->getFromRequest('id_a', 0);
            $this->annuaireGroupeService->addContactToGroupe((int)$id_g, $id_a);
            return ['result' => self::RESULT_OK];
        }

        $id_e = (int)$this->getFromRequest('id_e', 0);
        $nom  = (string)$this->getFromRequest('nom', '');
        $this->checkDroit($id_e, DroitService::getDroitEdition(DroitService::DROIT_ANNUAIRE));

        $info = $this->annuaireGroupeService->createGroupe($id_e, $nom);
        return $this->formatGroupe($info);
    }

    /**
     * @throws ForbiddenException
     * @throws NotFoundException
     */
    public function delete(): array
    {
        $id_g = (int)$this->getFromQueryArgs(0);
        $id_a = $this->getFromQueryArgs(1);

        $info = $this->annuaireGroupeService->findGroupe($id_g);
        $id_e = (int)$info['id_e'];
        $this->checkDroit($id_e, DroitService::getDroitEdition(DroitService::DROIT_ANNUAIRE));

        if ($id_a !== false) {
            $this->annuaireGroupeService->removeContactFromGroupe($id_g, (int)$id_a);
            return ['result' => self::RESULT_OK];
        }

        $this->annuaireGroupeService->deleteGroupe($id_e, $id_g);
        return ['result' => self::RESULT_OK];
    }

    private function formatGroupe(array $groupe): array
    {
        return [
            'id_g' => (string)$groupe['id_g'],
            'id_e' => (string)$groupe['id_e'],
            'nom'  => $groupe['nom'],
        ];
    }
}
