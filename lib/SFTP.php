<?php

declare(strict_types=1);

class SFTP
{
    private bool $isLogged = false;

    public function __construct(
        private readonly phpseclib3\Net\SFTP $netSFTP,
        private readonly SFTPProperties $sftpProperties,
    ) {
    }

    /**
     * @throws UnrecoverableException
     */
    public function listDirectory(string $directory): array
    {
        $this->login();
        $result = $this->netSFTP->nlist($directory);
        if ($result === false) {
            $this->throwLastError("Impossible de lister le répertoire $directory");
        }
        return $result;
    }

    /**
     * @throws UnrecoverableException
     */
    public function get(string $remote_path, string $local_path): bool
    {
        $this->login();
        if (! $this->netSFTP->get($remote_path, $local_path)) {
            $this->throwLastError("Impossible de récupérer le fichier $remote_path");
        }
        return true;
    }

    /**
     * @throws UnrecoverableException
     */
    public function put(string $remote_path, string $local_path): bool
    {
        $this->login();
        $result = $this->netSFTP->put(
            $remote_path,
            $local_path,
            phpseclib3\Net\SFTP::SOURCE_LOCAL_FILE
        );
        if (! $result) {
            $this->throwLastError("Impossible de déposer le fichier $remote_path");
        }
        return true;
    }

    /**
     * @throws UnrecoverableException
     */
    public function rename(string $from, string $to): bool
    {
        $this->login();
        if (! $this->netSFTP->rename($from, $to)) {
            $this->throwLastError("Impossible de renommer $from en $to");
        }
        return true;
    }

    /**
     * @throws UnrecoverableException
     */
    public function delete(string $remote_path): bool
    {
        $this->login();
        if (! $this->netSFTP->delete($remote_path)) {
            $this->throwLastError("Impossible de supprimer $remote_path");
        }
        return true;
    }

    /**
     * @throws UnrecoverableException
     */
    public function mkdir(string $remote_path): bool
    {
        $this->login();
        if (! $this->netSFTP->mkdir($remote_path)) {
            $this->throwLastError("Impossible de créer le répertoire $remote_path");
        }
        return true;
    }

    /**
     * @throws UnrecoverableException
     */
    private function login(): void
    {
        if ($this->isLogged) {
            return;
        }
        try {
            $error = $this->netSFTP->login(
                $this->sftpProperties->login,
                $this->sftpProperties->password
            );
            if ($error === false) {
                throw new UnrecoverableException('Impossible de se connecter au serveur SFTP');
            }
        } catch (Exception $e) {
            throw new UnrecoverableException($e->getMessage(), 0, $e);
        }
        $serverFingerprint = $this->getFingerprint();
        if ($this->sftpProperties->verifyFingerprint && $serverFingerprint !== $this->sftpProperties->fingerprint) {
            throw new UnrecoverableException(
                "L'empreinte du serveur ($serverFingerprint) ne correspond pas"
            );
        }
        $this->isLogged = true;
    }

    /**
     * @throws UnrecoverableException
     */
    private function throwLastError(string $defaultMessage): never
    {
        throw new UnrecoverableException($this->netSFTP->getLastSFTPError() ?: $defaultMessage);
    }

    /**
     * @throws UnrecoverableException
     */
    private function getFingerprint(): string
    {
        $serverPublicHostKey = $this->netSFTP->getServerPublicHostKey();
        if ($serverPublicHostKey === false) {
            throw new UnrecoverableException('Impossible de récupérer la clé publique du serveur');
        }
        $hostKey = substr($serverPublicHostKey, 8);
        $hostKey = sha1($hostKey) ;
        return  strtoupper($hostKey);
    }

    /**
     * @throws UnrecoverableException
     */
    public function isDir(string $file_or_directory): bool
    {
        $this->login();
        return $this->netSFTP->is_dir($file_or_directory);
    }

    /**
     * @throws UnrecoverableException
     */
    public function exists(string $file_or_directory): bool
    {
        $this->login();
        return $this->netSFTP->file_exists($file_or_directory);
    }
}
