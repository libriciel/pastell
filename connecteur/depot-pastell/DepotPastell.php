<?php

class DepotPastell extends GEDConnecteur
{
    public const CONNECTEUR_ID = "depot-pastell";

    public const PASTELL_URL  = "pastell_url";
    public const PASTELL_LOGIN = "pastell_login";
    public const PASTELL_PASSWORD = "pastell_password";
    public const PASTELL_ID_E = "pastell_id_e";
    public const PASTELL_TYPE_DOSSIER = "pastell_type_dossier";
    public const PASTELL_METADATA = "pastell_metadata";
    public const PASTELL_ACTION = "pastell_action";

    public const NO_ACTION = 'NO_ACTION';

    private $curlWrapperFactory;

    public function __construct(CurlWrapperFactory $curlWrapperFactory)
    {
        $this->curlWrapperFactory = $curlWrapperFactory;
    }

    /**
     * @throws UnrecoverableException
     */
    public function send(DonneesFormulaire $donneesFormulaire): array
    {
        list($metadata,$files) = $this->getMetadataAndFiles($donneesFormulaire);

        $id_d = $this->createDocument()['id_d'] ?? false;
        if (! $id_d) {
            throw new UnrecoverableException("Impossible de créer le dossier sur Pastell");
        }
        $this->addGedDocumentId($id_d, $id_d);

        $last_call = $this->postMetadataAndFiles($donneesFormulaire, $id_d, $metadata, $files);
        if (empty($last_call['formulaire_ok'])) {
            throw new UnrecoverableException(
                "Impossible d'appeller l'action sur le document Pastell car le formulaire n'est pas valide : " .
                ($last_call['message'] ?? '')
            );
        }

        if ($this->connecteurConfig->get(self::PASTELL_ACTION) === self::NO_ACTION) {
            return $this->getGedDocumentsId();
        }

        $action_call_result = $this->postAction($id_d);
        if (empty($action_call_result['result'])) {
            throw new UnrecoverableException(
                "Erreur lors de l'appel à l'action sur le document : " .
                ($action_call_result['error-message'] ?? '')
            );
        }
        return $this->getGedDocumentsId();
    }

    private function getDictionnary()
    {
        $pastell_metadata = $this->connecteurConfig->get(self::PASTELL_METADATA);

        $metadata_list = explode("\n", $pastell_metadata);

        $result = [];
        foreach ($metadata_list as $metadata) {
            $l = explode(":", $metadata, 2);
            $key = trim($l[0]);
            $value = trim($l[1] ?? "");
            if ($key && $value) {
                $result[$key] = $value;
            }
        }
        return $result;
    }


    /**
     * @return string
     * @throws UnrecoverableException
     */
    public function getVersion(): string
    {
        $result = $this->callPastell("version", "GET");
        return $result['version_complete'];
    }

    /**
     * @return array
     * @throws UnrecoverableException
     */
    public function createDocument(): array
    {
        $id_e = $this->connecteurConfig->get(self::PASTELL_ID_E);
        $type_dossier = $this->connecteurConfig->get('pastell_type_dossier');
        return $this->callPastell(
            "entite/$id_e/document?type=$type_dossier",
            CurlWrapper::POST_METHOD
        );
    }

    /**
     * @param $id_d
     * @return array
     * @throws UnrecoverableException
     */
    public function postAction($id_d): array
    {
        $id_e = $this->connecteurConfig->get(self::PASTELL_ID_E);
        $action_name = $this->connecteurConfig->get(self::PASTELL_ACTION);
        return $this->callPastell(
            "/entite/$id_e/document/$id_d/action/$action_name",
            CurlWrapper::POST_METHOD
        );
    }

    /**
     * @param $donneesFormulaire
     * @return array
     * @throws UnrecoverableException
     */
    private function getMetadataAndFiles(DonneesFormulaire $donneesFormulaire): array
    {
        $type_dossier = $this->connecteurConfig->get(self::PASTELL_TYPE_DOSSIER);

        $dictionnary = $this->getDictionnary();
        $formulaire = $donneesFormulaire->getFormulaire();

        $metadata = [];
        $files = [];

        foreach ($dictionnary as $id_element_pastell_cible => $element_pastell_source) {
            if (preg_match("#^%(.*)%$#", $element_pastell_source, $matches)) {
                $id_element_pastell_source = $matches[1];
                if (! $formulaire->getField($id_element_pastell_source)) {
                    throw new UnrecoverableException(
                        "L'élement « $id_element_pastell_source » n'existe pas pour le type de dossier $type_dossier"
                    );
                }
                if ($formulaire->getField($id_element_pastell_source)->getType() == 'file') {
                    $files[$id_element_pastell_cible] = $id_element_pastell_source;
                } else {
                    $metadata[$id_element_pastell_cible] = $donneesFormulaire->get($id_element_pastell_source);
                }
            } else {
                $metadata[$id_element_pastell_cible] = $element_pastell_source;
            }
        }
        return [$metadata,$files];
    }

    /**
     * @param DonneesFormulaire $donneesFormulaire
     * @param $id_d
     * @param array $metadata
     * @param array $files
     * @return array
     * @throws UnrecoverableException
     */
    public function postMetadataAndFiles(DonneesFormulaire $donneesFormulaire, $id_d, array $metadata, array $files): array
    {
        $id_e = $this->connecteurConfig->get(self::PASTELL_ID_E);
        $last_call = $this->callPastell(
            "/entite/$id_e/document/$id_d",
            CurlWrapper::PATCH_METHOD,
            $metadata
        );

        foreach ($files as $id_file_cible => $id_file_source) {
            if ($donneesFormulaire->get($id_file_source)) {
                foreach ($donneesFormulaire->get($id_file_source) as $nb_file => $filename) {
                    $last_call = $this->callPastell(
                        "/entite/$id_e/document/$id_d/file/$id_file_cible/$nb_file",
                        CurlWrapper::POST_METHOD,
                        [
                            'file_name' => $filename,
                            'file_content' => curl_file_create(
                                $donneesFormulaire->getFilePath($id_file_source, $nb_file)
                            )
                        ],
                        false
                    );
                }
            }
        }
        return $last_call;
    }

    /**
     * @param string $api_function
     * @param string $method
     * @param array $post_data
     * @param bool $url_encode
     * @return array
     * @throws UnrecoverableException
     */
    private function callPastell(string $api_function, string $method = '', array $post_data = [], bool $url_encode = true): array
    {
        $curlWrapper = $this->curlWrapperFactory->getInstance();

        $login = $this->connecteurConfig->get(self::PASTELL_LOGIN);
        $curlWrapper->httpAuthentication(
            $login,
            $this->connecteurConfig->get(self::PASTELL_PASSWORD)
        );

        if ($method) {
            $curlWrapper->setProperties(CURLOPT_CUSTOMREQUEST, $method);
        }
        if ($url_encode) {
            $post_data_encode = [];
            foreach ($post_data as $k => $v) {
                $post_data_encode[urlencode($k)] = urlencode($v);
            }
            $curlWrapper->setPostDataUrlEncode($post_data_encode);
        } else {
            foreach ($post_data as $key => $value) {
                $curlWrapper->addPostData($key, $value);
            }
        }

        $url = rtrim($this->connecteurConfig->get('pastell_url'), "/") . "/api/v2/$api_function";

        $curl_output = strval($curlWrapper->get($url));
        $http_code = strval($curlWrapper->getLastHttpCode());

        $this->getLogger()->info("Pastell call URL : $url, username: $login, code_reponse: $http_code");
        $this->getLogger()->debug("Pastell response : $curl_output");

        if (! in_array($http_code, [200,201])) {
            $last_error = $curlWrapper->getLastError();
            throw new UnrecoverableException("Erreur $http_code ($last_error) lors de la réponse de Pastell : " . get_hecho($curl_output));
        }

        $result = json_decode($curl_output, true);
        if (! $result) {
            throw new UnrecoverableException("Message de Pastell non compréhensible : " . get_hecho($curl_output));
        }
        return $result;
    }
}
