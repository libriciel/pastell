<?php

class FactureCPPIparapheurRecupTest extends ExtensionCppTestCase
{
    use SoapUtilitiesTestTrait;

    private function createAndAssociateIparapheurConnector(): void
    {
        $connector = $this->createConnector('iParapheur', 'parapheur');
        $this->configureConnector($connector['id_ce'], [
            'iparapheur_wsdl' => 'wsdl'
        ]);
        $this->associateFluxWithConnector($connector['id_ce'], 'facture-cpp', 'signature');
    }

    /**
     * @param DonneesFormulaire $donneesFormulaire
     * @return DonneesFormulaire
     */
    private function setDefaultDataToDocument(DonneesFormulaire $donneesFormulaire): DonneesFormulaire
    {
        $donneesFormulaire->setTabData([
            'id_facture_cpp' => "20191125160915_1449812468",
            'no_facture' => "FAC19-2512",
            'statut_cpp' => "MISE_A_DISPOSITION" ,
            'envoi_visa' => "On",
            'iparapheur_type' => "Facture CPP",
            'iparapheur_sous_type' => "Service Fait",
            'service_destinataire_code' => "",
            'facture_numero_engagement' => "",
            'facture_numero_marche' => "",
            'siret' => "00000000013456",
            'montant_ttc' => "20",
        ]);

        return $donneesFormulaire;
    }

    public function testRejetParapheur()
    {
        $this->createAndAssociateIparapheurConnector();
        $document = $this->createDocument('facture-cpp');
        $id_d = $document['id_d'];
        $donneesFormulaire = $this->getDonneesFormulaireFactory()->get($id_d);
        $this->setDefaultDataToDocument($donneesFormulaire);


        $this->mockSoapClient(
            function ($soapMethod, $arguments) use ($id_d) {
                if (in_array($soapMethod, ['GetHistoDossier', 'GetDossier'])) {
                    $this->assertSame(
                        $this->getDonneesFormulaireFactory()->get($id_d)->get('iparapheur_dossier_id'),
                        $arguments[0]
                    );
                }
                if ($soapMethod === 'CreerDossier') {
                    return json_decode(
                        '{"MessageRetour":{"codeRetour":"OK","message":"","severite":"INFO"}}',
                        false,
                        512,
                        JSON_THROW_ON_ERROR
                    );
                }
                if ($soapMethod === 'GetHistoDossier') {
                    return json_decode(json_encode([
                        'LogDossier' => [
                            0 => [
                                'timestamp' => 1,
                                'annotation' => 'annotation',
                                'status' => 'RejetVisa'
                            ],
                        ],
                        'MessageRetour' => [
                            'codeRetour' => 'OK',
                            'message' => '',
                            'severite' => 'INFO'
                        ]
                    ], JSON_THROW_ON_ERROR), false, 512, JSON_THROW_ON_ERROR);
                }
                if ($soapMethod === 'GetDossier') {
                    return json_decode(json_encode([
                        'DocPrincipal' => [
                            '_' => '%PDF1-4',
                            'contentType' => 'application/pdf'
                        ],
                        'NomDocPrincipal' => 'docPrincipal.pdf',
                        'MetaDonnees' => [
                            'MetaDonnee' => [
                                ['nom' => 'chorusproStatutRejet', 'valeur' => 'SUSPENDUE'],
                                ['nom' => 'ph:dossierTitre', 'valeur' => '20191125160915_1449812468'],
                            ],
                        ],
                        'MessageRetour' => [
                            'codeRetour' => 'OK'
                        ]
                    ], JSON_THROW_ON_ERROR), false, 512, JSON_THROW_ON_ERROR);
                }
            }
        );

        $this->triggerActionOnDocument($document['id_d'], 'send-iparapheur');
        $this->assertLastMessage('Le document a été envoyé au parapheur électronique');
        $this->triggerActionOnDocument($document['id_d'], 'verif-iparapheur');

        /**
         * If this assertion fails with "La connexion avec le iParapheur a échoué : Failed asserting that two strings are identical."
         * It probably means that the assertion in the returnCallback() of the mocked soapClient is broken and the exception
         * is caught by the connector.
         */
        $this->assertLastMessage('01/01/1970 01:00:00 : [RejetVisa] annotation');

        $donneesFormulaire = $this->getDonneesFormulaireFactory()->get($id_d);

        $this->assertEquals("SUSPENDUE", $donneesFormulaire->get('statut_cible_liste'));
        $this->assertLastDocumentAction("rejet-iparapheur", $id_d);
    }

    public function testNonLuParapheur(): void
    {
        $this->createAndAssociateIparapheurConnector();
        $document = $this->createDocument('facture-cpp');
        $id_d = $document['id_d'];
        $donneesFormulaire = $this->getDonneesFormulaireFactory()->get($id_d);
        $this->setDefaultDataToDocument($donneesFormulaire);


        $this->mockSoapClient(
            function ($soapMethod) {
                if ($soapMethod === 'CreerDossier') {
                    return json_decode(
                        '{"MessageRetour":{"codeRetour":"OK","message":"","severite":"INFO"}}',
                        false,
                        512,
                        JSON_THROW_ON_ERROR
                    );
                }
                if ($soapMethod === 'GetHistoDossier') {
                    return json_decode(json_encode([
                        'LogDossier' => [
                            0 => [
                                'timestamp' => 1,
                                'annotation' => 'Emission du dossier',
                                'status' => 'NonLu'
                            ],
                        ],
                        'MessageRetour' => [
                            'codeRetour' => 'OK',
                            'message' => '',
                            'severite' => 'INFO'
                        ]
                    ], JSON_THROW_ON_ERROR), false, 512, JSON_THROW_ON_ERROR);
                }
            }
        );

        $this->triggerActionOnDocument($document['id_d'], 'send-iparapheur');
        $this->assertLastMessage('Le document a été envoyé au parapheur électronique');
        $this->triggerActionOnDocument($document['id_d'], 'verif-iparapheur');
        $this->assertLastMessage('01/01/1970 01:00:00 : [NonLu] Emission du dossier');
    }
}
