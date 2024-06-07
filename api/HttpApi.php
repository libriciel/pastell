<?php

use Monolog\Logger;
use Symfony\Component\RateLimiter\Exception\RateLimitExceededException;

class HttpApi
{
    public const PARAM_API_FUNCTION = 'api_function';

    public const API_VERSION = 'v2';

    public static $HTTP_AUTHORIZED_METHOD =  ['get','post','patch','delete'];

    /** @var JSONoutput */
    private $jsonOutput;

    private $objectInstancier;

    private $get = [];

    private $request = [];

    private $server = [];

    private $is_legacy = false;

    private Logger $logger;

    public function __construct(ObjectInstancier $objectInstancier)
    {
        $this->objectInstancier = $objectInstancier;
        $this->jsonOutput = $objectInstancier->getInstance(JSONoutput::class);
        $this->logger = $this->objectInstancier->getInstance(Logger::class);
    }

    public function setRequestArray(array $request)
    {
        $this->request = $request;
    }

    public function setGetArray(array $get)
    {
        $this->get = $get;
    }

    public function setServerArray(array $server)
    {
        $this->server = $server;
    }

    public function dispatch()
    {
        try {
            $this->dispatchThrow();
        } catch (BadRequestException $e) {
            header_wrapper('HTTP/1.1 400 Bad Request');
        } catch (UnauthorizedException $e) {
            header_wrapper('HTTP/1.1 401 Unauthorized');
            header_wrapper('WWW-Authenticate: Basic realm="API Pastell"');
        } catch (ForbiddenException $e) {
            header_wrapper('HTTP/1.1 403 Forbidden');
        } catch (NotFoundException $e) {
            header_wrapper('HTTP/1.1 404 Not Found');
        } catch (MethodNotAllowedException $e) {
            header_wrapper('HTTP/1.1 405 Method Not Allowed');
        } catch (ConflictException $e) {
            header_wrapper('HTTP/1.1 409 Conflict');
        } catch (RateLimitExceededException $e) {
            header_wrapper("HTTP/1.1 429 Too Many Requests");
            header_wrapper("Retry-after: 60");
        } catch (InternalServerException $e) {
            header_wrapper('HTTP/1.1 500 Internal Server Error');
        } catch (Exception $e) {
            if (! $this->is_legacy) {
                header_wrapper('HTTP/1.1 400 Bad Request');
            }
        } finally {
            if (isset($e)) {
                $result['status'] = 'error';
                $result['error-message'] = $e->getMessage();
                $this->jsonOutput->sendJson($result);
                $this->logger->warning(
                    "API call error  : {$result['error-message']}"
                );
            } else {
                $this->logger->warning(
                    'API call success'
                );
            }
        }
    }

    private function dispatchThrow()
    {
        $request_method = strtolower($this->server['REQUEST_METHOD']);

        if (! in_array($request_method, self::$HTTP_AUTHORIZED_METHOD)) {
            throw new MethodNotAllowedException("Cette méthode n'est pas utilisable sur l'API Pastell");
        }

        if (empty($this->get[self::PARAM_API_FUNCTION])) {
            throw new Exception("Il faut spécifier une fonction de l'api");
        }
        $api_function = $this->get[self::PARAM_API_FUNCTION];
        $api_function = ltrim($api_function, "/");

        $this->logger->info(
            "Call $request_method $api_function",
            ['GET' => $this->get,'FILES' => $_FILES]
        );

        $is_legacy = false;
        $old_api_function = false;
        if (preg_match("#\.php$#", $api_function)) {
            $old_api_function = $api_function;
            $old_info = $this->getAPINameFromLegacyScript($api_function);
            $api_function = "v2/" . $old_info[0];
            $request_method = $old_info[1];
            $is_legacy = true;
            $this->is_legacy = true;
            $this->logger->info(
                "Call legacy API corresponding to > $request_method $api_function"
            );
        }



        if (preg_match("#rest/allo#", $api_function)) {
            $api_function = "v2/version/allo";
        }

        $list = explode("/", $api_function);
        $api_version = array_shift($list);

        if ($api_version != self::API_VERSION) {
            throw new Exception("Version de l'API incorrecte ou version absente");
        }
        $ressource = implode("/", $list);

        /** @var InternalAPI $internalAPI */
        $internalAPI = $this->objectInstancier->getInstance(InternalAPI::class);

        $utilisateur_id = $this->getUtilisateurId();

        $internalAPI->setUtilisateurId($utilisateur_id);
        $internalAPI->setCallerType(InternalAPI::CALLER_TYPE_WEBSERVICE);

        $this->objectInstancier->getInstance(Journal::class)->setId($utilisateur_id);

        if ($request_method == 'patch' && ! $is_legacy) {
            parse_str(file_get_contents("php://input"), $this->request);
        }

        if ($is_legacy) {
            $this->logger->debug("[Legacy API] $api_function", $_FILES);

            foreach ($_FILES as $index => $files) {
                if (is_array($_FILES[$index]['name'])) {
                    foreach ($_FILES[$index]['name'] as $i => $name) {
                        $_FILES[$index]['name'][$i] = utf8_encode($name);
                    }
                } else {
                    $_FILES[$index]['name'] = utf8_encode($files['name']);
                }
            }

            $fileUploader = $this->objectInstancier->getInstance(FileUploader::class);
            $fileUploader->setFiles($_FILES);
            $internalAPI->setFileUploader($fileUploader);
            $this->request = utf8_encode_array($this->request);
        }
        if (in_array($request_method, ['post']) && !$is_legacy) {
            header_wrapper('HTTP/1.1 201 Created');
        }
        $result = $internalAPI->$request_method($ressource, $this->request);
        if ($is_legacy) {
            if ($old_api_function == 'action.php' && $result['result'] === true) {
                $result['result'] = "1";
            }
            $result = $this->string_encode_array($result);
        }

        $this->jsonOutput->sendJson($result, $is_legacy ? false : true);
        $this->logger->debug(
            "API result : " . json_encode($result)
        );
    }

    private function string_encode_array($array)
    {
        if (! is_array($array) && !is_object($array)) {
            return strval($array);
        }
        $result = [];
        foreach ($array as $cle => $value) {
            $result[strval($cle)] = $this->string_encode_array($value);
        }
        return $result;
    }

    public function getUtilisateurId()
    {
        /** @var ApiAuthentication $apiAuthentication */
        $apiAuthentication = $this->objectInstancier->getInstance(ApiAuthentication::class);
        $apiAuthentication->setRequestInfo($this->request);
        $apiAuthentication->setServerInfo($this->server);
        return $apiAuthentication->getUtilisateurId();
    }

    public function getAPINameFromLegacyScript($old_script_name)
    {
        $legacy_script = [
            'version.php' => ['version', 'get'],

            'list-roles.php' => ['role', 'get'],

            'document-type.php' => ['flux', 'get'],
            'document-type-info.php' => ["flux/{$this->getFromRequest('type')}", 'get'],
            'document-type-action.php' => ["flux/{$this->getFromRequest('type')}/action", 'get'],

            'list-extension.php' => ['extension', 'get'],
            'edit-extension.php' => ['extension', 'compatV1Edition'],
            'delete-extension.php' => ["extension/{$this->getFromRequest('id_extension')}", 'delete'],
            'journal.php' => ['journal', 'get'],
            'list-utilisateur.php' => ['utilisateur', 'get'],
            'detail-utilisateur.php' => ["utilisateur/{$this->getFromRequest('id_u')}", 'get'],
            'delete-utilisateur.php' => ["utilisateur/{$this->getFromRequest('id_u')}", 'delete'],
            'modif-utilisateur.php' => ["utilisateur/{$this->getFromRequest('id_u')}", 'patch', 'edit'],
            'create-utilisateur.php' => ['utilisateur', 'post'],
            'list-role-utilisateur.php' => ["utilisateur/{$this->getFromRequest('id_u')}/role", 'get'],
            'delete-role-utilisateur.php' => ["utilisateur/{$this->getFromRequest('id_u')}/role",'delete'],
            'add-role-utilisateur.php' => ["utilisateur/{$this->getFromRequest('id_u')}/role", 'post'],
            'add-several-role-utilisateur.php' => ["utilisateur/{$this->getFromRequest('id_u')}/role/add",'compatV1Edition'],
            'delete-several-roles-utilisateur.php' => ["utilisateur/{$this->getFromRequest('id_u')}/role/delete", 'compatV1Edition'],

            'list-entite.php' => ['entite', 'get'],
            'detail-entite.php' => ["entite/{$this->getFromRequest('id_e')}", 'get'],
            'modif-entite.php' => ["entite/{$this->getFromRequest('id_e')}", 'patch'],
            'delete-entite.php' => ["entite/{$this->getFromRequest('id_e')}", 'delete'],
            'create-entite.php' => ['entite', 'post'],

            'list-connecteur-entite.php' => ["entite/{$this->getFromRequest('id_e')}/connecteur", 'get'],
            'detail-connecteur-entite.php' => ["entite/{$this->getFromRequest('id_e')}/connecteur/{$this->getFromRequest('id_ce')}", 'get'],
            'delete-connecteur-entite.php' => ["entite/{$this->getFromRequest('id_e')}/connecteur/{$this->getFromRequest('id_ce')}", 'delete'],
            'modif-connecteur-entite.php' => ["entite/{$this->getFromRequest('id_e')}/connecteur/{$this->getFromRequest('id_ce')}", 'patch'],
            'edit-connecteur-entite.php' => ["entite/{$this->getFromRequest('id_e')}/connecteur/{$this->getFromRequest('id_ce')}/content", 'patch'],

            'create-connecteur-entite.php' => ["entite/{$this->getFromRequest('id_e')}/connecteur/{$this->getFromRequest('id_ce')}", 'post'],
            'action-connecteur-entite.php' =>
                [
                    "entite/{$this->getFromRequest('id_e')}/flux/{$this->getFromRequest('flux')}/action",
                    'post'
                ],

            'create-flux-connecteur.php' => [
                sprintf(
                    'entite/%s/flux/%s/connecteur/%s?type=%s',
                    $this->getFromRequest('id_e'),
                    $this->getFromRequest('flux'),
                    $this->getFromRequest('id_ce'),
                    $this->getFromRequest('type')
                ),
                'post'
            ],
            'delete-flux-connecteur.php' => ["entite/{$this->getFromRequest('id_e')}/flux/{$this->getFromRequest('id_fe')}", 'delete'],


            'list-flux-connecteur.php' => [
                "/entite/{$this->getFromRequest('id_e')}/flux?type={$this->getFromRequest('type')}&flux={$this->getFromRequest('flux')}",
                'get'
            ],

            'list-document.php' => ["entite/{$this->getFromRequest('id_e')}/document", 'get'],
            'detail-document.php' => ["entite/{$this->getFromRequest('id_e')}/document/{$this->getFromRequest('id_d')}", 'get'],
            'detail-several-document.php' => ["entite/{$this->getFromRequest('id_e')}/document/", 'get'],

            'create-document.php' =>  ["entite/{$this->getFromRequest('id_e')}/document", 'post'],
            'modif-document.php' => ["entite/{$this->getFromRequest('id_e')}/document/{$this->getFromRequest('id_d')}", 'patch'],
            'recherche-document.php' => ["entite/{$this->getFromRequest('id_e')}/document", 'get'],

            'external-data.php' => ["entite/{$this->getFromRequest('id_e')}/document/{$this->getFromRequest('id_d')}/externalData/{$this->getFromRequest('field')}", 'get'],
            'recuperation-fichier.php' => [
                sprintf(
                    'entite/%s/document/%s/file/%s/%s',
                    $this->getFromRequest('id_e'),
                    $this->getFromRequest('id_d'),
                    $this->getFromRequest('field'),
                    $this->getFromRequest('num')
                ),
                'get'
            ],

            ## oops : field => field_name num=>file_number pour faire comme en V1

            'receive-file.php' => [
                sprintf(
                    'entite/%s/document/%s/file/%s/%s?receive=true',
                    $this->getFromRequest('id_e'),
                    $this->getFromRequest('id_d'),
                    $this->getFromRequest('field_name'),
                    $this->getFromRequest('file_number')
                ),
                'get'
            ],

            'send-file.php' => ["entite/{$this->getFromRequest('id_e')}/document/{$this->getFromRequest('id_d')}/file/{$this->getFromRequest('field')}/{$this->getFromRequest('num')}", 'post'],
            'action.php' => ["entite/{$this->getFromRequest('id_e')}/document/{$this->getFromRequest('id_d')}/action/{$this->getFromRequest('action')}", 'post'],

        ];
        if (empty($legacy_script[$old_script_name])) {
            throw new NotFoundException("Impossible de trouver le script $old_script_name");
        }

        return $legacy_script[$old_script_name];
    }

    private function getFromRequest($key, $default = false)
    {
        if (empty($this->request[$key])) {
            return $default;
        }
        if (is_array($this->request[$key])) {
            return '';
        }
        return $this->request[$key];
    }
}
