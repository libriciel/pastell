<?php

use Pastell\Service\TypeDossier\TypeDossierImportService;
use Pastell\Service\TypeDossier\TypeDossierUtilService;
use Pastell\Service\Droit\DroitType;
use Pastell\Service\Droit\DroitService;

class TypeDossierLoader
{
    private $workspacePath;
    private $memoryCache;
    private $extensionLoader;
    private $roleSQL;

    private $tmp_folder;
    private $typeDossierImportService;

    public function __construct(
        $workspacePath,
        MemoryCache $memoryCache,
        ExtensionLoader $extensionLoader,
        RoleSQL $roleSQL,
        TypeDossierImportService $typeDossierImportService
    ) {
        $this->workspacePath = $workspacePath;
        $this->memoryCache = $memoryCache;
        $this->extensionLoader = $extensionLoader;
        $this->roleSQL = $roleSQL;
        $this->typeDossierImportService = $typeDossierImportService;
    }

    /**
     * La fonction glob() permet pas de rechercher dans le VFS, du coup, la génération dynamique
     * des fichiers de definition YAML n'est pas opérante à travers DocumentTypeFactory...
     *
     * Contournement : on réécrit le fichier quelque part et on charge le module...
     *
     * @param $type_dossier
     * @throws TypeDossierException
     */
    public function createTypeDossierDefinitionFile($type_dossier)
    {
        $this->createTypeDossierFromFilepath(__DIR__ . "/fixtures/{$type_dossier}.json");
    }

    /**
     * La fonction glob() permet pas de rechercher dans le VFS, du coup, la génération dynamique
     * des fichiers de definition YAML n'est pas opérante à travers DocumentTypeFactory...
     *
     * Contournement : on réécrit le fichier quelque part et on charge le module...
     *
     * @param $definition_filepath
     * @throws TypeDossierException
     * @throws Exception
     */
    public function createTypeDossierFromFilepath($definition_filepath)
    {
        $tmpFolder = new TmpFolder();
        $this->tmp_folder = $tmpFolder->create();

        $info = $this->typeDossierImportService->importFromFilePath($definition_filepath);

        $type_dossier = $info[TypeDossierUtilService::ID_TYPE_DOSSIER];
        mkdir($this->tmp_folder . "/module/$type_dossier/", 0777, true);
        copy(
            $this->workspacePath . "/" . TypeDossierPersonnaliseDirectoryManager::SUB_DIRECTORY . "/module/{$type_dossier}/definition.yml",
            $this->tmp_folder . "/module/{$type_dossier}/definition.yml"
        );

        $this->extensionLoader->loadExtension([$this->tmp_folder]);

        $this->roleSQL->addDroit('admin', DroitService::getDroitFor($type_dossier, DroitType::LECTURE));
        $this->roleSQL->addDroit('admin', DroitService::getDroitFor($type_dossier, DroitType::EDITION));
        $this->memoryCache->flushAll();
    }

    public function unload()
    {
        if (!$this->tmp_folder) {
            return;
        }
        $tmpFolder = new TmpFolder();
        $tmpFolder->delete($this->tmp_folder);
        $this->memoryCache->delete('pastell_all_module');
    }
}
