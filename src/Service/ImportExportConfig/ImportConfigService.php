<?php

namespace Pastell\Service\ImportExportConfig;

use ConnecteurFactory;
use DonneesFormulaireException;
use Exception;
use FluxEntiteHeritageSQL;
use Pastell\Service\Connecteur\ConnecteurAssociationService;
use Pastell\Service\Connecteur\ConnecteurCreationService;
use Pastell\Service\Entite\EntityCreationService;
use UnrecoverableException;

final class ImportConfigService
{
    //Ne permets pas d'importer les centres de gestion des collectivités !

    /** @var string[]  */
    private array $lastErrors = [];

    public function __construct(
        private readonly EntityCreationService $entityCreationService,
        private readonly ConnecteurFactory $connecteurFactory,
        private readonly FluxEntiteHeritageSQL $fluxEntiteHeritageSQL,
        private readonly ConnecteurCreationService $connecteurCreationService,
        private readonly ConnecteurAssociationService $connecteurAssociationService,
    ) {
    }

    public function getLastErrors(): array
    {
        return $this->lastErrors;
    }

    /**
     * @param array $exportedData
     * @param int $id_e_root
     * @throws DonneesFormulaireException
     */
    public function import(array $exportedData, int $id_e_root): void
    {
        $this->lastErrors = [];
        $id_e_mapping = $this->importEntity($exportedData, $id_e_root);
        $id_e_mapping = $this->importChildEntity($exportedData, $id_e_mapping, $id_e_root);
        $id_e_mapping[0] = 0;
        $connectorMapping = $this->importConnector($exportedData, $id_e_mapping, $id_e_root);
        $this->importAssociation($exportedData, $id_e_mapping, $connectorMapping, $id_e_root);
        $this->importAssociationInheritance($exportedData, $id_e_mapping, $id_e_root);
    }

    /**
     * @throws UnrecoverableException
     */
    private function importEntity(array $exportedData, int $id_e_root): array
    {
        $id_e_mapping = [];
        if (empty($exportedData[ExportConfigService::ENTITY_INFO])) {
            return $id_e_mapping;
        }
        $entityInfo = $exportedData[ExportConfigService::ENTITY_INFO];
        $id_e_entity = $this->entityCreationService->create(
            $entityInfo['denomination'],
            $entityInfo['siren'],
            $entityInfo['type'],
            $id_e_root
        );
        $id_e_mapping[$entityInfo['id_e']] = $id_e_entity;
        return $id_e_mapping;
    }

    /**
     * @throws UnrecoverableException
     */
    public function importChildEntity(array $exportedData, array $id_e_mapping, int $id_e_root): array
    {
        if (empty($exportedData[ExportConfigService::ENTITY_CHILD])) {
            return $id_e_mapping;
        }
        usort($exportedData[ExportConfigService::ENTITY_CHILD], static function (array $a, array $b) {
            return $a['id_e'] < $b['id_e'] ? -1 : 1;
        });
        foreach ($exportedData[ExportConfigService::ENTITY_CHILD] as $entity_child) {
            if (empty($id_e_mapping[$entity_child['entite_mere']])) {
                $this->lastErrors[] = "L'entité mère de {$entity_child['denomination']} est inconnue, l'entité sera attachée à l'entité $id_e_root.";
                $entity_child['entite_mere'] = $id_e_root;
            } else {
                $entity_child['entite_mere'] = $id_e_mapping[$entity_child['entite_mere']];
            }
            $id_e_entity = $this->entityCreationService->create(
                $entity_child['denomination'],
                $entity_child['siren'],
                $entity_child['type'],
                $entity_child['entite_mere'],
            );
            $id_e_mapping[$entity_child['id_e']] = $id_e_entity;
        }
        return $id_e_mapping;
    }

    /**
     * @param array $exportedData
     * @param array $id_e_mapping
     * @return array
     * @throws DonneesFormulaireException
     * @throws Exception
     */
    private function importConnector(array $exportedData, array $id_e_mapping, int $id_e_root): array
    {
        $connectorMapping = [];
        if (empty($exportedData[ExportConfigService::CONNECTOR_INFO])) {
            return $connectorMapping;
        }
        foreach ($exportedData[ExportConfigService::CONNECTOR_INFO] as $connecteurInfo) {
            if ($connecteurInfo['id_e'] !== 0 && empty($id_e_mapping[$connecteurInfo['id_e']])) {
                if ($id_e_root === 0) {
                    $this->lastErrors[] = sprintf(
                        "Le connecteur %s est attaché à une entité inconnue : il n'a pas été importé.",
                        $connecteurInfo['libelle']
                    );
                    continue;
                }
                $this->lastErrors[] = sprintf(
                    "Le connecteur %s est attaché à une entité inconnue : il sera attaché à l'entité %s.",
                    $connecteurInfo['libelle'],
                    $id_e_root
                );
                $id_e_mapping[$connecteurInfo['id_e']] = $id_e_root;
            }

            $isGlobalConnecteur = $connecteurInfo['global'] ?? (($connecteurInfo['id_e'] === 0) ? 1 : 0);
            $new_id_e_connecteur = $id_e_mapping[$connecteurInfo['id_e']];
            if ($id_e_root !== 0) {
                if ($isGlobalConnecteur) {
                    $this->lastErrors[] = sprintf(
                        "Le connecteur global %s ne peut pas être importé sur une entité fille : il n'a pas été importé.",
                        $connecteurInfo['libelle']
                    );
                    continue;
                }
                if ($connecteurInfo['id_e'] === 0) {
                    $new_id_e_connecteur = $id_e_root;
                }
            }

            $id_ce = $this->connecteurCreationService->createConnecteur(
                $connecteurInfo['id_connecteur'],
                $connecteurInfo['type'],
                $isGlobalConnecteur,
                $new_id_e_connecteur,
                0,
                $connecteurInfo['libelle']
            );
            $connectorMapping[$connecteurInfo['id_ce']] = $id_ce;
            $connecteurConfig = $this->connecteurFactory->getConnecteurConfig($id_ce);
            $connecteurConfig->jsonImport($connecteurInfo['data']);
        }
        return $connectorMapping;
    }

    private function importAssociation(array $exportedData, array $id_e_mapping, array $connectorMapping, int $id_e_root): void
    {
        if (empty($exportedData[ExportConfigService::ASSOCIATION_INFO])) {
            return;
        }
        foreach ($exportedData[ExportConfigService::ASSOCIATION_INFO] as $id_e => $fluxInfo) {
            if ($id_e !== 0 && empty($id_e_mapping[$id_e])) {
                if ($id_e_root === 0) {
                    $this->lastErrors[] = "L'entité du fichier d'import id_e=$id_e n'est pas présente : ces associations n'ont pas été importées.";
                    continue;
                }
                $this->lastErrors[] = "L'entité du fichier d'import id_e=$id_e n'est pas présente : associations importées sur l'entité $id_e_root.";
                $id_e = $id_e_root;
            }
            foreach ($fluxInfo as $flux_name => $connecteurInfo) {
                foreach ($connecteurInfo as $typeFlux => $listConnecteurInfo) {
                    foreach ($listConnecteurInfo as $theConnectorInfo) {
                        $this->associateConnector($theConnectorInfo, $id_e_mapping[$id_e], $flux_name, $typeFlux, $connectorMapping);
                    }
                }
            }
        }
    }

    /**
     * @throws Exception
     */
    private function associateConnector(
        array $theConnectorInfo,
        int $id_e,
        string $flux_name,
        string $typeFlux,
        array $connectorMapping
    ): void {
        if (empty($connectorMapping[$theConnectorInfo['id_ce']])) {
            $this->lastErrors[] = \sprintf(
                "La définition du connecteur id_ce=%s n'est pas présente : l'association n'a pas été importée.",
                $theConnectorInfo['id_ce']
            );
            return;
        }
        try {
            if ($id_e === 0 && $flux_name === 'global') {
                $this->connecteurAssociationService->addConnecteurAssociation(
                    $id_e,
                    $connectorMapping[$theConnectorInfo['id_ce']],
                    $typeFlux,
                );
            } else {
                $this->connecteurAssociationService->addConnecteurAssociation(
                    $id_e,
                    $connectorMapping[$theConnectorInfo['id_ce']],
                    $typeFlux,
                    0,
                    $flux_name,
                    $theConnectorInfo['num_same_type']
                );
            }
        } catch (UnrecoverableException $e) {
            //TODO: have a dedicated exception
            if (\str_starts_with($e->getMessage(), 'Le type de dossier « ')) {
                $this->lastErrors[] = $e->getMessage();
                return;
            }
        }
    }

    private function importAssociationInheritance(array $exportedData, array $id_e_mapping, int $id_e_root): void
    {
        if (empty($exportedData[ExportConfigService::ASSOCIATION_HERITAGE_INFO])) {
            return;
        }
        foreach ($exportedData[ExportConfigService::ASSOCIATION_HERITAGE_INFO] as $id_e => $heritage_list) {
            if (!isset($id_e_mapping[$id_e])) {
                if ($id_e_root === 0) {
                    $this->lastErrors[] = \sprintf(
                        "L'entité du fichier d'import id_e=%s n'est pas présente : " .
                        "les héritages d'associations n'ont pas été importées.",
                        $id_e
                    );
                    continue;
                }
                $this->lastErrors[] = \sprintf(
                    "L'entité du fichier d'import id_e=%s n'est pas présente : " .
                    "les héritages d'associations sont importées sur %s.",
                    $id_e,
                    $id_e_root
                );
                $id_e_mapping[$id_e] = $id_e_root;
            }
            foreach ($heritage_list as $flux) {
                $this->fluxEntiteHeritageSQL->setInheritance($id_e_mapping[$id_e], $flux);
            }
        }
    }
}
