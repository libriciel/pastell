<?php

use Pastell\Service\Droit\DroitType;
use Pastell\Service\Droit\DroitService;
use Pastell\Service\Entite\EntiteDeletionService;
use Pastell\Service\Entite\EntityCreationService;
use Pastell\Service\Entite\EntityUpdateService;

final class EntiteAPIController extends BaseAPIController
{
    public function __construct(
        private readonly EntiteSQL $entiteSQL,
        private readonly EntityCreationService $entityCreationService,
        private readonly EntityUpdateService $entityUpdateService,
        private readonly EntiteDeletionService $entiteDeletionService,
    ) {
    }

    /**
     * @throws NotFoundException
     * @throws Exception
     */
    public function get()
    {
        if ($this->getFromQueryArgs(0)) {
            return $this->getInfo($this->getFromQueryArgs(0));
        }
        $data['is_active'] = $this->getFromRequest('is_active', null);

        $users = $this->getRoleUtilisateur()->getAllEntiteWithFille(
            $this->getUtilisateurId(),
            DroitService::getDroitFor(DroitService::DROIT_ENTITE, DroitType::LECTURE),
            $data['is_active']
        );

        foreach ($users as &$user) {
            $user['id_e'] = (string)$user['id_e'];
            $user['centre_de_gestion'] = (string)$user['centre_de_gestion'];
            $user['is_active'] = (bool)$user['is_active'];
        }
        return $users;
    }

    /**
     * @throws ForbiddenException
     * @throws NotFoundException
     */
    private function getInfo($id_e)
    {
        $infoEntite = $this->entiteSQL->getInfo($id_e);

        if (!$infoEntite) {
            throw new NotFoundException("L'entité $id_e n'a pas été trouvée");
        }
        $this->checkDroitFor($id_e, DroitService::DROIT_ENTITE, DroitType::LECTURE);

        // Chargement des entités filles
        $resultFille = [];
        $entiteFille = $this->entiteSQL->getFille($id_e);
        if ($entiteFille) {
            //GDON : completer les TU pour passer dans la boucle.
            foreach ($entiteFille as $key => $valeur) {
                $resultFille[$key] = ['id_e' => (string)$valeur['id_e']];
            }
        }

        $result = [];
        $result['id_e'] = (string)$infoEntite['id_e'];
        $result['denomination'] = $infoEntite['denomination'];
        $result['siren'] = $infoEntite['siren'];
        $result['type'] = $infoEntite['type'];
        $result['entite_mere'] = $infoEntite['entite_mere'];
        $result['entite_fille'] = $resultFille;
        $result['centre_de_gestion'] = (string)$infoEntite['centre_de_gestion'];
        $result['is_active'] = (bool)$infoEntite['is_active'];

        return $result;
    }

    /**
     * @throws UnrecoverableException
     * @throws NotFoundException
     * @throws ForbiddenException
     */
    public function post()
    {
        $id_e = $this->getFromQueryArgs(0);
        if ($id_e !== false && $this->checkDroitFor($id_e, DroitService::DROIT_ENTITE, DroitType::EDITION)) {
            $action = $this->getFromQueryArgs(1);
            if ($action === 'activate') {
                $this->entiteSQL->setActive($id_e, 1);
            } elseif ($action === 'deactivate') {
                $this->entiteSQL->setActive($id_e, 0);
            } else {
                throw new UnrecoverableException('Cette action n\'existe pas.');
            }
            return $this->getInfo($id_e);
        }
        $entite_mere = $this->getFromRequest('entite_mere', 0);
        $type = $this->getFromRequest('type');
        $siren = $this->getFromRequest('siren', '');
        $denomination = $this->getFromRequest('denomination');
        $centre_de_gestion = $this->getFromRequest('centre_de_gestion', 0);

        $this->checkDroitFor($entite_mere, DroitService::DROIT_ENTITE, DroitType::EDITION);
        $id_e = $this->entityCreationService->create($denomination, $siren, $type, $entite_mere, $centre_de_gestion);
        return $this->getInfo($id_e);
    }

    /**
     * @return mixed
     * @throws ForbiddenException
     * @throws Exception
     */
    public function delete()
    {
        $data['id_e'] = $this->getFromQueryArgs(0);
        $data['denomination'] = $this->getFromRequest('denomination');
        $infoEntiteExistante = $this->entiteSQL->getEntiteFromData($data);
        $id_e = $infoEntiteExistante['id_e'];

        $this->checkDroitFor($id_e, DroitService::DROIT_ENTITE, DroitType::EDITION);

        $this->entiteDeletionService->delete($id_e);

        $result['result'] = self::RESULT_OK;
        return $result;
    }

    /**
     * @throws UnrecoverableException
     * @throws NotFoundException
     * @throws ForbiddenException
     * @throws Exception
     */
    public function patch()
    {
        $data = $this->getRequest();
        $data['id_e'] = $this->getFromQueryArgs(0);

        $infoEntiteExistante = $this->entiteSQL->getEntiteFromData($data);

        $id_e = $infoEntiteExistante['id_e'];

        // Sauvegarde des valeurs. Si elles ne sont pas présentes dans $data, il faut les conserver.
        $entite_mere = $infoEntiteExistante['entite_mere'];
        $centre_de_gestion = $infoEntiteExistante['centre_de_gestion'];

        // Modification de l'entité chargée avec les infos passées par l'API
        foreach ($data as $key => $newValeur) {
            if (array_key_exists($key, $infoEntiteExistante)) {
                $infoEntiteExistante[$key] = $newValeur;
            }
        }

        $type = $infoEntiteExistante['type'];
        $siren = $infoEntiteExistante['siren'] ?? '';
        $denomination = $infoEntiteExistante['denomination'];
        if ($infoEntiteExistante['entite_mere']) {
            $entite_mere = $infoEntiteExistante['entite_mere'];
        }
        if ($infoEntiteExistante['centre_de_gestion']) {
            $centre_de_gestion = $infoEntiteExistante['centre_de_gestion'];
        }

        $this->checkDroitFor($id_e, DroitService::DROIT_ENTITE, DroitType::EDITION);
        $this->checkDroitFor($entite_mere, DroitService::DROIT_ENTITE, DroitType::EDITION);
        $this->entityUpdateService->update($id_e, $denomination, $siren, $type, $entite_mere, $centre_de_gestion);

        $result = $this->getInfo($id_e);
        $result['result'] = self::RESULT_OK;
        return $result;
    }
}
