<?php

class IParapheurRejetTest extends PastellTestCase
{
    use SoapUtilitiesTestTrait;

    /**
     * @throws NotFoundException
     */
    public function testRejet()
    {
        $this->mockSoapClient(function ($soapMethod) {
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
                            'timestamp' => '2024-02-23T15:22:30.003Z',
                            'nom' => 'WS User',
                            'status' => 'NonLu',
                            'annotation' => 'Création de dossier',
                        ],
                        1 => [
                            'timestamp' => '2024-02-23T15:22:30.003Z',
                            'nom' => 'WS User',
                            'status' => 'NonLu',
                            'annotation' => 'Emission du dossier',
                        ],
                        2 => [
                            'timestamp' => '2024-02-23T15:22:30.003Z',
                            'nom' => 'WS User',
                            'status' => 'PretCachet',
                            'annotation' => 'Dossier déposé sur le bureau User pour cachet serveur.',
                        ],
                        3 => [
                            'timestamp' => '2024-02-23T15:22:30.150Z',
                            'nom' => 'Signe1 User',
                            'status' => 'RejetCachet',
                            'annotation' => 'test rejet cachet',
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
                    'NomDocPrincipal' => 'test éàê accent.pdf',
                    'DocumentsAnnexes' => [
                        'DocAnnexe' => [
                            [
                                'nom' => 'annexe origine.pdf',
                                'fichier' => [
                                    '_' => 'annexe origine content',
                                    'contentType' => 'application/pdf',
                                ],
                            ],
                            [
                                'nom' => 'annexe rajoutée dans i-parapheur.pdf',
                                'fichier' => [
                                    '_' => 'annexe rajoutée dans i-parapheur content',
                                    'contentType' => 'application/pdf',
                                ],
                            ],
                            [
                                'nom' => 'iParapheur_impression_dossier.pdf',
                                'fichier' => [
                                    '_' => 'Bordereau de signature content',
                                    'contentType' => 'application/pdf',
                                ],
                            ],
                        ],
                    ],
                    'MetaDonnees' => [
                        'MetaDonnee' => [
                            ['nom' => 'i_Parapheur_internal_creation_date', 'valeur' => '2024-10-30T16:43:36.464+0100'],
                            ['nom' => 'test_pastell_texte', 'valeur' => 'Doc KO'],
                            ['nom' => 'ph:dossierTitre', 'valeur' => 'LIBELLE'],
                        ],
                    ],
                    'MessageRetour' => [
                        'codeRetour' => 'OK'
                    ]
                ], JSON_THROW_ON_ERROR), false, 512, JSON_THROW_ON_ERROR);
            }
            if ($soapMethod === 'EffacerDossierRejete') {
                return json_decode(
                    '{"MessageRetour":{"codeRetour":"OK","message":"message.","severite":"INFO"}}',
                    false,
                    512,
                    JSON_THROW_ON_ERROR
                );
            }
            throw new UnrecoverableException("unknow $soapMethod call");
        });

        $id_ce = $this->createConnector('iParapheur', 'i-parapheur')['id_ce'];

        $this->configureConnector($id_ce, [
            'iparapheur_wsdl' => 'https://foo',
        ]);

        $this->associateFluxWithConnector($id_ce, 'ls-document-pdf', 'signature');

        $id_d = $this->createDocument('ls-document-pdf')['id_d'];
        $donneesFormulaire = $this->getDonneesFormulaireFactory()->get($id_d);
        $donneesFormulaire->setTabData([
            'iparapheur_type' => 'FOO',
            'iparapheur_sous_type' => 'BAR',
            'libelle' => 'LIBELLE',
        ]);
        $donneesFormulaire->addFileFromData('document', 'test éàê accent.pdf', 'test');
        $this->getDonneesFormulaireFactory()->get($id_d)->addFileFromData(
            'annexe',
            'annexe origine.pdf',
            'annexe origine content',
            0
        );

        $this->triggerActionOnDocument($id_d, 'send-iparapheur');
        $this->assertLastMessage('Le document a été envoyé au parapheur électronique');

        $this->triggerActionOnDocument($id_d, 'verif-iparapheur');

        $this->assertLastDocumentAction('rejet-iparapheur', $id_d);
        $this->assertLastMessage('23/02/2024 16:22:30 : [RejetCachet] test rejet cachet');

        $donnesFormulaire = $this->getDonneesFormulaireFactory()->get($id_d);
        static::assertSame(
            '{"i_Parapheur_internal_creation_date":"2024-10-30T16:43:36.464+0100","test_pastell_texte":"Doc KO","ph:dossierTitre":"LIBELLE"}',
            $donnesFormulaire->getFileContent('iparapheur_metadata_sortie')
        );

        $domDocument = new DOMDocument();
        $domDocument->preserveWhiteSpace = false;
        $domDocument->formatOutput = true;
        $domDocument->loadXML($donneesFormulaire->getFileContent('iparapheur_historique'));

        static::assertStringEqualsFile(
            __DIR__ . '/fixtures/iparapheur-historique-rejetCachet.xml',
            $domDocument->saveXML()
        );

        static::assertSame(
            'annexe rajoutée dans i-parapheur.pdf',
            $this->getDonneesFormulaireFactory()
                ->get($id_d)
                ->getFileName('iparapheur_annexe_sortie', 0)
        );
    }
}
