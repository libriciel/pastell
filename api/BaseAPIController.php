<?php

use Pastell\Service\Droit\DroitService;

abstract class BaseAPIController
{
    public const string RESULT_OK = 'ok';

    private $id_u;
    private array $request = [];

    private $caller_type;


    /** @var RoleUtilisateur */
    private $roleUtilisateur;

    /** @var  DroitService */
    private $droitService;

    /** @var  FileUploader */
    private $fileUploader;

    private $query_args;

    private bool $hasAllDroit = false;

    public function setQueryArgs(array $query_args)
    {
        $this->query_args = $query_args;
    }

    public function getFromQueryArgs($place_number)
    {
        return $this->query_args[$place_number] ?? false;
    }

    public function setAllDroit(bool $hasAllDroit = false): void
    {
        $this->hasAllDroit = $hasAllDroit;
    }

    protected function hasAllDroit(): bool
    {
        return $this->hasAllDroit;
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
     * @throws ForbiddenException
     */
    protected function checkDroit($id_e, string $droit): void
    {
        if (!(($this->hasAllDroit) || $this->getDroitService()->hasDroit($this->id_u, $droit, $id_e))) {
            throw new ForbiddenException("Acces interdit id_e=$id_e, droit=$droit,id_u={$this->id_u}");
        }
    }

    /**
     * @throws ForbiddenException
     */
    protected function checkOneDroit(string $droit): void
    {
        if (!(($this->hasAllDroit) || $this->getDroitService()->hasOneDroit($this->id_u, $droit))) {
            throw new ForbiddenException("Vous devez avoir le droit $droit pour accéder à la ressource.");
        }
    }
}
