<?php

use Pastell\Service\Droit\DroitService;
use Pastell\Service\Droit\DroitType;

abstract class BaseAPIController
{
    public const RESULT_OK = "ok";

    private $id_u;
    private $request = [];

    private $caller_type;


    /** @var RoleUtilisateur */
    private $roleUtilisateur;

    /** @var  DroitService */
    private $droitService;

    /** @var  FileUploader */
    private $fileUploader;

    private $query_args;

    private $hasAllDroit;

    public function setQueryArgs(array $query_args)
    {
        $this->query_args = $query_args;
    }

    public function getFromQueryArgs($place_number)
    {
        return $this->query_args[$place_number] ?? false;
    }

    public function setAllDroit($hasAllDroit = false)
    {
        $this->hasAllDroit = $hasAllDroit;
    }

    public function setCallerType($caller_type)
    {
        $this->caller_type = $caller_type;
    }

    public function getCallerType()
    {
        return $this->caller_type;
    }

    public function setFileUploader(FileUploader $fileUploader)
    {
        $this->fileUploader = $fileUploader;
    }

    public function getFileUploader()
    {
        return $this->fileUploader;
    }

    public function setRoleUtilisateur(RoleUtilisateur $roleUtilisateur)
    {
        $this->roleUtilisateur = $roleUtilisateur;
    }

    public function getRoleUtilisateur()
    {
        return $this->roleUtilisateur;
    }

    /**
     * @param DroitService $droitService
     */
    public function setDroitService(DroitService $droitService): void
    {
        $this->droitService = $droitService;
    }

    /**
     * @return DroitService
     */
    public function getDroitService(): DroitService
    {
        return $this->droitService;
    }

    public function setUtilisateurId($id_u)
    {
        $this->id_u = $id_u;
    }

    public function getUtilisateurId()
    {
        return $this->id_u;
    }

    public function setRequestInfo(array $request)
    {
        $this->request = $request;
    }

    public function getFromRequest($key, $default = false)
    {
        if (! isset($this->request[$key])) {
            return $default;
        }
        return $this->request[$key];
    }

    public function getRequest()
    {
        return $this->request;
    }

    /**
     * @deprecated 4.1.21 Use checkDroitFor() instead
     */
    protected function checkDroit($id_e, $droit)
    {
        if ($this->hasAllDroit) {
            return true;
        }
        if (! $this->getDroitService()->hasDroit($this->id_u, $droit, $id_e)) {
            throw new ForbiddenException("Acces interdit id_e=$id_e, droit=$droit,id_u={$this->id_u}");
        }
        return true;
    }

    /**
     * @throws ForbiddenException
     * @throws NotFoundException
     */
    protected function checkDroitFor($id_e, string $droit_id, DroitType $droit_type): bool
    {
        if ($this->hasAllDroit) {
            return true;
        }
        if (! $this->getDroitService()->hasDroitFor($this->id_u, (int) $id_e, $droit_id, $droit_type)) {
            $droit = DroitService::getDroitFor($droit_id, $droit_type);
            throw new ForbiddenException("Acces interdit id_e=$id_e, droit=$droit,id_u={$this->id_u}");
        }
        return true;
    }

    /**
     * @deprecated 4.1.21 Use checkOneDroitFor() instead
     */
    protected function checkOneDroit($droit)
    {
        if (!$this->hasOneDroit($droit)) {
            throw new ForbiddenException("Vous devez avoir le droit $droit pour accéder à la ressource.");
        }
        return true;
    }

    /**
     * @deprecated 4.1.21 Use hasOneDroitFor() instead
     */
    public function hasOneDroit($droit)
    {
        if ($this->hasAllDroit) {
            return true;
        }
        return $this->getDroitService()->hasOneDroit($this->getUtilisateurId(), $droit);
    }

    /**
     * @throws ForbiddenException
     */
    protected function checkOneDroitFor(string $droit_id, DroitType $droit_type): bool
    {
        if (!$this->hasOneDroitFor($droit_id, $droit_type)) {
            $droit = DroitService::getDroitFor($droit_id, $droit_type);
            throw new ForbiddenException("Vous devez avoir le droit $droit pour accéder à la ressource.");
        }
        return true;
    }

    public function hasOneDroitFor(string $droit_id, DroitType $droit_type): bool
    {
        if ($this->hasAllDroit) {
            return true;
        }
        return $this->getDroitService()->hasOneDroitFor($this->getUtilisateurId(), $droit_id, $droit_type);
    }
}
