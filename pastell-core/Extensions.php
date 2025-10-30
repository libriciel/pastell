<?php

class Extensions
{
    public const MODULE_FOLDER_NAME = "module";
    public const CONNECTEUR_FOLDER_NAME = "connecteur";
    public const CONNECTEUR_TYPE_FOLDER_NAME = "connecteur-type";
    public const TYPE_DOSSIER_FOLDER_NAME = "type-dossier";
    public const DATABASE_FILENAME = "database.json";


    public const PASTELL_ALL_MODULE_CACHE_KEY = "pastell_all_module";
    public const PASTELL_ALL_CONNECTEUR_CACHE_KEY = "pastell_all_connecteur";
    public const PASTELL_ALL_CONNECTEUR_TYPE_CACHE_KEY = "pastell_all_connecteur_type";
    public const PASTELL_CONNECTEUR_TYPE_PATH_CACHE_KEY = "pastell_connecteur_type";
    public const PASTELL_ALL_TYPE_DOSSIER_CACHE_KEY = "pastell_all_type_dossier";
    public const EXTENSIONS_SUBDIR = 'extensions';
    public const EXTENSION_BUILT_SUBDIR = 'build';

    private $extensionSQL;
    private $manifestFactory;
    private $pastell_path;

    private $memoryCache;
    private $cache_ttl_in_seconds;
    private $workspace_path;


    public function __construct(
        ExtensionSQL $extensionSQL,
        ManifestFactory $manifestFactory,
        string $pastell_path,
        MemoryCache $memoryCache,
        int $cache_ttl_in_seconds,
        string $workspacePath
    ) {
        $this->extensionSQL = $extensionSQL;
        $this->manifestFactory = $manifestFactory;
        $this->pastell_path = $pastell_path;
        $this->memoryCache = $memoryCache;
        $this->cache_ttl_in_seconds = $cache_ttl_in_seconds;
        $this->workspace_path = $workspacePath;
    }

    public function getAll()
    {
        $extensions_list = [];
        foreach ($this->extensionSQL->getAll() as $extension) {
            $extensions_list[$extension['id_e']] = $this->getInfo($extension['id_e']);
        }
        uasort($extensions_list, [$this,"compareExtension"]);
        return $extensions_list;
    }

    public function getAllDatabaseFile(): array
    {
        $result = [];
        foreach ($this->getAllExtensionsPath() as $extensionPath) {
            $databaseFilePath = sprintf("%s/%s", $extensionPath, self::DATABASE_FILENAME);
            if (file_exists($databaseFilePath)) {
                $result[] = $databaseFilePath;
            }
        }
        return $result;
    }

    private function compareExtension($a, $b)
    {
        return strcmp($a['nom'], $b['nom']);
    }

    public function getAllModule()
    {
        return $this->getAllElement(
            self::MODULE_FOLDER_NAME,
            self::PASTELL_ALL_MODULE_CACHE_KEY
        );
    }

    public function getModulePath($id_module_to_found)
    {
        $result = $this->getAllModule();
        if (empty($result[$id_module_to_found])) {
            return false;
        }
        return $result[$id_module_to_found];
    }

    public function getAllConnecteur()
    {
        return $this->getAllElement(
            self::CONNECTEUR_FOLDER_NAME,
            self::PASTELL_ALL_CONNECTEUR_CACHE_KEY
        );
    }

    public function getConnecteurPath($id_connecteur)
    {
        $result = $this->getAllConnecteur();
        if (empty($result[$id_connecteur])) {
            return false;
        }
        return $result[$id_connecteur];
    }

    public function getAllConnecteurType()
    {
        return $this->getAllElement(
            self::CONNECTEUR_TYPE_FOLDER_NAME,
            self::PASTELL_ALL_CONNECTEUR_TYPE_CACHE_KEY
        );
    }

    public function getAllTypeDossier()
    {
        return $this->getAllElement(
            self::TYPE_DOSSIER_FOLDER_NAME,
            self::PASTELL_ALL_TYPE_DOSSIER_CACHE_KEY
        );
    }

    public function getTypeDossierPath($type_etape)
    {
        $result = $this->getAllTypeDossier();
        if (empty($result[$type_etape])) {
            return false;
        }
        return $result[$type_etape];
    }

    private function getAllElement(string $extensions_sub_directory, string $element_cache_key): array
    {
        $result = $this->memoryCache->fetch($element_cache_key);
        if ($result) {
            return $result;
        }
        $result = [];
        foreach ($this->getAllExtensionsPath() as $search) {
            $glob_all = $this->globAll($search . "/" . $extensions_sub_directory . "/*");
            foreach ($glob_all as $id_connecteur) {
                $result[$id_connecteur] = $search . "/" . $extensions_sub_directory . "/$id_connecteur";
            }
        }
        $this->memoryCache->store(
            $element_cache_key,
            $result,
            $this->cache_ttl_in_seconds
        );
        return $result;
    }


    private function getAllExtensionsPath(): array
    {
        $to_search = [$this->pastell_path];

        $localExtensions = $this->globAll(sprintf("%s/%s/*", $this->pastell_path, self::EXTENSIONS_SUBDIR));

        foreach ($localExtensions as $localExtension) {
            $extensionBuildPath = sprintf(
                "%s/%s/%s/%s",
                $this->pastell_path,
                self::EXTENSIONS_SUBDIR,
                $localExtension,
                self::EXTENSION_BUILT_SUBDIR
            );
            if (\file_exists($extensionBuildPath)) {
                $to_search[] = $extensionBuildPath;
            }
        }

        $to_search[] = $this->workspace_path . "/" . TypeDossierPersonnaliseDirectoryManager::SUB_DIRECTORY;
        foreach ($this->extensionSQL->getAll() as $extension) {
            $to_search[] = $extension['path'];
        }
        return $to_search;
    }

    public function autoloadExtensions(): void
    {
        $extensions = $this->getAllExtensionsPath();
        // Remove pastell root path
        unset($extensions[0]);
        foreach ($extensions as $extension) {
            if (\file_exists($extension . '/autoload.php')) {
                require_once $extension . '/autoload.php';
            } elseif (\file_exists($extension . '/vendor/autoload.php')) {
                require_once $extension . '/vendor/autoload.php';
            }
        }
    }

    /**
     * @throws Exception
     */
    public function getInfo($id_e, $path = null)
    {
        if ($path) {
            $info = $this->getInfoFromPath($path);
        } else {
            $info = $this->extensionSQL->getInfo($id_e);
            if ($info === false) {
                throw new Exception("L'extension $id_e n'existe pas");
            }
            $info = $this->getInfoFromPath($info['path']);
        }
        $info['error'] = false;
        $info['warning'] = false;
        $info['pastell-version-ok'] = true;

        $info['id_e'] = $id_e;
        if (! file_exists($info['path'])) {
            $info['error'] = "Extension non trouvée";
            $info['error-detail'] = "L'emplacement {$info['path']} n'a pas été trouvé sur le système de fichier";
            return $info;
        }
        if (empty($info['manifest']['nom'])) {
            $info['warning'] = "manifest.yml absent";
            $info['warning-detail'] = "Le fichier manifest.yml n'a pas été trouvé dans {$info['path']}";
            return $info;
        }

        $pastellManifest = $this->manifestFactory->getPastellManifest();

        if (! $pastellManifest->isVersionOK($info['manifest']['pastell-version'])) {
            $info['warning'] = "Version de pastell incorrecte";
            $info['warning-detail'] = "Ce module attend une version de Pastell ({$info['manifest']['pastell-version']}) non prise en charge par ce Pastell";
            $info['pastell-version-ok'] = false;
            return $info;
        }
        $extension_absente = [];
        $extension_bad_version = [];
        foreach ($info['manifest']['extension_needed'] as $extension_needed => $extension_needed_info) {
            $info['manifest']['extension_needed'][$extension_needed] = $this->checkExtensionNeeded($extension_needed, $extension_needed_info);
            if (! $info['manifest']['extension_needed'][$extension_needed]['extension_presente']) {
                $extension_absente[] = $extension_needed;
            } elseif (! $info['manifest']['extension_needed'][$extension_needed]['extension_version_ok']) {
                $extension_bad_version[] = $extension_needed;
            }
        }

        if ($extension_absente) {
            $info['warning'] = "Extensions(s) manquante(s)";
            $info['warning-detail'] = "Cette extension dépend d'autres extensions qui ne sont pas installés sur cette instance de Pastell : " . implode(', ', $extension_absente);
            return $info;
        }
        if ($extension_bad_version) {
            $info['warning'] = "Mauvais numéro de version d'une dépendance";
            $info['warning-detail'] = "Ce extension dépend d'autres extensions qui ne sont pas dans une version attendue : " . implode(', ', $extension_bad_version);
            return $info;
        }

        return $info;
    }

    private function checkExtensionNeeded($extension_needed, $extension_needed_info)
    {
        $extension_needed_info['extension_presente'] = false;
        $extension_needed_info['extension_version_ok'] = false;
        $info = $this->getInfoFromId($extension_needed);
        if (! $info) {
            return $extension_needed_info;
        }

        $extension_needed_info['extension_presente'] = true;

        if (empty($extension_needed_info['version'])) {
            return $extension_needed_info;
        }
        if (empty($info['manifest']['extensions_versions_accepted'])) {
            return $extension_needed_info;
        }

        foreach ($info['manifest']['extensions_versions_accepted'] as $version_accepted) {
            if ($version_accepted == $extension_needed_info['version']) {
                $extension_needed_info['extension_version_ok'] = true;
                return $extension_needed_info;
            }
        }

        return $extension_needed_info;
    }

    private function getInfoFromId($extension_id)
    {
        foreach ($this->extensionSQL->getAll() as $extension) {
            $info = $this->getInfoFromPath($extension['path']);
            if ($info['id'] == $extension_id) {
                return $info;
            }
        }
        return false;
    }

    private function getInfoFromPath($path)
    {
        $result['path'] = $path;
        $result['flux'] = $this->getAllModuleByPath($path);
        $result['connecteur'] = $this->getAllConnecteurByPath($path);
        $result['connecteur-type'] = $this->getAllConnecteurTypeByPath($path);
        $result['type_dossier'] = $this->getAllStepTypesByPath($path);
        $manifest = $this->getManifest($path);
        $result['manifest'] = $manifest;
        $result['id'] = $manifest['id'] ?? basename($path);
        $result['nom'] = $manifest['nom'] ?? $result['id'];
        return $result;
    }

    private function getManifest($path)
    {
        try {
            $manifest = $this->manifestFactory->getManifest($path);
        } catch (Exception $e) {
            return false;
        }
        return $manifest->getInfo();
    }

    private function getAllModuleByPath($path): array
    {
        return $this->globAll($path . '/' . self::MODULE_FOLDER_NAME . '/*');
    }

    private function getAllConnecteurByPath($path): array
    {
        return $this->globAll($path . '/' . self::CONNECTEUR_FOLDER_NAME . '/*');
    }

    private function getAllConnecteurTypeByPath($path): array
    {
        return $this->globAll($path . '/' . self::CONNECTEUR_TYPE_FOLDER_NAME . '/*');
    }

    private function getAllStepTypesByPath($path): array
    {
        return $this->globAll($path . '/' . self::TYPE_DOSSIER_FOLDER_NAME . '/*');
    }

    private function globAll($glob_expression)
    {
        $result = [];
        foreach (glob($glob_expression) as $file_config) {
            if (is_dir($file_config)) {
                $result[] = basename($file_config);
            }
        }
        return $result;
    }
}
