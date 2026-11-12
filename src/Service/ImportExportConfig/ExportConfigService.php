<?php

namespace Pastell\Service\ImportExportConfig;

use ConnecteurEntiteSQL;
use ConnecteurFactory;
use EntiteSQL;
use Exception;
use FluxEntiteHeritageSQL;
use FluxEntiteSQL;
use Pastell\Service\Entite\EntityHierarchyService;

class ExportConfigService
{
    public const ENTITY_INFO = 'entity_info';
    public const ENTITY_CHILD = 'entity_child';
    public const CONNECTOR_INFO = 'connecteur_info';
    public const ASSOCIATION_INFO = 'association_info';
    public const ASSOCIATION_HERITAGE_INFO = 'association_heritage_info';

    public const INCLUDE_ENTITY = 'include_entity';
    public const INCLUDE_CHILD = 'include_child';
    public const INCLUDE_CONNECTOR = 'include_connector';
    public const INCLUDE_ASSOCIATION = 'include_association';

    public function __construct(
        private readonly EntiteSQL $entiteSQL,
        private readonly EntityHierarchyService $entityHierarchyService,
        private readonly ConnecteurEntiteSQL $connecteurEntiteSQL,
        private readonly ConnecteurFactory $connecteurFactory,
        private readonly FluxEntiteSQL $fluxEntiteSQL,
        private readonly FluxEntiteHeritageSQL $fluxEntiteHeritageSQL,
    ) {
    }

    public static function getOptions(): array
    {
        return [
            self::INCLUDE_ENTITY => "Inclure les informations sur l'entité",
            self::INCLUDE_CHILD => "Inclure les entités filles",
            self::INCLUDE_CONNECTOR => "Inclure les connecteurs",
            self::INCLUDE_ASSOCIATION => "Inclure les associations connecteurs/flux",
            /*'include_user' => 'Inclure les utilisateurs',
            'include_frequence' => 'Inclure la définition des fréquences des flux associés exportés',
            'include_studio' => 'Inclure la définition des flux studio des flux associés exportés',
            'include_role' => 'Inclure les rôles des utilisateurs associés exportés'*/
        ];
    }

    /**
     * @throws Exception
     */
    public function getInfo(int $id_e, array $options): array
    {
        $result = [];
        $entityList = [];
        if ($options[self::INCLUDE_ENTITY]) {
            $result[self::ENTITY_INFO] = $this->entiteSQL->getInfo($id_e);
            $entityList[] = $id_e;
        }
        if ($options[self::INCLUDE_CHILD]) {
            $result[self::ENTITY_CHILD] = $this->entityHierarchyService->getAllChildren($id_e);
            foreach ($result[self::ENTITY_CHILD] as $entityInfo) {
                $entityList[] = $entityInfo['id_e'];
            }
        }
        if ($options[self::INCLUDE_CONNECTOR]) {
            $result[self::CONNECTOR_INFO] = [];
            foreach ($entityList as $id_e_to_save) {
                $allConnecteur = $this->connecteurEntiteSQL->getAll($id_e_to_save);
                foreach ($allConnecteur as $connecteur) {
                    $connecteurConfig = $this->connecteurFactory->getConnecteurConfig($connecteur['id_ce']);
                    $connecteur['data'] = $connecteurConfig->jsonExport();
                    $result[self::CONNECTOR_INFO][] = $connecteur;
                }
            }
        }
        if ($options[self::INCLUDE_ASSOCIATION]) {
            $result[self::ASSOCIATION_INFO] = [];
            foreach ($entityList as $id_e_to_save) {
                $result[self::ASSOCIATION_INFO][$id_e_to_save] =
                    $this->fluxEntiteSQL->getAllWithSameType($id_e_to_save);
                $result[self::ASSOCIATION_HERITAGE_INFO][$id_e_to_save] =
                    $this->fluxEntiteHeritageSQL->getInheritance($id_e_to_save);
            }
        }
        return $result;
    }

    /**
     * @throws \JsonException
     */
    public function getExportedFile(int $id_e, array $options): string
    {
        $info = $this->getInfo($id_e, $options);
        return json_encode($info, JSON_THROW_ON_ERROR);
    }
}
