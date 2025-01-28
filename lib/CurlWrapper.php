<?php

use Pastell\Validator\UrlValidator;
use Pastell\Validator\UrlValidatorInterface;

class CurlWrapper
{
    private const POST_DATA_SEPARATOR = "\r\n";

    public const GET_METHOD = "GET";
    public const POST_METHOD = "POST";
    public const PATCH_METHOD = "PATCH";
    public const DELETE_METHOD = "DELETE";
    public const PUT_METHOD = "PUT";

    private $curlHandle;
    private $lastError = "";

    private $postDataList = [];
    /**
     * @var CurlWrapperFileProperties[]
     */
    private $filePropertiesList = [];

    private $httpCode = 0;
    private $lastOutput;

    private $http_proxy_url;
    private $no_proxy;

    private $header  = [];

    public function __construct(
        private ?CurlFunctions $curlFunctions = null,
        private ?UrlValidatorInterface $urlValidator = null,
    ) {
        if (! $curlFunctions) {
            $curlFunctions = new CurlFunctions();
        }
        $this->curlFunctions = $curlFunctions;

        if ($urlValidator === null) {
            $urlValidator = new UrlValidator();
        }
        $this->setUrlValidator($urlValidator);
        $this->curlHandle = $this->curlFunctions->curl_init();
        $this->setProperties(CURLOPT_RETURNTRANSFER, 1);
        $this->setProperties(CURLOPT_FOLLOWLOCATION, 1);
        $this->setProperties(CURLOPT_MAXREDIRS, 5);
    }

    public function __destruct()
    {
        $this->curlFunctions->curl_close($this->curlHandle);
    }

    public function setProxy(string $http_proxy_url): void
    {
        $this->http_proxy_url = $http_proxy_url;
    }

    public function setNoProxy(string $no_proxy)
    {
        $this->no_proxy = $no_proxy;
    }

    public function setUrlValidator(UrlValidatorInterface $urlValidator): void
    {
        $this->urlValidator = $urlValidator;
    }

    public function httpAuthentication($username, $password)
    {
        $this->setProperties(CURLOPT_USERPWD, "$username:$password");
        $this->setProperties(CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    }

    public function addHeader($name, $value)
    {
        $this->header[$name] = $value;

        $headersFlattened = [];
        foreach ($this->header as $key => $value) {
            $headersFlattened[] = "$key: $value";
        }

        $this->setProperties(CURLOPT_HTTPHEADER, $headersFlattened);
    }

    /**
     * @return string
     */
    public function getFullMessage(): string
    {
        return sprintf(
            "Code HTTP: %s. %s %s",
            $this->httpCode,
            $this->lastError,
            $this->lastOutput
        );
    }

    public function getLastError(): string
    {
        return $this->lastError;
    }

    public function setProperties($properties, $values)
    {
        $this->curlFunctions->curl_setopt($this->curlHandle, $properties, $values);
    }

    public function setAccept($format)
    {
        $this->addHeader("Accept", $format);
    }

    public function dontVerifySSLCACert()
    {
        $this->setProperties(CURLOPT_SSL_VERIFYHOST, 0);
        $this->setProperties(CURLOPT_SSL_VERIFYPEER, 0);
    }

    public function setServerCertificate($serverCertificate)
    {
        $this->setProperties(CURLOPT_CAINFO, $serverCertificate);
        $this->setProperties(CURLOPT_SSL_VERIFYPEER, 0);
    }

    public function setClientCertificate($clientCertificate, $clientKey, $clientKeyPassword)
    {
        $this->setProperties(CURLOPT_SSLCERT, $clientCertificate);
        $this->setProperties(CURLOPT_SSLKEY, $clientKey);
        $this->setProperties(CURLOPT_SSLKEYPASSWD, $clientKeyPassword);
    }

    private function isProxyNeeded(string $url): bool
    {
        if (! $this->http_proxy_url) {
            return false;
        }
        if (! $this->no_proxy) {
            return true;
        }
        $no_proxy_array = explode(",", $this->no_proxy);
        $host = parse_url($url, PHP_URL_HOST);
        return (! in_array($host, $no_proxy_array));
    }

    private function addProxyHeader(string $url): void
    {
        if ($this->isProxyNeeded($url)) {
            $this->setProperties(CURLOPT_PROXY, $this->http_proxy_url);
        } else {
            $this->setProperties(CURLOPT_PROXY, '');
        }
    }

    public function get($url)
    {
        $this->urlValidator->validate($url);
        $this->setProperties(CURLOPT_URL, $url);
        if ($this->filePropertiesList || $this->postDataList) {
            $this->curlSetPostData();
        }

        $this->addProxyHeader($url);

        $this->lastOutput = $this->curlFunctions->curl_exec($this->curlHandle);
        $this->lastError = $this->curlFunctions->curl_error($this->curlHandle);


        if ($this->lastError) {
            $this->lastError = "Erreur de connexion au serveur : " . $this->lastError;
            return false;
        }
        $this->httpCode = $this->curlFunctions->curl_getinfo($this->curlHandle, CURLINFO_HTTP_CODE);
        if ($this->httpCode == 404) {
            $this->lastError = "$url : 404 Not Found";
            return false;
        }

        return $this->lastOutput;
    }

    public function getLastHttpCode(): int
    {
        return $this->httpCode;
    }

    public function getLastOutput()
    {
        return $this->lastOutput;
    }

    public function setJsonPostData(array $data, $json_flag = JSON_NUMERIC_CHECK)
    {
        $json_to_send = json_encode($data, $json_flag);
        $this->setProperties(CURLOPT_POST, true);
        $this->setProperties(CURLOPT_POSTFIELDS, $json_to_send);
        $this->addHeader('Content-Type', 'application/json');
    }

    public function addPostData($name, $value)
    {
        $this->postDataList[] = [$name,$value];
    }

    public function setPostDataUrlEncode(array $post_data)
    {
        $pd = [];
        foreach ($post_data as $k => $v) {
            $pd[] = "$k=$v";
        }
        $pd = implode("&", $pd);
        $this->setProperties(CURLOPT_POST, true);
        $this->setProperties(CURLOPT_POSTFIELDS, $pd);
        $this->setProperties(CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
    }


    public function addPostFile(
        $field,
        $filePath,
        $fileName = false,
        $contentType = "application/octet-stream",
        $contentTransferEncoding = false
    ) {
        if (! $fileName) {
            $fileName = basename($filePath);
        }

        $fileProperties = new CurlWrapperFileProperties();
        $fileProperties->field = $field;
        $fileProperties->filename = $fileName;
        $fileProperties->filepath = $filePath;
        $fileProperties->contentType = $contentType;
        $fileProperties->contentTransferEncoding = $contentTransferEncoding;

        $this->filePropertiesList[] = $fileProperties;
    }

    private function getBoundary(): string
    {
        return '----------------------------' .
            mb_substr(sha1('CurlWrapper' . microtime()), 0, 12);
    }

    private function curlSetPostData()
    {
        $this->setProperties(CURLOPT_POST, true);
        if ($this->isPostDataWithSimilarName()) {
            $this->curlSetPostDataWithSimilarFilename();
        } else {
            $this->curlPostDataStandard();
        }
    }

    private function isPostDataWithSimilarName(): bool
    {
        $array = [];

        //cURL ne permet pas de poster plusieurs fichiers avec le même nom !
        //cette fonction est inspirée de http://blog.srcmvn.com/multiple-values-for-the-same-key-and-file-upl
        foreach ($this->postDataList as $postData) {
            if (isset($array[$postData[0]])) {
                return true;
            }
            $array[$postData[0]] = true;
        }

        foreach ($this->filePropertiesList as $fileProperties) {
            if (isset($array[$fileProperties->field])) {
                return true;
            }
            $array[$fileProperties->field] = true;
        }
        return false;
    }

    private function curlPostDataStandard()
    {
        $post = [];
        foreach ($this->postDataList as $postData) {
            $post[$postData[0]] = $postData[1];
        }
        foreach ($this->filePropertiesList as $fileProperty) {
            $post[$fileProperty->field] = new CURLFile(
                $fileProperty->filepath,
                null,
                $fileProperty->filename
            );
        }
        $this->curlFunctions->curl_setopt($this->curlHandle, CURLOPT_POSTFIELDS, $post);
    }

    private function curlSetPostDataWithSimilarFilename()
    {
        //cette fonction, bien que résolvant la limitation du problème de nom multiple de fichier
        //nécessite le chargement de l'ensemble des fichiers dans la mémoire.
        $boundary = $this->getBoundary();

        $body = [];

        foreach ($this->postDataList as $postData) {
                $body[] = "--$boundary";
                $body[] = "Content-Disposition: form-data; name=$postData[0]";
                $body[] = '';
                $body[] = $postData[1];
        }

        foreach ($this->filePropertiesList as $fileProperty) {
            $body[] = "--$boundary";
            $body[] = "Content-Disposition: form-data; name=$fileProperty->field; filename=\"$fileProperty->filename\"";
            $body[] = "Content-Type: $fileProperty->contentType";
            if ($fileProperty->contentTransferEncoding) {
                $body[] = "Content-Transfer-Encoding: " . $fileProperty->contentTransferEncoding;
            }
            $body[] = '';
            $body[] = file_get_contents($fileProperty->filepath);
        }

        $body[] = "--$boundary--";
        $body[] = '';

        $content = join(self::POST_DATA_SEPARATOR, $body);

        $curlHttpHeader[] = 'Content-Length: ' . strlen($content);
        $curlHttpHeader[] = 'Expect: 100-continue';
        $curlHttpHeader[] = "Content-Type: multipart/form-data; boundary=$boundary";

        $this->setProperties(CURLOPT_HTTPHEADER, $curlHttpHeader);
        $this->setProperties(CURLOPT_POSTFIELDS, $content);
    }

    public function getHTTPCode()
    {
        return $this->curlFunctions->curl_getinfo($this->curlHandle, CURLINFO_HTTP_CODE);
    }
}
