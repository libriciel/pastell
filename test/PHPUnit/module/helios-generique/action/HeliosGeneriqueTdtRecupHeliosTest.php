<?php

declare(strict_types=1);

class HeliosGeneriqueTdtRecupHeliosTest extends PastellTestCase
{
    use CurlUtilitiesTestTrait;

    /**
     * @throws NotFoundException
     * @throws DonneesFormulaireException
     */
    public function testCasNominal(): void
    {
        $info_connecteur = $this->createConnector('fakeTdt', 'Bouchon Tdt');
        $this->associateFluxWithConnector($info_connecteur['id_ce'], 'helios-generique', 'TdT');

        $info = $this->createDocument('helios-generique');

        $donneesFormulaire = $this->getDonneesFormulaireFactory()->get($info['id_d']);
        $donneesFormulaire->setTabData(['objet' => 'Foo','envoi_tdt' => 'On']);
        $donneesFormulaire->addFileFromCopy(
            'fichier_pes',
            'HELIOS_SIMU_ALR2_1496987735_826268894.xml',
            __DIR__ . '/../fixtures/HELIOS_SIMU_ALR2_1496987735_826268894.xml'
        );

        static::assertTrue(
            $this->triggerActionOnDocument($info['id_d'], 'send-tdt')
        );

        static::assertTrue(
            $this->triggerActionOnDocument($info['id_d'], 'verif-tdt')
        );

        $donneesFormulaire = $this->getDonneesFormulaireFactory()->get($info['id_d']);

        static::assertSame(
            'HELIOS_SIMU_ALR2_1496987735_826268894_ACK.xml',
            $donneesFormulaire->getFileName('fichier_reponse')
        );
        static::assertSame('1', $donneesFormulaire->get('etat_ack'));
    }

    /**
     * @throws NotFoundException
     * @throws DonneesFormulaireException
     */
    public function testWhenErrorOnTdt(): void
    {
        $this->mockCurl([
            '/admin/users/api-list-login.php' => 'ok',
            '/modules/helios/api/helios_importer_fichier.php' =>
                file_get_contents(__DIR__ . '/../fixtures/helios-post.xml'),
            '/modules/helios/api/helios_transac_get_status.php?transaction=1234' =>
                file_get_contents(__DIR__ . '/../fixtures/helios-reponse.xml'),
        ]);

        $info_connecteur = $this->createConnector('s2low', 's2low');

        $this->associateFluxWithConnector($info_connecteur['id_ce'], 'helios-generique', 'TdT');

        $info = $this->createDocument('helios-generique');

        $donneesFormulaire = $this->getDonneesFormulaireFactory()->get($info['id_d']);
        $donneesFormulaire->setTabData(['objet' => 'Foo','envoi_tdt' => 'On']);
        $donneesFormulaire->addFileFromCopy(
            'fichier_pes',
            'fichier.xml',
            __DIR__ . '/../fixtures/HELIOS_SIMU_ALR2_1496987735_826268894.xml'
        );

        static::assertTrue(
            $this->triggerActionOnDocument($info['id_d'], 'send-tdt')
        );
        static::assertFalse(
            $this->triggerActionOnDocument($info['id_d'], 'verif-tdt')
        );
        $this->assertLastMessage(
            "Transaction en erreur sur le TdT: Ceci est un message d'erreur avec accent à é ç"
        );
    }
}
