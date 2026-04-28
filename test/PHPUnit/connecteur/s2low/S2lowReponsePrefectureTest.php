<?php

declare(strict_types=1);

class S2lowReponsePrefectureTest extends PastellTestCase
{
    use CurlUtilitiesTestTrait;

    /**
     * @throws DonneesFormulaireException
     * @throws Exception
     */
    private function getS2low(string $curlResponse): S2low
    {
        $this->mockCurl([
            '/admin/users/api-list-login.php' => true,
            '/modules/actes/actes_transac_reponse_create.php' => $curlResponse,
        ]);

        $info = $this->createConnector('s2low', 'S2LOW');

        $collectiviteProperties = $this->getDonneesFormulaireFactory()->getConnecteurEntiteFormulaire($info['id_ce']);
        $collectiviteProperties->addFileFromCopy(
            'classification_file',
            'classification.xml',
            __DIR__ . '/fixtures/classification-exemple.xml'
        );

        /** @var S2low */
        return $this->getConnecteurFactory()->getConnecteurById($info['id_ce']);
    }

    /**
     * @throws S2lowException
     * @throws UnrecoverableException
     * @throws Exception
     */
    public function testSendReponseType(): void
    {
        $s2low = $this->getS2low("OK\n52");

        $donneesFormulaire = $this->getDonneesFormulaireFactory()->getNonPersistingDonneesFormulaire();
        $donneesFormulaire->setTabData([
            'type_reponse' => TdtConnecteur::DEMANDE_PIECE_COMPLEMENTAIRE,
            'type_acte_demande_piece_complementaire' => '99_AI',
            'acte_nature' => 3,
            'type_pj_demande_piece_complementaire' => json_encode(['99_AU', 'AA_11'], JSON_THROW_ON_ERROR),
        ]);

        $donneesFormulaire->addFileFromData('reponse', 'foo.pdf', 'bar');
        $donneesFormulaire->addFileFromData('reponse_pj_demande_piece_complementaire', 'baz.pdf', 'buz', 0);
        $donneesFormulaire->addFileFromData('reponse_pj_demande_piece_complementaire', 'baz2.pdf', 'buz2', 1);

        $s2low->sendResponse($donneesFormulaire);

        static::assertSame('52', $donneesFormulaire->get('response_transaction_id'));
    }
}
