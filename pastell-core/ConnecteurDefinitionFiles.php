<?php

declare(strict_types=1);

//Chargé des fichiers entite-properties.yml et global-properties.yml

use Pastell\Configuration\ConnectorConfiguration;
use Pastell\Service\Pack\PackService;

class ConnecteurDefinitionFiles
{
    public const ENTITE_PROPERTIES_FILENAME = 'entite-properties.yml';
    public const GLOBAL_PROPERTIES_FILENAME = 'global-properties.yml';

    private ?array $allTypes = null;

    public function __construct(
        private readonly Extensions $extensions,
        private readonly YMLLoader $yml_loader,
        private readonly PackService $packService,
    ) {
    }

    public function getAllConnecteursEntite(bool $isEntiteRacine = false): array
    {
        return $this->getAllConnecteurByFile(self::ENTITE_PROPERTIES_FILENAME, $isEntiteRacine);
    }

    public function getAllConnecteursGlobaux(): array
    {
        return $this->getAllConnecteurByFile(self::GLOBAL_PROPERTIES_FILENAME);
    }

    private function getAllConnecteurByFile(string $file_name, bool $isEntiteRacine = false): array
    {
        $result = [];
        foreach ($this->extensions->getAllConnecteur() as $id_connecteur => $connecteur_path) {
            $definition_file_path = $connecteur_path . '/' . $file_name;
            if (file_exists($definition_file_path)) {
                $connecteur_definition = $this->yml_loader->getArray($definition_file_path);
                if (!is_array($connecteur_definition) || $this->isRestrictedConnecteur($connecteur_definition)) {
                    continue;
                }

                if ($isEntiteRacine && !$this->isAllowedOnEntiteRacine($connecteur_definition)) {
                    continue;
                }

                $result[$id_connecteur] = $connecteur_definition;
            }
        }
        uasort($result, [$this, 'sortConnecteur']);
        return $result;
    }

    public function isAllowedOnEntiteRacine(array $connecteur_definition): bool
    {
        $allowOnEntiteRacine = $connecteur_definition[ConnectorConfiguration::ALLOW_ON_ENTITE_RACINE] ?? false;
        return $allowOnEntiteRacine === true;
    }

    public function isAllowedOnEntiteRacineById(string $id_connecteur): bool
    {
        $connecteur_definition = $this->getInfo($id_connecteur);
        if (!$connecteur_definition) {
            return false;
        }
        return $this->isAllowedOnEntiteRacine($connecteur_definition);
    }

    public function getAllDefinitionPath(string $filePath): array
    {
        $result = [];
        foreach ($this->extensions->getAllConnecteur() as $id_connecteur => $connecteur_path) {
            $entitiesDefinitionFilePath = $connecteur_path . '/' . $filePath;
            if (file_exists($entitiesDefinitionFilePath)) {
                $result[$id_connecteur]  = $entitiesDefinitionFilePath;
            }
        }
        return $result;
    }

    private function sortConnecteur(array $a, array $b): int
    {
        return strcasecmp($a[ConnectorConfiguration::NOM], $b[ConnectorConfiguration::NOM]);
    }


    public function getAllType(): array
    {
        if ($this->allTypes === null) {
            $this->allTypes = $this->getAllTypeByDef($this->getAllConnecteursEntite());
        }
        return $this->allTypes;
    }

    public function getAllGlobalType(): array
    {
        return $this->getAllTypeByDef($this->getAllConnecteursGlobaux());
    }

    private function getAllTypeByDef(array $connecteur_definition): array
    {
        $result = [];
        foreach ($connecteur_definition as $def) {
            $result[$def[ConnectorConfiguration::TYPE]] = 1;
        }
        $result = array_keys($result);

        usort($result, 'strcasecmp');
        return $result;
    }

    /**
     * @param int<0,1> $global
     */
    public function getInfo(string $id_connecteur, int $global = 0): bool|array
    {
        if ($global) {
            return $this->getInfoGlobal($id_connecteur);
        }
        $connecteur_path = $this->extensions->getConnecteurPath($id_connecteur);
        $array =  $this->yml_loader->getArray("$connecteur_path/" . self::ENTITE_PROPERTIES_FILENAME);

        if (isset($array['heritage'])) {
            $heritage_array = $this->yml_loader->getArray(PASTELL_PATH . "/common-yaml/{$array['heritage']}.yml");
            if ($heritage_array) {
                $array = array_merge_recursive($heritage_array, $array);
            }
        }
        return $array;
    }

    public function getInfoGlobal(string $id_connecteur): bool|array
    {
        $connecteur_path = $this->extensions->getConnecteurPath($id_connecteur);
        return $this->yml_loader->getArray("$connecteur_path/" . self::GLOBAL_PROPERTIES_FILENAME);
    }

    /**
     * @param string $id_connecteur
     * @return mixed
     * @throws Exception
     */
    public function getConnecteurClass(string $id_connecteur): mixed
    {
        $connecteur_path = $this->extensions->getConnecteurPath($id_connecteur);
        $all = glob("$connecteur_path/*.php");
        if (! $all) {
            throw new Exception("Impossible de trouver une classe pour le connecteur $id_connecteur");
        }
        $class_file = $all[0];
        $class_name = basename($class_file, '.php');
        if (!class_exists($class_name, false)) {
            require_once($class_file);
        }
        return $class_name;
    }

    public function getAllByFamille(string $famille_connecteur, bool $global = false): array
    {
        $result = [];
        $all_connectors = $global ? $this->getAllConnecteursGlobaux() : $this->getAllConnecteursEntite();
        foreach ($all_connectors as $connecteur_id => $connecteur_properties) {
            if ($connecteur_properties['type'] === $famille_connecteur) {
                $result[$connecteur_id] = true;
            }
        }
        $result = array_keys($result);
        usort($result, 'strcasecmp');
        return $result;
    }

    /**
     * @param bool $global
     * @return array
     */
    public function getAllRestricted(bool $global = false): array
    {
        if ($global) {
            return $this->getAllRestrictedGlobal();
        }
        return $this->getAllRestrictedByFile(self::ENTITE_PROPERTIES_FILENAME);
    }

    /**
     * @return array
     */
    private function getAllRestrictedGlobal(): array
    {
        return $this->getAllRestrictedByFile(self::GLOBAL_PROPERTIES_FILENAME);
    }

    /**
     * @param string $file_name
     * @return array
     */
    private function getAllRestrictedByFile(string $file_name): array
    {
        $result = [];
        foreach ($this->extensions->getAllConnecteur() as $id_connecteur => $connecteur_path) {
            $definition_file_path = $connecteur_path . '/' . $file_name;
            if (file_exists($definition_file_path)) {
                $connecteur_definition = $this->yml_loader->getArray($definition_file_path);
                if ($connecteur_definition && $this->isRestrictedConnecteur($connecteur_definition)) {
                    $result[] = $id_connecteur;
                }
            }
        }
        return $result;
    }

    /**
     * @param array $connecteur_definition
     * @return bool
     */
    private function isRestrictedConnecteur(array $connecteur_definition = []): bool
    {
        $restriction_pack = $connecteur_definition[ConnectorConfiguration::RESTRICTION_PACK] ?? [];
        return (! $this->packService->hasOneOrMorePackEnabled($restriction_pack));
    }

    public function getDefinitionPath(string $connectorId, bool $isGlobal = false): string
    {
        $connectorPath = $this->extensions->getConnecteurPath($connectorId);
        if ($isGlobal) {
            return $connectorPath . '/' . self::GLOBAL_PROPERTIES_FILENAME;
        }
        return $connectorPath . '/' . self::ENTITE_PROPERTIES_FILENAME;
    }
}
