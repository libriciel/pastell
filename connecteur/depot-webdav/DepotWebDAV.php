<?php

// Un docker pour tester :  docker run -d -e USERNAME=test -e PASSWORD=test -p 8888:80 morrisjobke/webdav


class DepotWebDAV extends DepotConnecteur
{
    public const DEPOT_WEBDAV_URL = 'depot_webdav_url';
    public const DEPOT_WEBDAV_LOGIN = 'depot_webdav_login';
    public const DEPOT_WEBDAV_PASSWORD = 'depot_webdav_password';

    /** @var  WebdavWrapper */
    private $webDavWrapper;

    public function __construct(WebdavWrapper $webdavWrapper)
    {
        $this->webDavWrapper = $webdavWrapper;
    }

    public function setConnecteurConfig(DonneesFormulaire $connecteurConfig)
    {
        parent::setConnecteurConfig($connecteurConfig);
        $this->webDavWrapper->setDataConnexion(
            $connecteurConfig->get(self::DEPOT_WEBDAV_URL),
            $connecteurConfig->get(self::DEPOT_WEBDAV_LOGIN),
            $connecteurConfig->get(self::DEPOT_WEBDAV_PASSWORD)
        );
    }

    public function listDirectory()
    {
        return $this->webDavWrapper->listFolder("");
    }

    public function makeDirectory(string $directory_name)
    {
        $this->webDavWrapper->createFolder("", $directory_name);
        return $directory_name;
    }

    public function saveDocument(string $directory_name, string $filename, string $filepath)
    {
        $this->webDavWrapper->addDocument($directory_name, $filename, file_get_contents($filepath));
        return rtrim($this->connecteurConfig->get(self::DEPOT_WEBDAV_URL), '/') . "/" . rawurlencode($directory_name) . "/" . rawurlencode($filename);
    }

    public function directoryExists(string $directory_name)
    {
        return $this->webDavWrapper->exists($directory_name);
    }

    public function fileExists(string $filename)
    {
        return $this->directoryExists($filename);
    }
}
