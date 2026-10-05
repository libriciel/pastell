<?php

declare(strict_types=1);

class SFTPFactory
{
    public const DEFAULT_HOST = 'localhost';
    public const DEFAULT_PORT = 22;

    /**
     * Host key algorithms supported by phpseclib 2, in the same order.
     * phpseclib 3 prefers ed25519/ecdsa, which makes the server present another
     * host key and invalidates the fingerprints already stored in connectors.
     */
    private const HOST_KEY_ALGORITHMS = [
        'rsa-sha2-256',
        'rsa-sha2-512',
        'ssh-rsa',
        'ssh-dss',
    ];

    public function getInstance(SFTPProperties $sftpProperties): SFTP
    {
        $netSFTP = new phpseclib3\Net\SFTP(
            $sftpProperties->host ?: self::DEFAULT_HOST,
            $sftpProperties->port ?: self::DEFAULT_PORT,
            $sftpProperties->timeout
        );
        $netSFTP->setPreferredAlgorithms(['hostkey' => self::HOST_KEY_ALGORITHMS]);
        return new SFTP($netSFTP, $sftpProperties);
    }
}
