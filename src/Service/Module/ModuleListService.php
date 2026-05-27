<?php

declare(strict_types=1);

namespace Pastell\Service\Module;

use DocumentTypeFactory;
use Pastell\Service\Droit\DroitService;

final readonly class ModuleListService
{
    public function __construct(
        private DocumentTypeFactory $documentTypeFactory,
        private DroitService $droitService,
    ) {
    }

    public function getModuleListOrderByNom(int $id_u, bool $hasAllDroit = false): array
    {
        $moduleList = [];
        $allDocType = $this->documentTypeFactory->getAllType();
        foreach ($allDocType as $typeFlux => $listFlux) {
            foreach ($listFlux as $idFlux => $libelleFlux) {
                if ($hasAllDroit || $this->droitService->hasOneDroit($id_u, DroitService::getDroitLecture($idFlux))) {
                    $moduleList[$idFlux]  = ['type' => $typeFlux,'nom' => $libelleFlux];
                }
            }
        }

        $currentLocale = setlocale(LC_COLLATE, '0');
        setlocale(LC_COLLATE, 'fr_FR.utf8');
        uasort($moduleList, static function (array $a, array $b) {
            return strcoll($a['nom'], $b['nom']);
        });
        setlocale(LC_COLLATE, $currentLocale);

        return $moduleList;
    }

    public function toTreeselectOptions(int $id_u, bool $hasAllDroit = false): array
    {
        $tree = [];
        foreach ($this->getModuleListOrderByType($id_u, $hasAllDroit) as $type => $modules) {
            $children = [];
            foreach ($modules as $idFlux => $nom) {
                $children[] = ['name' => $nom, 'value' => $idFlux];
            }
            $tree[] = ['name' => $type, 'value' => $type, 'isGroupSelectable' => false, 'children' => $children];
        }
        return $tree;
    }

    public function getModuleListOrderByType(int $id_u, bool $hasAllDroit = false): array
    {
        $moduleListByType = [];
        $moduleList = $this->getModuleListOrderByNom($id_u, $hasAllDroit);
        foreach ($moduleList as $idFlux => $infoFlux) {
            $moduleListByType[$infoFlux['type']][$idFlux] = $infoFlux['nom'];
        }

        $currentLocale = setlocale(LC_COLLATE, '0');
        setlocale(LC_COLLATE, 'fr_FR.utf8');
        ksort($moduleListByType, SORT_LOCALE_STRING);
        setlocale(LC_COLLATE, $currentLocale);

        return $moduleListByType;
    }
}
