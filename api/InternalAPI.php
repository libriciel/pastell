<?php

use Pastell\Service\Droit\DroitService;

class InternalAPI
{
    public const CALLER_TYPE_NONE = "";
    public const CALLER_TYPE_CONSOLE = "console";
    public const CALLER_TYPE_WEBSERVICE = "webservice";
    public const CALLER_TYPE_SCRIPT = "script";

    /** @var ObjectInstancier */
    private $objectInstancier;

    private $id_u;

    private $caller_type;

    private $fileUploader;

    public function __construct(ObjectInstancier $objectInstancier)
    {
        $this->objectInstancier = $objectInstancier;
        $this->fileUploader = new FileUploader();
    }

    public function setUtilisateurId($id_u)
    {
        $this->id_u = $id_u;
    }

    public function setCallerType($caller_type)
    {
        $this->caller_type = $caller_type;
    }

    public function setFileUploader(FileUploader $fileUploader)
    {
        $this->fileUploader = $fileUploader;
    }

    public function get($ressource, $data = [])
    {
        $path = parse_url($ressource, PHP_URL_PATH);
        $query = parse_url($ressource, PHP_URL_QUERY);
        parse_str($query ?? "", $data_from_query);
        $data = array_merge($data, $data_from_query);
        return $this->callMethod('get', $path, $data);
    }

    public function post($ressource, $data = [])
    {
        return $this->callMethod('post', $ressource, $data);
    }

    public function delete($ressource, $data = [])
    {
        $path = parse_url($ressource, PHP_URL_PATH);
        $query = parse_url($ressource, PHP_URL_QUERY);
        parse_str($query ?? "", $data_from_query);
        $data = array_merge($data, $data_from_query);
        return $this->callMethod('delete', $path, $data);
    }

    public function patch($ressource, $data = [])
    {
        return $this->callMethod('patch', $ressource, $data);
    }

    public function compatV1Edition($ressource, $data = [])
    {
        return $this->callMethod('compatV1Edition', $ressource, $data);
    }

    private function callMethod($request_method, $ressource, $data)
    {
        list($controller_name,$query_arg) = $this->getControllerName($ressource);
        $controllerObject = $this->getInstance($controller_name, $data);
        $controllerObject->setQueryArgs($query_arg);
        $controllerObject->setCallerType($this->caller_type);

        if (! method_exists($controllerObject, $request_method)) {
            throw new MethodNotAllowedException("La méthode $request_method n'existe pas pour cette ressource");
        }

        return $controllerObject->$request_method();
    }

    private function getInstance($controllerName, $data = [])
    {
        $controller_name = ucfirst("{$controllerName}APIController");

        if (! class_exists($controller_name)) {
            throw new NotFoundException("La ressource $controllerName n'a pas été trouvée");
        }

        /** @var BaseAPIController $controllerObject */
        $controllerObject = $this->objectInstancier->getInstance($controller_name);
        if (! $this->id_u && $this->caller_type != self::CALLER_TYPE_SCRIPT) {
            throw new UnauthorizedException("Vous devez être connecté pour utiliser l'API");
        }
        if ($this->caller_type == self::CALLER_TYPE_SCRIPT) {
            $controllerObject->setAllDroit(true);
        }
        $controllerObject->setUtilisateurId($this->id_u);
        $controllerObject->setRequestInfo($data);
        $controllerObject->setRoleUtilisateur($this->objectInstancier->getInstance(RoleUtilisateur::class));
        $controllerObject->setDroitService($this->objectInstancier->getInstance(DroitService::class));
        $controllerObject->setFileUploader($this->fileUploader);

        return $controllerObject;
    }

    private function getControllerName($ressource)
    {
        $ressource = ltrim($ressource, "/");
        $query_arg = explode("/", $ressource);
        $controller_name = ucfirst(array_shift($query_arg));
        if (! $controller_name) {
            throw new Exception("Ressource absente");
        }
        if (isset($query_arg[1]) && $controller_name == 'Utilisateur' && ucfirst($query_arg[1]) == 'Role') {
            $controller_name = "UtilisateurRole";
        }

        if (isset($query_arg[1]) && $controller_name == 'Entite' && ucfirst($query_arg[1]) == 'Connecteur') {
            $controller_name = "Connecteur";
        }

        if (isset($query_arg[1]) && $controller_name == 'Entite' && ucfirst($query_arg[1]) == 'Document') {
            $controller_name = "Document";
        }
        if (isset($query_arg[1]) && $controller_name == 'Entite' && ucfirst($query_arg[1]) == 'Flux') {
            $controller_name = "EntiteFlux";
        }

        return [$controller_name,$query_arg];
    }
}
