<?php

abstract class ConnecteurTypeActionExecutor extends ActionExecutor
{
    protected $mapping;
    private array $transformations;
    protected $data_seda_class_name;

    public function setMapping(array $mapping)
    {
        $this->mapping = $mapping;
    }

    public function getMappingValue($key)
    {
        if (empty($this->mapping[$key])) {
            return $key;
        }
        return $this->mapping[$key];
    }

    public function getMappingValueList($key): array
    {
        return array_values(array_filter((array)$this->getMappingValue($key)));
    }

    public function setTransformations(array $transformations): void
    {
        $this->transformations = $transformations;
    }
    public function getTransformations(): array
    {
        return $this->transformations;
    }

    public function setDataSedaClassName($data_seda_class_name)
    {
        $this->data_seda_class_name = $data_seda_class_name;
    }

    public function getDataSedaClassName()
    {
        return $this->data_seda_class_name;
    }

    public function getDataSedaClassPath()
    {
        $extensions = $this->objectInstancier->getInstance(Extensions::class);

        foreach ($extensions->getAllModule() as $module_id => $module_path) {
            $fichier_recherche = $module_path . "/lib/" . $this->data_seda_class_name . ".php";
            if (file_exists($fichier_recherche)) {
                return $fichier_recherche;
            }
        }
        return false;
    }
}
