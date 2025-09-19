<?php

class DepotSFTP extends DepotConnecteur
{
    public const DEPOT_SFTP_HOST = 'depot_sftp_host';
    public const DEPOT_SFTP_PORT = 'depot_sftp_port';
    public const DEPOT_SFTP_PORT_DEFAULT = 22;
    public const DEPOT_SFTP_LOGIN = 'depot_sftp_login';
    public const DEPOT_SFTP_PASSWORD = 'depot_sftp_password';
    public const DEPOT_SFTP_FINGERPRINT = 'depot_sftp_fingerprint';
    public const DEPOT_SFTP_DIRECTORY = 'depot_sftp_directory';

    public const DEPOT_SFTP_RENAME_SUFFIX = 'depot_sftp_rename_suffix';

    /** @var  SFTPFactory  */
    private $sftpFactory;

    /** @var  SFTP */
    private $sftp;

    private $depot_sftp_rename_suffix;

    public function __construct(SFTPFactory $SFTPFactory)
    {
        $this->sftpFactory = $SFTPFactory;
    }

    public function setConnecteurConfig(DonneesFormulaire $donneesFormulaire)
    {
        parent::setConnecteurConfig($donneesFormulaire);
        $sftpProperties = new SFTPProperties();
        $sftpProperties->host = $donneesFormulaire->get(self::DEPOT_SFTP_HOST);
        $sftpProperties->port = $donneesFormulaire->get(self::DEPOT_SFTP_PORT) ?: self::DEPOT_SFTP_PORT_DEFAULT;
        $sftpProperties->login = $donneesFormulaire->get(self::DEPOT_SFTP_LOGIN);
        $sftpProperties->password = $donneesFormulaire->get(self::DEPOT_SFTP_PASSWORD);
        $sftpProperties->verifyFingerprint = true;
        $sftpProperties->fingerprint = $donneesFormulaire->get(self::DEPOT_SFTP_FINGERPRINT);

        $this->sftp = $this->sftpFactory->getInstance($sftpProperties);

        $this->depot_sftp_rename_suffix = $donneesFormulaire->get(self::DEPOT_SFTP_RENAME_SUFFIX);
    }

    /**
     * @return mixed
     * @throws Exception
     */
    public function listDirectory()
    {
        return $this->sftp->listDirectory($this->connecteurConfig->get(self::DEPOT_SFTP_DIRECTORY));
    }

    /**
     * @param string $directory_name
     * @return string
     * @throws Exception
     */
    public function makeDirectory(string $directory_name)
    {
        $new_directory_name = $this->getAbsolutePath($directory_name);
        $this->sftp->mkdir($new_directory_name);
        return $new_directory_name;
    }

    /**
     * @param string $directory_name
     * @param string $filename
     * @param string $filepath
     * @return string
     * @throws Exception
     */
    public function saveDocument(string $directory_name, string $filename, string $filepath)
    {
        $new_filepath = $this->getAbsolutePath($directory_name, $filename);

        if ($this->depot_sftp_rename_suffix) {
            $tmp_filepath = $new_filepath . $this->depot_sftp_rename_suffix;
            $this->getLogger()->debug("dépôt du fichier $tmp_filepath");
            $this->sftp->put($tmp_filepath, $filepath);
            $this->getLogger()->debug("Renommage du fichier $tmp_filepath -> $new_filepath");
            $this->sftp->rename($tmp_filepath, $new_filepath);
        } else {
            $this->sftp->put($new_filepath, $filepath);
        }

        return $new_filepath;
    }


    /**
     * @param string $item_name
     * @return mixed
     * @throws Exception
     */
    private function itemExists(string $item_name)
    {
        return array_reduce(
            $this->listDirectory(),
            function ($carry, $item) use ($item_name) {
                $carry = $carry || basename($item) == $item_name;
                return $carry;
            }
        );
    }

    /**
     * @param string $directory_name
     * @return mixed
     * @throws Exception
     */
    public function directoryExists(string $directory_name)
    {
        return $this->itemExists($directory_name);
    }

    /**
     * @param string $filename
     * @return mixed
     * @throws Exception
     */
    public function fileExists(string $filename)
    {
        return $this->itemExists($filename);
    }
    private function getAbsolutePath($directory_or_file_name, $filename = false)
    {
        $directory_or_file_name = $this->sanitizeFilename($directory_or_file_name);
        $result = rtrim($this->connecteurConfig->get(self::DEPOT_SFTP_DIRECTORY), "/") . "/" . $directory_or_file_name;
        if ($filename) {
            $result .= "/" . $this->sanitizeFilename($filename);
        }
        return $result;
    }

    private function sanitizeFilename($filename)
    {
        return strtr($filename, "/", "_");
    }
}
