<?php

use Pastell\Service\Droit\DroitService;

/**
 * Class BaseAPIControllerFactory @deprecated
 */
class BaseAPIControllerFactory
{
    private $objectInstancier;

    private $request;

    private array $server;

    private $fileUploader;

    public function __construct(ObjectInstancier $objectInstancier)
    {
        $this->objectInstancier = $objectInstancier;
        $this->setRequest($_REQUEST);
        $this->setServer($_SERVER);
        $this->setFileUploader(new FileUploader());
    }

    public function setRequest($request)
    {
        $this->request = $request;
    }

    public function setServer(array $server): void
    {
        $this->server = $server;
    }

    public function setFileUploader(FileUploader $fileUploader)
    {
        $this->fileUploader = $fileUploader;
    }

    public function getInstance($controllerName, $id_u)
    {
        $controller_name = "{$controllerName}APIController";

        if (! class_exists($controller_name)) {
            throw new NotFoundException("La ressource $controllerName n'a pas été trouvée");
        }

        /** @var BaseAPIController $controllerObject */
        $controllerObject = $this->objectInstancier->getInstance($controller_name);
        $controllerObject->setUtilisateurId($id_u);
        $controllerObject->setRequestInfo($this->request);
        $controllerObject->setRoleUtilisateur($this->objectInstancier->getInstance(RoleUtilisateur::class));
        $controllerObject->setDroitService($this->objectInstancier->getInstance(DroitService::class));
        $controllerObject->setFileUploader($this->fileUploader);

        return $controllerObject;
    }

    public function callMethod($controller, array $query_arg, $request_method)
    {
        $controllerObject = $this->getInstance($controller, $this->getUtilisateurId());

        $controllerObject->setQueryArgs($query_arg);

        $controllerObject->setCallerType('web service');

        if (! method_exists($controllerObject, $request_method)) {
            throw new MethodNotAllowedException("La méthode $request_method n'est pas disponible pour l'objet $controller");
        }

        return $controllerObject->$request_method();
    }

    public function getUtilisateurId()
    {
        /** @var ApiAuthentication $apiAuthentication */
        $apiAuthentication = $this->objectInstancier->getInstance(ApiAuthentication::class);
        $apiAuthentication->setRequestInfo($this->request);
        $apiAuthentication->setServerInfo($this->server);
        return $apiAuthentication->getUtilisateurId();
    }
}
