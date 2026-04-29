<?php

class SignatureEnvoieTest extends PastellTestCase
{
    use SoapUtilitiesTestTrait;

    /**
     * @throws NotFoundException
     * @throws Exception
     */
    public function testWhenHasDateLimite()
    {
        $this->mockSoapClient(function ($soapMethod, $arguments) {
            $this->assertSame("CreerDossier", $soapMethod);
            $arguments[0]['DossierID'] = "NOT TESTABLE";
            $this->assertEquals(
                [
                    0 =>
                         [
                            'TypeTechnique' => 'FOO',
                            'SousType' => 'BAR',
                            'DossierID' => 'NOT TESTABLE',
                            'DocumentPrincipal' =>
                                 [
                                    '_' => 'test',
                                    'contentType' => 'text/plain',
                                ],
                            'Visibilite' => 'SERVICE',
                            'NomDocPrincipal' => 'document.pdf',
                            'DossierTitre' => 'LIBELLE',
                            'DateLimite' => '2012-08-05',
                        ],
                 ],
                $arguments
            );
            return json_decode(
                '{"MessageRetour":{"codeRetour":"OK","message":"Dossier 201812101413 Achat_de_libriciels soumis dans le circuit","severite":"INFO"}}'
            );
        });

        $connecteur_info = $this->createConnector('iParapheur', "i-Parapheur");
        $connecteurDonneesFormulaire = $this->getDonneesFormulaireFactory()
            ->getConnecteurEntiteFormulaire($connecteur_info['id_ce']);
        $connecteurDonneesFormulaire->setTabData([
            'iparapheur_wsdl' => 'https://foo',
        ]);
        $this->associateFluxWithConnector($connecteur_info['id_ce'], "document-a-signer", "signature");

        $document_info = $this->createDocument('document-a-signer');

        $donneesFormulaire = $this->getDonneesFormulaireFactory()->get($document_info['id_d']);
        $donneesFormulaire->setTabData([
            'iparapheur_type' => 'FOO',
            'iparapheur_sous_type' => 'BAR',
            'libelle' => 'LIBELLE',
            'has_date_limite' => "On",
            'date_limite' => '2012-08-05',
        ]);
        $donneesFormulaire->addFileFromData('document', 'document.pdf', "test");

        $this->triggerActionOnDocument($document_info['id_d'], "send-iparapheur");

        $this->assertLastMessage('Le document a été envoyé au parapheur électronique');
    }
}
