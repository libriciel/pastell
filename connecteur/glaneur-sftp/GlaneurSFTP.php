<?php

class GlaneurSFTP extends GlaneurConnecteur
{
    public const GLANEUR_SFTP_HOST = "glaneur_sftp_host";
    public const GLANEUR_SFTP_PORT = "glaneur_sftp_port";
    public const GLANEUR_SFTP_LOGIN = "glaneur_sftp_login";
    public const GLANEUR_SFTP_PASSWORD = "glaneur_sftp_password";
    public const GLANEUR_SFTP_FINGERPRINT = "glaneur_sftp_fingerprint";

    /** @var SFTP */
    private $sftp;

    /** @var SFTPFactory */
    private $sftpFactory;

    public function __construct(
        DocumentTypeFactory $documentTypeFactory,
        GlaneurDocumentCreator $glaneurLocalDocumentCreator,
        SFTPFactory $sftpFactory
    ) {
        parent::__construct($documentTypeFactory, $glaneurLocalDocumentCreator);
        $this->setSFTPFactory($sftpFactory);
    }

    public function setSFTPFactory(SFTPFactory $sftpFactory)
    {
        $this->sftpFactory = $sftpFactory;
    }

    /**
     * @return SFTP
     */
    private function getSFTP()
    {
        if ($this->sftp === null) {
            $sftpProperties = new SFTPProperties();
            $sftpProperties->fingerprint = $this->connecteurConfig->get(self::GLANEUR_SFTP_FINGERPRINT);
            $sftpProperties->host = $this->connecteurConfig->get(self::GLANEUR_SFTP_HOST);
            $sftpProperties->login = $this->connecteurConfig->get(self::GLANEUR_SFTP_LOGIN);
            $sftpProperties->password = $this->connecteurConfig->get(self::GLANEUR_SFTP_PASSWORD);
            $sftpProperties->port = (int)$this->connecteurConfig->get(self::GLANEUR_SFTP_PORT);
            $this->sftp = $this->sftpFactory->getInstance($sftpProperties);
        }
        return $this->sftp;
    }

    /**
     * @param string $directory
     * @return array
     * @throws Exception
     */
    protected function listAllFile(string $directory): array
    {
        return array_filter($this->getSFTP()->listDirectory($directory), function ($a) {
            return ! in_array($a, [".",".."]);
        });
    }

    /**
     * @param string $directory
     * @return array
     * @throws Exception
     */
    protected function listFile(string $directory): array
    {
        $list = $this->getSFTP()->listDirectory($directory);
        $count = count($list) - 2;

        $new_list = array_slice($list, 0, GlaneurConnecteur::NB_MAX_FILE_DISPLAY);
        $detail = implode(',', $new_list);

        return ['count' => $count,'detail' => $detail];
    }

    /**
     * @throws Exception
     */
    protected function getNextItem(string $directory): ?string
    {
        $list = $this->getSFTP()->listDirectory($directory);

        foreach ($list as $file) {
            if (in_array($file, [".",".."])) {
                continue;
            }
            return $file;
        }
        return null;
    }

    /**
     * @param string $directory_or_file
     * @return bool
     * @throws Exception
     */
    protected function isDir(string $directory_or_file): bool
    {
        return $this->getSFTP()->isDir($directory_or_file);
    }

    /**
     * @param string $directory
     * @param string $tmp_folder
     * @throws Exception
     */
    protected function mirror(string $directory, string $tmp_folder)
    {
        $local_sftp = $this->getSFTP();
        if (! $local_sftp->isDir($directory)) {
            $local_sftp->get($directory, $tmp_folder . "/$directory");
        }


        foreach ($local_sftp->listDirectory($directory) as $file) {
            if (in_array($file, [".",".."])) {
                continue;
            }
            if ($local_sftp->isDir($directory . "/" . $file)) {
                mkdir($tmp_folder . "/" . $file);
                $this->mirror($directory . "/" . $file, $tmp_folder . "/" . $file);
            } else {
                $local_sftp->get($directory . "/" . $file, $tmp_folder . "/" . $file);
            }
        }
    }

    /**
     * @param array $item_list
     * @return mixed|void
     * @throws Exception
     */
    protected function remove(array $item_list)
    {
        foreach ($item_list as $item) {
            $this->getSFTP()->delete($item);
        }
    }

    /**
     * @param string $file_or_directory
     * @return bool
     * @throws Exception
     */
    protected function exists(string $file_or_directory): bool
    {
        return $this->getSFTP()->exists($file_or_directory);
    }

    /**
     * @param string $item
     * @param string $file_deplacement
     * @throws Exception
     */
    protected function rename(string $item, string $file_deplacement)
    {
        $this->getSFTP()->rename($item, $file_deplacement);
    }

    /**
     * @param string $originFile
     * @param string $targetFile
     * @return mixed|void
     * @throws Exception
     */
    protected function copy(string $originFile, string $targetFile)
    {
        $this->getSFTP()->get($originFile, $targetFile);
    }
}
