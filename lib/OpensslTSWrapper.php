<?php

use Pastell\Process\CommandResult;
use Pastell\Process\CommandRunner;

class OpensslTSWrapper
{
    private string $lastError;
    private string $hashAlgorithm;

    public function __construct(
        private readonly string $opensslPath,
        private readonly CommandRunner $commandRunner,
    ) {
        $this->setHashAlgorithm('sha1');
    }

    public function setHashAlgorithm(string $hashAlgorithm): void
    {
        if (in_array($hashAlgorithm, ['sha1','sha256',])) {
            $this->hashAlgorithm = $hashAlgorithm;
        }
    }

    public function getLastError(): string
    {
        return $this->lastError;
    }
    private function execute(array $command): CommandResult
    {
        return $this->commandRunner->run($command);
    }

    private function getTmpFile($data = "")
    {
        $file_path = sys_get_temp_dir()  . "/" . mt_rand();
        file_put_contents($file_path, $data);
        return $file_path;
    }

    public function getTimestampQuery($data)
    {
        $dataFilePath = $this->getTmpFile($data);

        $command = [
            $this->opensslPath, 'ts', '-query',
            '--' . $this->hashAlgorithm,
            '-data', $dataFilePath,
            '-cert',
        ];
        $result = $this->execute($command);

        unlink($dataFilePath);
        return $result->stdout;
    }

    public function getTimestampQueryString($timestampQuery)
    {
        $timestampQueryFilePath = $this->getTmpFile($timestampQuery);
        $command = [
            $this->opensslPath, 'ts', '-query',
            '-in',  $timestampQueryFilePath,
            '-text',
        ];
        $result = $this->execute($command);

        unlink($timestampQueryFilePath);
        return $result->stdout;
    }

    public function getTimestampReplyString($timestampReply)
    {
        $timestampReplyFilePath = $this->getTmpFile($timestampReply);
        $command = [
            $this->opensslPath, 'ts', '-reply',
            '-in', $timestampReplyFilePath,
            '-text',
        ];
        $result = $this->execute($command);

        unlink($timestampReplyFilePath);
        return $result->stdout;
    }


    public function verify($data, $timestampReply, $CAFilePath, $certFilePath, $configFile)
    {
        $dataFilePath = $this->getTmpFile($data);
        $timestampReplyFilePath = $this->getTmpFile($timestampReply);

        $command = [
            $this->opensslPath, 'ts', '-verify',
            '-data', $dataFilePath,
            '-in', $timestampReplyFilePath,
            '-CAfile', $CAFilePath,
            '-untrusted', $certFilePath,
            '-config', $configFile,
        ];
        $result = $this->execute($command);

        if (!$result->isSuccessful()) {
            $this->lastError = $result->stdout;
        }

        unlink($dataFilePath);
        unlink($timestampReplyFilePath);

        return $result->isSuccessful();
    }

    public function createTimestampReply(
        $timestampRequest,
        $signerCertificate,
        $signerKey,
        $signerKeyPassword,
        $configFile
    ) {
        $timestampRequestFile = $this->getTmpFile($timestampRequest);
        $timestampReplyFile = $this->getTmpFile("");

        $command = [
            $this->opensslPath, 'ts', '-reply',
            '-queryfile', $timestampRequestFile,
            '-signer', $signerCertificate,
            '-inkey', $signerKey,
            '-passin', 'pass:' . $signerKeyPassword,
            '-out', $timestampReplyFile,
            '-config', $configFile,
        ];

        //TODO vérifier le retour
        $this->execute($command);

        $timestampReply = file_get_contents($timestampReplyFile);
        unlink($timestampRequestFile);
        unlink($timestampReplyFile);
        return $timestampReply;
    }
}
