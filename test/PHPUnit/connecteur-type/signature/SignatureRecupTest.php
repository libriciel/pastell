<?php

class SignatureRecupTest extends PastellTestCase
{
    use SoapUtilitiesTestTrait;

    /**
     * @throws NotFoundException
     * @throws Exception
     */
    public function testSignedDocumentKeepAccentuatedCharacters()
    {
        $this->mockSoapClient(
            function ($soapMethod, $arguments) {
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
                                'status' => 'Archive'
                            ],
                        ]
                    ], JSON_THROW_ON_ERROR), false, 512, JSON_THROW_ON_ERROR);
                }
                return json_decode(json_encode([
                    'DocPrincipal' => [
                        '_' => '%PDF1-4',
                        'contentType' => 'application/pdf'
                    ],
                    'NomDocPrincipal' => 'test éàê accent.pdf',
                    'MessageRetour' => [
                        'codeRetour' => 'OK'
                    ]
                ], JSON_THROW_ON_ERROR), false, 512, JSON_THROW_ON_ERROR);
            }
        );

        $connecteur_info = $this->createConnector('iParapheur', 'i-Parapheur');
        $connecteurDonneesFormulaire = $this->getDonneesFormulaireFactory()
            ->getConnecteurEntiteFormulaire($connecteur_info['id_ce']);
        $connecteurDonneesFormulaire->setTabData([
            'iparapheur_wsdl' => 'https://foo',
        ]);
        $this->associateFluxWithConnector($connecteur_info['id_ce'], 'document-a-signer', 'signature');

        $document_info = $this->createDocument('document-a-signer');

        $donneesFormulaire = $this->getDonneesFormulaireFactory()->get($document_info['id_d']);
        $donneesFormulaire->setTabData([
            'iparapheur_type' => 'FOO',
            'iparapheur_sous_type' => 'BAR',
            'libelle' => 'LIBELLE',
        ]);
        $donneesFormulaire->addFileFromData('document', 'test éàê accent.pdf', 'test');

        $this->triggerActionOnDocument($document_info['id_d'], 'send-iparapheur');
        $this->assertLastMessage('Le document a été envoyé au parapheur électronique');

        $this->triggerActionOnDocument($document_info['id_d'], 'verif-iparapheur');
        $this->assertLastMessage('La signature a été récupérée');

        $donneesFormulaire = $this->getDonneesFormulaireFactory()->get($document_info['id_d']);
        $this->assertSame(
            'test éàê accent.pdf',
            $donneesFormulaire->getFileName('document')
        );

        $this->assertSame(
            'test éàê accent_orig.pdf',
            $donneesFormulaire->getFileName('document_orignal')
        );
    }

    /**
     * @throws NotFoundException
     * @throws Exception
     */
    public function testSignedThreeMultiDocument()
    {
        $this->mockSoapClient(
            function ($soapMethod, $arguments) {
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
                                'status' => 'Archive'
                            ],
                        ]
                    ], JSON_THROW_ON_ERROR), false, 512, JSON_THROW_ON_ERROR);
                }
                return json_decode(json_encode([
                    'DocPrincipal' => [
                        '_' => '%PDF1-4',
                        'contentType' => 'application/pdf'
                    ],
                    'NomDocPrincipal' => 'doc principal.pdf',
                    'DocumentsSupplementaires' => [
                        'DocAnnexe' => [
                            [
                                'nom' => 'annexe 1.pdf',
                                'fichier' => [
                                    '_' => 'annexe 1 content',
                                    'contentType' => 'application/pdf',
                                ],
                            ],
                            [
                                'nom' => 'annexe 2.pdf',
                                'fichier' => [
                                    '_' => 'annexe 2 content',
                                    'contentType' => 'application/pdf',
                                ],
                            ],
                        ],
                    ],
                    'DocumentsAnnexes' => [
                        'DocAnnexe' => [
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
                    'MessageRetour' => [
                        'codeRetour' => 'OK'
                    ]
                ], JSON_THROW_ON_ERROR), false, 512, JSON_THROW_ON_ERROR);
            }
        );

        $connecteur_info = $this->createConnector('iParapheur', 'i-Parapheur');
        $connecteurDonneesFormulaire = $this->getDonneesFormulaireFactory()
            ->getConnecteurEntiteFormulaire($connecteur_info['id_ce']);
        $connecteurDonneesFormulaire->setTabData([
            'iparapheur_wsdl' => 'https://foo',
            'iparapheur_multi_doc' => true
        ]);
        $this->associateFluxWithConnector($connecteur_info['id_ce'], 'document-a-signer', 'signature');

        $document_info = $this->createDocument('document-a-signer');

        $donneesFormulaire = $this->getDonneesFormulaireFactory()->get($document_info['id_d']);
        $donneesFormulaire->setTabData([
            'iparapheur_type' => 'FOO',
            'iparapheur_sous_type' => 'BAR',
            'libelle' => 'LIBELLE',
        ]);
        $donneesFormulaire->addFileFromData('document', 'doc principal.pdf', 'test');
        $donneesFormulaire->addFileFromData('autre_document_attache', 'annexe 1.pdf', 'annexe 1 content', 0);
        $donneesFormulaire->addFileFromData('autre_document_attache', 'annexe 2.pdf', 'annexe 2 content', 1);

        $this->triggerActionOnDocument($document_info['id_d'], 'send-iparapheur');
        $this->assertLastMessage('Le document a été envoyé au parapheur électronique');

        $this->triggerActionOnDocument($document_info['id_d'], 'verif-iparapheur');
        $this->assertLastMessage('La signature a été récupérée');

        $donneesFormulaire = $this->getDonneesFormulaireFactory()->get($document_info['id_d']);
        $this->assertSame(
            'doc principal.pdf',
            $donneesFormulaire->getFileName('document')
        );
        $this->assertSame(
            'doc principal_orig.pdf',
            $donneesFormulaire->getFileName('document_orignal')
        );

        $this->assertSame(
            'annexe rajoutée dans i-parapheur.pdf',
            $donneesFormulaire->getFileName('iparapheur_annexe_sortie', 0)
        );

        $annexe_name = [];
        foreach ($donneesFormulaire->get('autre_document_attache') as $num => $fileName) {
            $annexe_name[] = $fileName;
        }
        $this->assertContains('annexe 1.pdf', $annexe_name);
        $this->assertContains('annexe 2.pdf', $annexe_name);

        $multi_document_original_name = [];
        foreach ($donneesFormulaire->get('multi_document_original') as $num => $fileName) {
            $multi_document_original_name[] = $fileName;
        }
        $this->assertContains('annexe 1_orig.pdf', $multi_document_original_name);
        $this->assertContains('annexe 2_orig.pdf', $multi_document_original_name);
    }


    /**
     * @throws NotFoundException
     * @throws Exception
     */
    public function testPadesSignatureWithOdtFilesConvertsToMultiDocumentPdf()
    {
        $this->mockSoapClient(
            function ($soapMethod, $arguments) {
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
                            [
                                'timestamp' => 1,
                                'annotation' => 'annotation',
                                'status' => 'Archive'
                            ]
                        ]
                    ], JSON_THROW_ON_ERROR), false, 512, JSON_THROW_ON_ERROR);
                }
                return json_decode(json_encode([
                    'DocPrincipal' => [
                        '_' => 'pdf signed content',
                        'contentType' => 'application/pdf'
                    ],
                    'NomDocPrincipal' => 'document.pdf',
                    'DocumentsSupplementaires' => [
                        'DocAnnexe' => [
                            [
                                'nom' => 'annexe-1.pdf',
                                'fichier' => [
                                    '_' => 'annexe 1 pdf content',
                                    'contentType' => 'application/pdf',
                                ],
                            ],
                            [
                                'nom' => 'annexe-2.pdf',
                                'fichier' => [
                                    '_' => 'annexe 2 pdf content',
                                    'contentType' => 'application/pdf',
                                ],
                            ],
                            [
                                'nom' => 'annexe-3.pdf',
                                'fichier' => [
                                    '_' => 'annexe 3 pdf content',
                                    'contentType' => 'application/pdf',
                                ],
                            ],
                        ],
                    ],
                    'MessageRetour' => [
                        'codeRetour' => 'OK'
                    ]
                ], JSON_THROW_ON_ERROR), false, 512, JSON_THROW_ON_ERROR);
            }
        );

        $connecteur_info = $this->createConnector('iParapheur', 'i-Parapheur');
        $connecteurDonneesFormulaire = $this->getDonneesFormulaireFactory()
            ->getConnecteurEntiteFormulaire($connecteur_info['id_ce']);
        $connecteurDonneesFormulaire->setTabData([
            'iparapheur_wsdl' => 'https://foo',
            'iparapheur_multi_doc' => true
        ]);
        $this->associateFluxWithConnector($connecteur_info['id_ce'], 'document-a-signer', 'signature');

        $document_info = $this->createDocument('document-a-signer');

        $donneesFormulaire = $this->getDonneesFormulaireFactory()->get($document_info['id_d']);
        $donneesFormulaire->setTabData([
            'iparapheur_type' => 'FOO',
            'iparapheur_sous_type' => 'BAR',
            'libelle' => 'LIBELLE',
        ]);
        $donneesFormulaire->addFileFromData('document', 'document.odt', 'odt document content');
        $donneesFormulaire->addFileFromData('autre_document_attache', 'annexe-1.odt', 'annexe 1 odt content', 0);
        $donneesFormulaire->addFileFromData('autre_document_attache', 'annexe-2.odt', 'annexe 2 odt content', 1);
        $donneesFormulaire->addFileFromData('autre_document_attache', 'annexe-3.odt', 'annexe 3 odt content', 2);

        $this->triggerActionOnDocument($document_info['id_d'], 'send-iparapheur');
        $this->assertLastMessage('Le document a été envoyé au parapheur électronique');

        $this->triggerActionOnDocument($document_info['id_d'], 'verif-iparapheur');
        $this->assertLastMessage('La signature a été récupérée');

        $donneesFormulaire = $this->getDonneesFormulaireFactory()->get($document_info['id_d']);

        $this->assertSame(
            'document.pdf',
            $donneesFormulaire->getFileName('document')
        );
        $this->assertSame(
            'document_orig.odt',
            $donneesFormulaire->getFileName('document_orignal')
        );

        $annexe_names = [];
        foreach ($donneesFormulaire->get('autre_document_attache') as $num => $fileName) {
            $annexe_names[] = $fileName;
        }
        $this->assertContains('annexe-1.pdf', $annexe_names);
        $this->assertContains('annexe-2.pdf', $annexe_names);
        $this->assertContains('annexe-3.pdf', $annexe_names);

        $multi_document_original_names = [];
        foreach ($donneesFormulaire->get('multi_document_original') as $num => $fileName) {
            $multi_document_original_names[] = $fileName;
        }
        $this->assertContains('annexe-1_orig.odt', $multi_document_original_names);
        $this->assertContains('annexe-2_orig.odt', $multi_document_original_names);
        $this->assertContains('annexe-3_orig.odt', $multi_document_original_names);
    }

    /**
     * @throws NotFoundException
     * @throws Exception
     */
    public function testSignedTwoMultiDocument()
    {
        $this->mockSoapClient(
            function ($soapMethod, $arguments) {
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
                                'status' => 'Archive'
                            ],
                        ]
                    ], JSON_THROW_ON_ERROR), false, 512, JSON_THROW_ON_ERROR);
                }
                return json_decode(json_encode([
                    'DocPrincipal' => [
                        '_' => '%PDF1-4',
                        'contentType' => 'application/pdf'
                    ],
                    'NomDocPrincipal' => 'doc principal.pdf',
                    'DocumentsSupplementaires' => [
                        'DocAnnexe' => [
                            'nom' => 'annexe 1.pdf',
                            'fichier' => [
                                '_' => 'annexe 1 content',
                                'contentType' => 'application/pdf',
                            ],
                        ],
                    ],
                    'DocumentsAnnexes' => [
                        'DocAnnexe' => [
                            'nom' => 'iParapheur_impression_dossier.pdf',
                            'fichier' => [
                                '_' => 'Bordereau de signature content',
                                'contentType' => 'application/pdf',
                            ],
                        ],
                    ],
                    'MessageRetour' => [
                        'codeRetour' => 'OK'
                    ]
                ], JSON_THROW_ON_ERROR), false, 512, JSON_THROW_ON_ERROR);
            }
        );

        $connecteur_info = $this->createConnector('iParapheur', 'i-Parapheur');
        $connecteurDonneesFormulaire = $this->getDonneesFormulaireFactory()
            ->getConnecteurEntiteFormulaire($connecteur_info['id_ce']);
        $connecteurDonneesFormulaire->setTabData([
            'iparapheur_wsdl' => 'https://foo',
            'iparapheur_multi_doc' => true
        ]);
        $this->associateFluxWithConnector($connecteur_info['id_ce'], 'document-a-signer', 'signature');

        $document_info = $this->createDocument('document-a-signer');

        $donneesFormulaire = $this->getDonneesFormulaireFactory()->get($document_info['id_d']);
        $donneesFormulaire->setTabData([
            'iparapheur_type' => 'FOO',
            'iparapheur_sous_type' => 'BAR',
            'libelle' => 'LIBELLE',
        ]);
        $donneesFormulaire->addFileFromData('document', 'doc principal.pdf', 'test');
        $donneesFormulaire->addFileFromData('autre_document_attache', 'annexe 1.pdf', 'annexe 1 content', 0);

        $this->triggerActionOnDocument($document_info['id_d'], 'send-iparapheur');
        $this->assertLastMessage('Le document a été envoyé au parapheur électronique');

        $this->triggerActionOnDocument($document_info['id_d'], 'verif-iparapheur');
        $this->assertLastMessage('La signature a été récupérée');

        $donneesFormulaire = $this->getDonneesFormulaireFactory()->get($document_info['id_d']);
        $this->assertSame(
            'doc principal.pdf',
            $donneesFormulaire->getFileName('document')
        );
        $this->assertSame(
            'doc principal_orig.pdf',
            $donneesFormulaire->getFileName('document_orignal')
        );

        $annexe_name = [];
        foreach ($donneesFormulaire->get('autre_document_attache') as $num => $fileName) {
            $annexe_name[] = $fileName;
        }
        $this->assertContains('annexe 1.pdf', $annexe_name);

        $multi_document_original_name = [];
        foreach ($donneesFormulaire->get('multi_document_original') as $num => $fileName) {
            $multi_document_original_name[] = $fileName;
        }
        $this->assertContains('annexe 1_orig.pdf', $multi_document_original_name);
    }
}
