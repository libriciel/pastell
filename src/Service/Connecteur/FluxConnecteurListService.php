<?php

declare(strict_types=1);

namespace Pastell\Service\Connecteur;

use DocumentType;
use DocumentTypeFactory;
use FluxDefinitionFiles;
use Pastell\Service\Entite\RootEntity;

final class FluxConnecteurListService
{
    private const MAX_DISPLAYED_CONNECTEUR_TYPE = 4;
    private const CONNECTEUR_TYPE_SEPARATOR = ', ';

    public function __construct(
        private readonly FluxInheritanceService $fluxInheritanceService,
        private readonly DocumentTypeFactory $documentTypeFactory,
        private readonly FluxDefinitionFiles $fluxDefinitionFiles,
    ) {
    }

    public function getConnecteurList(int $id_e, string $flux): array
    {
        $association_list = $this->fluxInheritanceService->getAllWithSameType($id_e);
        return $this->buildConnecteurList($flux, $association_list[$flux] ?? []);
    }

    public function getFluxListWithConnecteur(int $id_e): array
    {
        $association_list = $this->fluxInheritanceService->getAllWithSameType($id_e);

        $flux_list = [];
        foreach ($this->fluxDefinitionFiles->getAll() as $flux => $flux_definition) {
            $connecteur_list = $this->buildConnecteurList($flux, $association_list[$flux] ?? []);
            $connecteur_type_list = $this->getAssociatedConnecteurTypeList($connecteur_list);
            if ($connecteur_type_list === []) {
                continue;
            }
            unset($flux_definition['formulaire'], $flux_definition['action']);
            $flux_definition['nb_connector'] = count($connecteur_type_list);
            $flux_definition['famille_associe'] = $connecteur_type_list;
            $flux_list[$flux] = array_merge($flux_definition, $this->getConnecteurTypeDisplay($connecteur_type_list));
        }
        return $flux_list;
    }

    public function getPossiblePackList(array $module_list): array
    {
        $flux_definition_list = $this->fluxDefinitionFiles->getAll();

        $pack_list = [];
        foreach ($module_list as $flux => $module_info) {
            if (!empty($flux_definition_list[$flux]['connecteur'])) {
                $pack_list[$module_info['type']][$flux] = $module_info;
            }
        }
        return $pack_list;
    }

    private function buildConnecteurList(string $flux, array $flux_association_list): array
    {
        $documentType = $this->documentTypeFactory->getFluxDocumentType($flux);
        $nb_connecteur = count($documentType->getConnecteur());

        $connecteur_list = [];
        foreach ($documentType->getConnecteurAllInfo() as $num_connecteur => $connecteur_type_info) {
            $connecteur_type = $connecteur_type_info[DocumentType::CONNECTEUR_ID];
            $num_same_type = $connecteur_type_info[DocumentType::NUM_SAME_TYPE];

            $connecteur_info = $flux_association_list[$connecteur_type][$num_same_type] ?? false;
            if ($connecteur_info !== false) {
                $connecteur_info = RootEntity::addDenomination([$connecteur_info])[0];
            }

            $connecteur_list[] = [
                'nb_connecteur' => $nb_connecteur,
                'num_connecteur' => $num_connecteur,
                'id_flux' => $flux,
                'nom_flux' => $documentType->getName(),
                'connecteur_type' => $connecteur_type,
                DocumentType::CONNECTEUR_WITH_SAME_TYPE =>
                    $connecteur_type_info[DocumentType::CONNECTEUR_WITH_SAME_TYPE],
                DocumentType::NUM_SAME_TYPE => $num_same_type,
                'inherited_flux' => $flux_association_list['inherited_flux'] ?? false,
                'connecteur_info' => $connecteur_info,
            ];
        }
        return $connecteur_list;
    }

    private function getAssociatedConnecteurTypeList(array $connecteur_list): array
    {
        $connecteur_type_list = [];
        foreach ($connecteur_list as $connecteur) {
            if ($connecteur['connecteur_info']) {
                $connecteur_type_list[] = $connecteur['connecteur_info']['type'];
            }
        }
        return $connecteur_type_list;
    }

    private function getConnecteurTypeDisplay(array $connecteur_type_list): array
    {
        $all_connecteur_type = implode(self::CONNECTEUR_TYPE_SEPARATOR, $connecteur_type_list);
        if (count($connecteur_type_list) <= self::MAX_DISPLAYED_CONNECTEUR_TYPE) {
            return [
                'affiche_hover' => false,
                'famille_associe_affiche' => $all_connecteur_type,
            ];
        }

        $displayed_connecteur_type_list = array_slice($connecteur_type_list, 0, self::MAX_DISPLAYED_CONNECTEUR_TYPE);
        $displayed_connecteur_type = implode(self::CONNECTEUR_TYPE_SEPARATOR, $displayed_connecteur_type_list);
        return [
            'affiche_hover' => true,
            'hover' => $all_connecteur_type,
            'famille_associe_affiche' => $displayed_connecteur_type . self::CONNECTEUR_TYPE_SEPARATOR . '...',
        ];
    }
}
