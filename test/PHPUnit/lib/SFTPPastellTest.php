<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class SFTPPastellTest extends TestCase
{
    private SFTP $sftp;
    private SFTPProperties $sftpProperties;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sftpProperties = new SFTPProperties();
        $this->sftpProperties->host = 'localhost';
        $this->sftpProperties->login = 'admin';
        $this->sftpProperties->password = 'password';
        $this->sftpProperties->fingerprint = 'SHA256:rAWiWt2q2wyjOKXejvA7Q8nU6gTOJMuKeF8ug4XFzoE';
        $this->setSFTP();
    }

    private function setSFTP(): void
    {
        $netSFTP = $this->createMock(\phpseclib3\Net\SFTP::class);

        $closure = function ($a) {
            if ($a === 'foo bar') {
                throw new Exception('NET_SFTP_STATUS_NO_SUCH_FILE: No such file');
            }
            return   ['.','..','foo'];
        };

        $netSFTP
            ->method('nlist')
            ->willReturnCallback($closure);
        if ($this->sftpProperties->host === 'foo') {
            $netSFTP
                ->method('login')
                ->willThrowException(new Exception('Cannot connect to foo:22'));
        }
        $netSFTP
            ->method('getServerPublicHostKey')
            ->willReturn(file_get_contents(__DIR__ . '/fixtures/ssh_server_public_key.txt'));
        $this->sftp = new SFTP($netSFTP, $this->sftpProperties);
    }


    /**
     * @throws UnrecoverableException
     */
    public function testListDirectory(): void
    {
        $result = $this->sftp->listDirectory('/tmp/');
        static::assertContains('foo', $result);
    }

    public function testBadHost(): void
    {
        $this->sftpProperties->host = 'foo';
        $this->setSFTP();
        $this->expectException(UnrecoverableException::class);
        $this->expectExceptionMessageMatches('#Cannot connect to foo:22#');
        $this->sftp->listDirectory('/tmp/');
    }

    /**
     * @throws UnrecoverableException
     */
    public function testBadDirectory(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('NET_SFTP_STATUS_NO_SUCH_FILE: No such file');
        $this->sftp->listDirectory('foo bar');
    }

    public function testBadFingerPrint(): void
    {
        $this->sftpProperties->fingerprint = 'foo';
        $this->setSFTP();
        $this->expectException(UnrecoverableException::class);
        $this->expectExceptionMessage(
            "L'empreinte du serveur (SHA256:rAWiWt2q2wyjOKXejvA7Q8nU6gTOJMuKeF8ug4XFzoE) ne correspond pas"
        );
        $this->sftp->listDirectory('/tmp/');
    }

    /**
     * @throws UnrecoverableException
     */
    public function testRetrieveFile(): void
    {
        static::assertTrue(
            $this->sftp->get('/Users/eric/test1', '/var/tmp/toto')
        );
    }

    /**
     * @throws UnrecoverableException
     */
    public function testPut(): void
    {
        static::assertTrue($this->sftp->put('/tmp/test42', '/tmp/put.txt'));
    }

    /**
     * @throws UnrecoverableException
     */
    public function testDelete(): void
    {
        static::assertTrue($this->sftp->delete('/tmp/test42'));
    }

    /**
     * @throws UnrecoverableException
     */
    public function testMkdir(): void
    {
        static::assertTrue(
            $this->sftp->mkdir('/tmp/bar')
        );
    }

    /**
     * @throws UnrecoverableException
     */
    public function testRename(): void
    {
        static::assertTrue(
            $this->sftp->rename('foo', 'bar')
        );
    }
}
