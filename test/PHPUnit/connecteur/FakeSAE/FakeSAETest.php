<?php

class FakeSAETest extends PastellTestCase
{
    private const ACTES_GENERIQUE = "actes-generique";

    public static function dataProvider()
    {
        return [
            [
                [],
                "accepter-sae",
                'Ce transfert à été accepté par un connecteur bouchon SAE et n\'est donc pas réellement archivé !'
            ],
            [
                ['result_verif' => 2],
                "rejet-sae",
                'Votre transfert d\'archive a été rejeté par la plate-forme as@lae (Archive refusée - code de retour : 300)'
            ]
        ];
    }

    /**
     * @param array $fake_sae_configuration
     * @param string $last_action_expected
     * @param string $sae_atr_comment_expected
     * @throws NotFoundException
     * @dataProvider dataProvider
     */
    public function testARI(array $fake_sae_configuration, string $last_action_expected, string $sae_atr_comment_expected)
    {
        $id_ce = $this->createConnector('fakeSAE', "Bouchon SAE")['id_ce'];
        $this->configureConnector($id_ce, $fake_sae_configuration);

        $this->associateFluxWithConnector($id_ce, self::ACTES_GENERIQUE, "SAE");

        $id_ce = $this->createConnector('FakeSEDA', "Bouchon SEDA")['id_ce'];
        $this->associateFluxWithConnector($id_ce, "actes-generique", "Bordereau SEDA");

        $id_d = $this->createDocument(self::ACTES_GENERIQUE)['id_d'];
        $donnesFormulaire = $this->getDonneesFormulaireFactory()->get($id_d);
        $donnesFormulaire->setTabData([
            'acte_nature' => 3,
            'envoi_sae' => 'On',
        ]);

        $this->triggerActionOnDocument($id_d, 'send-archive');
        $this->triggerActionOnDocument($id_d, 'verif-sae');

        $this->assertLastDocumentAction('ar-recu-sae', $id_d);

        $donnesFormulaire = $this->getDonneesFormulaireFactory()->get($id_d);
        $this->assertMatchesRegularExpression('#ACK_\d*\.xml#', $donnesFormulaire->getFileName('ar_sae'));
        $this->assertEquals(
            'Ce transfert d\'archive a été envoyé à un connecteur bouchon SAE !',
            $donnesFormulaire->get('sae_ack_comment')
        );

        $this->triggerActionOnDocument($id_d, 'validation-sae');
        $this->assertLastDocumentAction($last_action_expected, $id_d);
        $donnesFormulaire = $this->getDonneesFormulaireFactory()->get($id_d);
        $this->assertMatchesRegularExpression('#ATR_\d*\.xml#', $donnesFormulaire->getFileName('reply_sae'));
        $this->assertEquals(
            $sae_atr_comment_expected,
            $donnesFormulaire->get('sae_atr_comment')
        );
    }
}
