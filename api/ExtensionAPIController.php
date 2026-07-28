<?php

use Pastell\Service\Droit\DroitService;
use Pastell\Service\Droit\DroitType;

class ExtensionAPIController extends BaseAPIController
{
    public function __construct(
        private readonly Extensions $extensions,
        private readonly ExtensionSQL $extensionSQL,
    ) {
    }

    /**
     * @throws ForbiddenException
     * @throws NotFoundException
     */
    public function get(): array
    {
        $this->checkDroitFor(EntiteSQL::ID_E_ENTITE_RACINE, DroitService::DROIT_SYSTEM, DroitType::LECTURE);
        $id_extension = $this->getFromQueryArgs(0);
        if ($id_extension) {
            if (! $this->extensionSQL->getInfo($id_extension)) {
                throw new NotFoundException("L'extension #{$id_extension} n'existe pas.");
            }
            return $this->extensions->getInfo($id_extension);
        }
        $extensions = $this->extensions->getAll();
        foreach ($extensions as &$extension) {
            $extension['id_e'] = (string)$extension['id_e'];
        }
        $result['result'] = $extensions;
        return $result;
    }

    /**
     * @throws ConflictException
     * @throws ForbiddenException
     * @throws Exception
     */
    public function post(): array
    {
        $this->checkDroitFor(EntiteSQL::ID_E_ENTITE_RACINE, DroitService::DROIT_SYSTEM, DroitType::EDITION);
        $path = $this->getFromRequest('path');
        if (! file_exists($path)) {
            throw new Exception("Le chemin « $path » n'existe pas sur le système de fichier");
        }
        $detail_extension = $this->extensions->getInfo(0, $path);
        $extension_list = $this->extensions->getAll();

        foreach ($extension_list as $id_e => $extension) {
            if (($extension['id'] == $detail_extension['id']) && !($extension['id_e'] == $detail_extension['id_e'])) {
                throw new ConflictException("L'extension #{$detail_extension['id']} est déja présente");
            }
        }
        $id_extension = $this->extensionSQL->edit(0, $path);
        $detail_extension['id_e'] = (string)$detail_extension['id_e'];
        return ['id_extension' => (string)$id_extension,'detail' => $detail_extension];
    }

    /**
     * @return array
     * @throws NotFoundException
     * @throws Exception
     */
    public function patch()
    {
        $this->checkDroitFor(EntiteSQL::ID_E_ENTITE_RACINE, DroitService::DROIT_SYSTEM, DroitType::EDITION);
        $id_extension = $this->getFromQueryArgs(0);
        if (! $id_extension || ! $this->extensionSQL->getInfo($id_extension)) {
            throw new NotFoundException("Extension #$id_extension non trouvée");
        }
        $path = $this->getFromRequest('path');
        if (! file_exists($path)) {
            throw new Exception("Le chemin « $path » n'existe pas sur le système de fichier");
        }

        $detail_extension = $this->extensions->getInfo($id_extension, $path);
        $extension_list = $this->extensions->getAll();

        foreach ($extension_list as $id_e => $extension) {
            if (($extension['id'] == $detail_extension['id']) && !($extension['id_e'] == $detail_extension['id_e'])) {
                throw new Exception("L'extension #{$detail_extension['id']} est déja présente");
            }
        }
        $this->extensionSQL->edit($id_extension, $path); // ajout ou modification

        return ['id_extension' => $id_extension,'detail' => $detail_extension];
    }

    /**
     * @return mixed
     * @throws ForbiddenException
     * @throws NotFoundException
     */
    public function delete()
    {
        $this->checkDroitFor(EntiteSQL::ID_E_ENTITE_RACINE, DroitService::DROIT_SYSTEM, DroitType::EDITION);
        $id_extension = $this->getFromQueryArgs(0);
        if (! $id_extension || ! $this->extensionSQL->getInfo($id_extension)) {
            throw new NotFoundException("Extension #$id_extension non trouvée");
        }
        $this->extensionSQL->delete($id_extension);
        $result['result'] = self::RESULT_OK;
        return $result;
    }


    /**
     * @return mixed
     * @throws ForbiddenException
     * @throws Exception
     */
    public function compatV1Edition()
    {
        $this->checkDroitFor(EntiteSQL::ID_E_ENTITE_RACINE, DroitService::DROIT_SYSTEM, DroitType::EDITION);

        $id_extension = $this->getFromRequest('id_extension');
        $path = $this->getFromRequest('path');

        if (! file_exists($path)) {
            throw new Exception("Le chemin « $path » n'existe pas sur le système de fichier");
        }
        if ($id_extension) {
            $info_extension = $this->extensionSQL->getInfo($id_extension);
            if (!$info_extension) {
                throw new Exception("L'extension #{$id_extension} est introuvable");
            }
        }

        $detail_extension = $this->extensions->getInfo($id_extension, $path);
        $extension_list = $this->extensions->getAll();

        foreach ($extension_list as $id_e => $extension) {
            if (($extension['id'] == $detail_extension['id']) && !($extension['id_e'] == $detail_extension['id_e'])) {
                throw new Exception("L'extension #{$detail_extension['id']} est déja présente");
            }
        }
        $this->extensionSQL->edit($id_extension, $path); // ajout ou modification

        $result['detail_extension'] = $detail_extension;
        $result['result'] = self::RESULT_OK;
        return $result;
    }
}
