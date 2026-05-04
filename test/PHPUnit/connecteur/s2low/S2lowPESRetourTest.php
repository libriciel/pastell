<?php

declare(strict_types=1);

class S2lowPESRetourTest extends PastellTestCase
{
    use CurlUtilitiesTestTrait;

    /**
     * @throws S2lowException
     * @throws Exception
     */
    public function testPESRetourList(): void
    {
        $this->mockCurlWithCallable(function ($url) {
            return match (true) {
                $url === '/admin/users/api-list-login.php' => true,
                $url === '/modules/helios/api/helios_get_list.php' =>
                    file_get_contents(__DIR__ . '/fixtures/pes_retour_liste.xml'),
                str_contains($url, '/modules/helios/api/helios_get_retour.php') => '<pes_retour></pes_retour>',
                str_contains($url, '/modules/helios/api/helios_change_status.php') =>
                    file_get_contents(__DIR__ . '/fixtures/pes_retour_change.xml'),
                default => throw new UnrecoverableException("Unexpected URL: $url"),
            };
        });

        $id_ce = $this->createConnector('s2low', 'Connecteur S2low')['id_ce'];
        /** @var S2low $s2low */
        $s2low = $this->getConnecteurFactory()->getConnecteurById($id_ce);

        $s2low->getPESRetourListe();

        $documentSQL = $this->getObjectInstancier()->getInstance(DocumentSQL::class);
        $info = $documentSQL->getAllByType(S2low::FLUX_PES_RETOUR);

        static::assertSame('PES_AAA.122', $info[0]['titre']);
        static::assertSame('PES_AAA.123', $info[1]['titre']);

        $donnesFormulaire = $this->getDonneesFormulaireFactory()->get($info[0]['id_d']);
        static::assertSame('<pes_retour></pes_retour>', $donnesFormulaire->getFileContent('fichier_pes'));
    }

    /**
     * @throws NotFoundException
     * @throws S2lowException
     * @throws Exception
     */
    public function testPesRetourCreation(): void
    {
        $this->mockCurl([
            '/admin/users/api-list-login.php' => 'ok',
            '/modules/helios/api/helios_get_retour.php?id=1234' => '<pes_retour></pes_retour>',
            '/modules/helios/api/helios_change_status.php?id=1234' => '<pes_retour></pes_retour>',
        ]);

        $id_ce = $this->createConnector('s2low', 'Connecteur S2low')['id_ce'];
        /** @var S2low $s2low */
        $s2low = $this->getConnecteurFactory()->getConnecteurById($id_ce);

        static::assertTrue($s2low->getPESRetour([
            'nom' => 'pes.xml',
            'date' => '2019/07/16',
            'id' => '1234',
        ]));

        $documentSQL = $this->getObjectInstancier()->getInstance(DocumentSQL::class);
        $documents = $documentSQL->getAllByType(S2low::FLUX_PES_RETOUR);

        static::assertSame('pes', $documents[0]['titre']);

        $donnesFormulaire = $this->getDonneesFormulaireFactory()->get($documents[0]['id_d']);
        static::assertSame('1', $donnesFormulaire->get('envoi_ged'));
    }
}
