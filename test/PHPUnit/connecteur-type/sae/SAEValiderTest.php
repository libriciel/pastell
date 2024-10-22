<?php

declare(strict_types=1);

class SAEValiderTest extends PastellTestCase
{
    use CurlUtilitiesTestTrait;

    /**
     * @throws NotFoundException
     */
    public function testValiderSEDAV0_2(): void
    {
        $id_d = $this->prepareValidation(__DIR__ . "/fixtures/ATR-SEDA-0.2.xml");

        $this->triggerActionOnDocument($id_d, 'validation-sae');
        $this->assertLastMessage("La transaction a été acceptée par le SAE");

        $this->assertLastDocumentAction('accepter-sae', $id_d);

        $donnesFormulaire = $this->getDonneesFormulaireFactory()->get($id_d);
        $this->assertEquals("62", $donnesFormulaire->get('sae_archival_identifier'));
        $this->assertEquals('ATA_2.xml', $donnesFormulaire->getFileName('reply_sae'));
        $this->assertEquals(
            'Votre transfert d\'archive a été accepté par la plate-forme as@lae',
            $donnesFormulaire->get('sae_atr_comment')
        );
    }

    /**
     * @throws NotFoundException
     */
    public function testValiderSEDAV1_0(): void
    {
        $id_d = $this->prepareValidation(__DIR__ . "/fixtures/ATR-SEDA-1.0.xml");

        $this->triggerActionOnDocument($id_d, 'validation-sae');
        $this->assertLastDocumentAction('accepter-sae', $id_d);

        $donnesFormulaire = $this->getDonneesFormulaireFactory()->get($id_d);
        $this->assertEquals("160", $donnesFormulaire->get('sae_archival_identifier'));
        $this->assertEquals('ATR_213.xml', $donnesFormulaire->getFileName('reply_sae'));
        $this->assertEquals(
            'Votre transfert d\'archive a été accepté par la plate-forme as@lae',
            $donnesFormulaire->get('sae_atr_comment')
        );
    }

    /**
     * @throws NotFoundException
     */
    public function testValiderSEDAV2_1(): void
    {
        $id_d = $this->prepareValidation(__DIR__ . "/fixtures/ATR-SEDA-2.1.xml");

        $this->triggerActionOnDocument($id_d, 'validation-sae');
        $this->assertLastMessage("La transaction a été acceptée par le SAE");

        $this->assertLastDocumentAction('accepter-sae', $id_d);

        $donnesFormulaire = $this->getDonneesFormulaireFactory()->get($id_d);
        $this->assertEquals("AE_2022_15", $donnesFormulaire->get('sae_archival_identifier'));
        $this->assertEquals('FRLSADC_ATR_27.xml', $donnesFormulaire->getFileName('reply_sae'));
        $this->assertEquals(
            'Versement flux test pastell dev',
            $donnesFormulaire->get('sae_atr_comment')
        );
    }

    /**
     * @throws NotFoundException
     */
    public function testWhenArchiveIsRejected(): void
    {
        $id_d = $this->prepareValidation(__DIR__ . "/fixtures/atr-rejet.xml");
        $this->triggerActionOnDocument($id_d, 'validation-sae');
        $this->assertLastMessage("La transaction a été refusée par le SAE. Parce que je le vaut bien (Archive refusée - code de retour : 300)");
        $this->assertLastDocumentAction('rejet-sae', $id_d);

        $donnesFormulaire = $this->getDonneesFormulaireFactory()->get($id_d);
        $this->assertFalse($donnesFormulaire->get('sae_archival_identifier'));
        $this->assertEquals('ATR_220.xml', $donnesFormulaire->getFileName('reply_sae'));
        $this->assertEquals(
            'Parce que je le vaut bien (Archive refusée - code de retour : 300)',
            $donnesFormulaire->get('sae_atr_comment')
        );
    }

    private function prepareValidation(string $sae_reponse_file): string
    {
        $this->mockCurl([
            'https://sae/sedaMessages/sequence:ArchiveTransfer/message:ArchiveTransferReply/' .
            'originOrganizationIdentification:LS_PA/' .
            'originMessageIdentifier:15ef78ef665a8777c33d1125783707f8dfb190f82869dc9248e46c5ed396d70b_1542893421'
            => file_get_contents($sae_reponse_file),
        ]);

        $id_ce = $this->createConnector('as@lae-rest', 'Asalae')['id_ce'];
        $this->associateFluxWithConnector($id_ce, 'actes-generique', 'SAE');
        $this->configureConnector($id_ce, [
            'url' => 'https://sae',
            'login' => 'login',
            'password' => 'password',
        ]);

        $id_d = $this->createDocument('actes-generique')['id_d'];
        $donnesFormulaire = $this->getDonneesFormulaireFactory()->get($id_d);
        $donnesFormulaire->setTabData([
            'sae_transfert_id' => '15ef78ef665a8777c33d1125783707f8dfb190f82869dc9248e46c5ed396d70b_1542893421',
        ]);

        $donnesFormulaire->addFileFromCopy(
            'sae_bordereau',
            'bordereau.xml',
            __DIR__ . '/fixtures/bordereau.xml'
        );

        $this->getObjectInstancier()
            ->getInstance(ActionChange::class)
            ->addAction($id_d, self::ID_E_COL, 0, 'verif-sae', 'test');
        return $id_d;
    }
}
