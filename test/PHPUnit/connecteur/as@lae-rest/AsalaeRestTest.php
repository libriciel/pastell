<?php

declare(strict_types=1);

use Monolog\Logger;

class AsalaeRestTest extends PastellTestCase
{
    use CurlUtilitiesTestTrait;

    private function getAsalaeRest(
        string $curl_response,
        int $http_code = 200,
        int $chunk_size_in_bytes = 0
    ): AsalaeREST {
        $this->mockCurl([
            'https://sae/restservices/ping' => $curl_response,
            'https://sae/restservices/versions' => $curl_response,
            'https://sae/restservices/sedaMessages' => $curl_response,
            'https://sae/restservices/sedaAttachmentsChunkFiles' => $curl_response,
        ], $http_code);

        $connecteurConfig = $this->getDonneesFormulaireFactory()->getNonPersistingDonneesFormulaire();
        $connecteurConfig->setTabData([
            'url' => 'https://sae/restservices',
            'login' => 'login',
            'password' => 'password',
            'chunk_size_in_bytes' => $chunk_size_in_bytes,
        ]);
        $asalaeRest = new AsalaeREST(
            $this->getObjectInstancier()->getInstance(CurlWrapperFactory::class),
            $this->getObjectInstancier()->getInstance(Logger::class)
        );
        $asalaeRest->setConnecteurConfig($connecteurConfig);
        return $asalaeRest;
    }

    /**
     * @throws Exception
     */
    public function testPing(): void
    {
        $asalaeRest = $this->getAsalaeRest(
            '"webservices as@lae accessibles"'
        );
        static::assertSame('webservices as@lae accessibles', $asalaeRest->ping());
    }

    /**
     * @throws Exception
     */
    public function testPingNotResponding(): void
    {
        $asalaeRest = $this->getAsalaeRest('');
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('');
        $asalaeRest->ping();
    }

    /**
     * @throws Exception
     */
    public function testPingFailed404(): void
    {
        $asalaeRest = $this->getAsalaeRest('"test"', 404);
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('"test" - code d\'erreur HTTP : 404');
        $asalaeRest->ping();
    }

    /**
     * @throws Exception
     */
    public function testPingNoJson(): void
    {
        $asalaeRest = $this->getAsalaeRest('toto');
        $this->expectException(Exception::class);
        $this->expectExceptionMessage(
            'Le serveur asalae n\'a pas renvoyé une réponse compréhensible - problème de configuration ? : toto'
        );
        $asalaeRest->ping();
    }

    /**
     * @throws Exception
     */
    public function testGetVersion(): void
    {
        $asalaeRest = $this->getAsalaeRest(
            '{"application":"as@lae","denomination":"","version":"V1.6.3"}'
        );
        static::assertSame('V1.6.3', $asalaeRest->getVersion()['version']);
    }

    /**
     * @throws Exception
     */
    public function testSendArchive(): void
    {
        $asalaeRest = $this->getAsalaeRest('"ok"');
        static::assertSame(
            '2020-05-12-ACTES-18',
            $asalaeRest->sendSIP(
                file_get_contents(__DIR__ . '/../../connecteur-type/fixtures/bordereau_seda_2.1.xml'),
                __FILE__
            )
        );
    }

    /**
     * @throws Exception
     */
    public function testSendArchiveInChunk(): void
    {
        $tmpFolder = new TmpFolder();
        $tmp_folder = $tmpFolder->create();

        copy(__DIR__ . '/../../fixtures/vide.pdf', $tmp_folder . '/archive.tar.gz');

        $chunksize = filesize(__DIR__ . '/../../fixtures/vide.pdf') / 2;

        $asalaeRest = $this->getAsalaeRest(
            '{"chunk_session_identifier":12,"chunk_security_identifier":42}',
            200,
            $chunksize
        );
        static::assertSame(
            '2020-05-12-ACTES-18',
            $asalaeRest->sendSIP(
                file_get_contents(__DIR__ . '/../../connecteur-type/fixtures/bordereau_seda_2.1.xml'),
                $tmp_folder . '/archive.tar.gz'
            )
        );
    }

    /**
     * @throws Exception
     */
    public function testSendArchiveInChunkFailed(): void
    {
        $tmpFolder = new TmpFolder();
        $tmp_folder = $tmpFolder->create();

        copy(__DIR__ . '/../../fixtures/vide.pdf', $tmp_folder . '/archive.tar.gz');

        $chunksize = filesize(__DIR__ . '/../../fixtures/vide.pdf') / 2;

        $asalaeRest = $this->getAsalaeRest('"ok"', 200, $chunksize);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Cette version d'asalae ne permet pas l'envoi d'archive par morceaux");
        $asalaeRest->sendSIP('test bordereau SEDA', $tmp_folder . '/archive.tar.gz');
    }

    /**
     * @throws Exception
     */
    public function testSendArchiveFailed(): void
    {
        $asalaeRest = $this->getAsalaeRest('');
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('');
        $asalaeRest->sendSIP('test bordereau SEDA', __FILE__);
    }
}
