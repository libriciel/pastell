<?php

//Responsabilité: Appeller les bons objects qui connaissent l'emplacement des fichier de conf
//et construire un DocumentType
//(documents, entités, propriétés globales)
class DocumentTypeFactory
{
    private $allType;

    public function __construct(
        private readonly ConnecteurDefinitionFiles $connecteurDefinitionFiles,
        private readonly FluxDefinitionFiles $fluxDefinitionFiles
    ) {
    }

    public function getGlobalDocumentType($id_connecteur): DocumentType
    {
        $connecteur_definition = $this->connecteurDefinitionFiles->getInfoGlobal($id_connecteur);
        if (!$connecteur_definition) {
            return new DocumentType($id_connecteur, $this->connecteurDefinitionFiles->getInfo('empty'));
        }
        return new DocumentType($id_connecteur, $connecteur_definition);
    }

    public function getEntiteDocumentType($id_connecteur): DocumentType
    {
        $connecteur_definition = $this->connecteurDefinitionFiles->getInfo($id_connecteur);
        if (!$connecteur_definition) {
            return new DocumentType($id_connecteur, $this->connecteurDefinitionFiles->getInfo('empty'));
        }
        return new DocumentType($id_connecteur, $connecteur_definition);
    }

    public function getFluxDocumentType($id_flux): DocumentType
    {
        $flux_definition = $this->getDocumentTypeArray($id_flux);
        if (!$flux_definition) {
            return new DocumentType($id_flux, []);
        }
        return new DocumentType($id_flux, $flux_definition);
    }

    public function getDocumentTypeArray($id_flux)
    {
        return $this->fluxDefinitionFiles->getInfo($id_flux);
    }

    public function getAllType()
    {
        if ($this->allType) {
            return $this->allType;
        }
        $all_type = [];
        foreach ($this->fluxDefinitionFiles->getAll() as $id_flux => $properties) {
            $documentType = $this->getFluxDocumentType($id_flux);
            $type = $documentType->getType();
            $all_type[$type][$id_flux] = $documentType->getName();
        }
        foreach ($all_type as $type => $flux) {
            asort($all_type[$type]);
        }
        asort($all_type);

        $this->allType = $all_type;
        return $this->allType;
    }

    public function isSuperTypePresent($type)
    {
        $all = $this->getAllType();
        return isset($all[$type]);
    }

    public function isTypePresent($type)
    {
        $all = $this->fluxDefinitionFiles->getAll();
        return isset($all[$type]);
    }

    /**
     * @param string $id_flux
     * @return bool
     */
    public function isRestrictedFlux(string $id_flux): bool
    {
        return in_array($id_flux, $this->fluxDefinitionFiles->getAllRestricted());
    }

    /**
     * @param array $list_flux
     * @return array
     */
    public function clearRestrictedFlux(array $list_flux): array
    {
        foreach ($list_flux as $id_flux => $values) {
            if ($this->isRestrictedFlux($id_flux)) {
                unset($list_flux[$id_flux]);
            }
        }
        return $list_flux;
    }

    /**
     * @param string $id_connecteur
     * @param bool $global
     * @return bool
     */

    public function isRestrictedConnecteur(string $id_connecteur, bool $global = false): bool
    {
        return $global ?
            in_array($id_connecteur, $this->connecteurDefinitionFiles->getAllRestricted(true)) :
            in_array($id_connecteur, $this->connecteurDefinitionFiles->getAllRestricted());
    }

    public function getActionByRole($allDroit)
    {
        $allType = [];
        $result = [];
        foreach ($allDroit as $droit) {
            $r = explode(":", $droit);
            $allType[$r[0]] = true;
        }
        $allType = array_keys($allType);
        foreach ($allType as $typeName) {
            try {
                $action = $this->getFluxDocumentType($typeName)->getAction();
            } catch (Exception $e) {
                continue;
            }
            $a_wf = $action->getWorkflowAction();
            if ($a_wf) {
                $result[$typeName] = $a_wf;
            }
        }
        return $result;
    }

    public function getAllDroit()
    {
        $list_droit = [];
        foreach ($this->fluxDefinitionFiles->getAll() as $id_flux => $properties) {
            $documentType = $this->getFluxDocumentType($id_flux);
            $list_droit = array_merge($list_droit, $documentType->getListDroit());
        }
        sort($list_droit);
        return $list_droit;
    }
}
