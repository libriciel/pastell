<?php

class FastParapheurTest extends PastellTestCase
{
    use SoapUtilitiesTestTrait;
    use CurlUtilitiesTestTrait;

    /** @var FastParapheur */
    private $fastParapheur;

    /**
     * @return DonneesFormulaire
     */
    private function getDefaultConnectorConfig(): DonneesFormulaire
    {
        $connecteurConfig = $this->getDonneesFormulaireFactory()->getNonPersistingDonneesFormulaire();
        $connecteurConfig->setData('url', 'https://domain.tld');
        $connecteurConfig->setData('numero_abonnement', '1234');

        return $connecteurConfig;
    }

    private function getFastParapheur(DonneesFormulaire $connectorConfig = null): FastParapheur
    {
        $fastParapheur = new FastParapheur(
            $this->getObjectInstancier()->getInstance(SoapClientFactory::class),
            $this->getObjectInstancier()->getInstance(CurlWrapperFactory::class),
            $this->getObjectInstancier()->getInstance(TmpFolder::class),
            $this->getObjectInstancier()->getInstance(ZipArchive::class)
        );
        $fastParapheur->setConnecteurConfig($connectorConfig ?? $this->getDefaultConnectorConfig());
        return $fastParapheur;
    }

    private function getFileToSign(): FileToSign
    {
        $fileToSign = new FileToSign();
        $fileToSign->document = new Fichier();
        $fileToSign->document->filepath = __DIR__ . '/fixtures/empty.txt';
        $fileToSign->document->filename = 'empty.txt';
        $fileToSign->document->content = '';
        $fileToSign->circuit = 'circuit';

        return $fileToSign;
    }

    /**
     * When the connection is ok
     *
     * @test
     * @throws Exception
     */
    public function whenConnectionIsOk()
    {
        $this->mockSoapClient(
            function ($soapMethod, $arguments) {
                if ($soapMethod === 'listRemainingAcknowledgements') {
                    return json_decode('', false);
                }
                throw new UnrecoverableException("Unexpected call to SOAP method : $soapMethod");
            }
        );

        $this->fastParapheur = $this->getFastParapheur();
        $this->assertEquals(json_decode("", false), $this->fastParapheur->testConnection());
    }

    /**
     * When the connection is not ok
     *
     * @test
     * @throws Exception
     */
    public function whenConnectionIsNotOk()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage(
            "Erreur: l'abonne est inconnu ou l'utilisateur n'a pas les permissions pour y réaliser l'action demandée"
        );

        $this->mockSoapClient(
            function ($soapMethod, $arguments) {
                if ($soapMethod === 'listRemainingAcknowledgements') {
                    throw new Exception(
                        "Erreur: l'abonne est inconnu ou l'utilisateur n'a pas les permissions pour y réaliser l'action demandée"
                    );
                }
                throw new UnrecoverableException("Unexpected call to SOAP method : $soapMethod");
            }
        );
        $this->fastParapheur = $this->getFastParapheur();
        $this->fastParapheur->testConnection();
    }

    /**
     * When getting subtypes
     *
     * @test
     * @throws UnrecoverableException
     */
    public function whenGettingSubtypes(): void
    {
        $this->mockSoapClient(
            function (string $soapMethod) {
                if ($soapMethod === 'getCircuits') {
                    return json_decode(
                        json_encode([
                            'return' => [
                                '0' => ['circuitId' => 'CIRCUIT 1'],
                                '1' => ['circuitId' => 'CIRCUIT 2'],
                                '2' => ['circuitId' => 'PES'],
                                '3' => ['circuitId' => 'BUREAUTIQUE']
                            ]
                        ], JSON_THROW_ON_ERROR),
                        false,
                        512,
                        JSON_THROW_ON_ERROR
                    );
                }
                throw new UnrecoverableException("Unexpected call to SOAP method : $soapMethod");
            }
        );
        $connecteurConfig = $this->getDefaultConnectorConfig();
        $this->fastParapheur = $this->getFastParapheur($connecteurConfig);
        $expected = [0 => 'CIRCUIT 1', 1 => 'CIRCUIT 2', 2 => 'PES', 3 => 'BUREAUTIQUE'];
        static::assertSame($expected, $this->fastParapheur->getSousType());
    }

    /**
     * When sending a document
     *
     * @throws Exception
     */
    public function testWhenSendingADocument(): void
    {
        $this->mockSoapClient(
            function (string $soapMethod) {
                if ($soapMethod === 'upload') {
                    return json_decode(json_encode([
                        'return' => '1234-abcd'
                    ]));
                }
                throw new UnrecoverableException("Unexpected call to SOAP method : $soapMethod");
            }
        );
        $this->fastParapheur = $this->getFastParapheur();

        $this->assertSame('1234-abcd', $this->fastParapheur->sendDossier($this->getFileToSign()));
    }

    /**
     * When sending a document and the server returns an error
     *
     * @throws Exception
     */
    public function testWhenSendingADocumentWithAnUploadError(): void
    {
        $this->mockSoapClient(
            function (string $soapMethod) {
                if ($soapMethod === 'upload') {
                    throw new Exception("Fichier deja depose par un agent");
                }
                throw new UnrecoverableException("Unexpected call to SOAP method : $soapMethod");
            }
        );

        $this->fastParapheur = $this->getFastParapheur();
        $this->assertFalse($this->fastParapheur->sendDossier($this->getFileToSign()));

        $this->assertSame("Fichier deja depose par un agent", $this->fastParapheur->getLastError());
    }

    /**
     * When sending a document without error but without receiving its id on the parapheur
     *
     * @throws Exception
     */
    public function testWhenSendingADocumentWithoutReceivingItsId(): void
    {
        $this->mockSoapClient(
            function (string $soapMethod) {
                if ($soapMethod === 'upload') {
                    return json_decode(json_encode([
                        'return' => ''
                    ]));
                }
                throw new UnrecoverableException("Unexpected call to SOAP method : $soapMethod");
            }
        );

        $this->fastParapheur = $this->getFastParapheur();

        $this->assertFalse($this->fastParapheur->sendDossier($this->getFileToSign()));

        $this->assertSame(
            "Le parapheur n'a pas retourné d'identifiant de document : {\"return\":\"\"}",
            $this->fastParapheur->getLastError()
        );
    }

    /**
     * When sending a document with annexes
     *
     * @throws Exception
     */
    public function testWhenSendingADocumentWithAnnexes(): void
    {
        $this->mockSoapClient(
            function (string $soapMethod) {
                if ($soapMethod === 'upload') {
                    return json_decode(json_encode([
                        'return' => '1234-abcd'
                    ]));
                }
                throw new UnrecoverableException("Unexpected call to SOAP method : $soapMethod");
            }
        );

        $this->fastParapheur = $this->getFastParapheur();
        $this->fastParapheur->setTmpFolder($this->getObjectInstancier()->getInstance(TmpFolder::class));

        $fileToSign = $this->getFileToSign();
        $annex = new Fichier();
        $annex->filename = 'empty.txt';
        $annex->filepath = __DIR__ . '/fixtures/empty.txt';
        $annex->content = 'content';
        $fileToSign->annexes = [$annex];
        $this->assertSame(
            '1234-abcd',
            $this->fastParapheur->sendDossier($fileToSign)
        );
    }

    /**
     * When sending a document with annexes but the archive cannot be built
     *
     * @throws Exception
     */
    public function testWhenTheArchiveCannotBeBuilt(): void
    {
        $zipArchive = $this->createMock(ZipArchive::class);
        $zipArchive
            ->method('open')
            ->willReturn(false);
        $this->getObjectInstancier()->setInstance(ZipArchive::class, $zipArchive);

        $this->fastParapheur = $this->getFastParapheur();

        $fileToSign = $this->getFileToSign();
        $annex = new Fichier();
        $annex->filename = 'empty.txt';
        $annex->filepath = __DIR__ . '/fixtures/empty.txt';
        $annex->content = 'content';
        $fileToSign->annexes = [$annex];
        $this->assertFalse($this->fastParapheur->sendDossier($fileToSign));
        $this->assertStringContainsString(
            "Impossible de créer le fichier d'archive : ",
            $this->fastParapheur->getLastError()
        );
    }

    /**
     * When getting the history of a document
     *
     * @test
     */
    public function whenGettingTheHistoryOfADocument()
    {
        $this->mockSoapClient(
            function ($soapMethod, $arguments) {
                if ($soapMethod === 'history') {
                    return json_decode(json_encode([
                        'return' => [
                            [
                                'userFullName' => 'Agent',
                                'date' => '2019-04-03T14:46:49.274+01:00',
                                'stateName' => 'Préparé'
                            ]
                        ]
                    ]));
                }
                throw new UnrecoverableException("Unexpected call to SOAP method : $soapMethod");
            }
        );
        $this->fastParapheur = $this->getFastParapheur();

        $history = new stdClass();
        $history->userFullName = 'Agent';
        $history->date = '2019-04-03T14:46:49.274+01:00';
        $history->stateName = 'Préparé';
        $this->assertEquals([$history], $this->fastParapheur->getAllHistoriqueInfo('1234-abcd'));
    }

    /**
     * When we are fetching history of a document that doesn't exist
     *
     * @test
     */
    public function whenGettingHistoryOfADocumentThatDoesNotExist()
    {
        $this->mockSoapClient(
            function ($soapMethod, $arguments) {
                if ($soapMethod === 'history') {
                    throw new Exception("Le document n'existe pas");
                }
                throw new UnrecoverableException("Unexpected call to SOAP method : $soapMethod");
            }
        );
        $this->fastParapheur = $this->getFastParapheur();

        $this->assertFalse($this->fastParapheur->getAllHistoriqueInfo('1234-abcd'));
        $this->assertSame("Le document n'existe pas", $this->fastParapheur->getLastError());
    }

    /**
     * When we are not getting history for a document
     *
     * @test
     */
    public function whenNotGettingHistoryForADocument()
    {
        $this->mockSoapClient(
            function ($soapMethod, $arguments) {
                if ($soapMethod === 'history') {
                    return json_decode(json_encode([
                        'return' => ''
                    ]));
                }
                throw new UnrecoverableException("Unexpected call to SOAP method : $soapMethod");
            }
        );

        $this->fastParapheur = $this->getFastParapheur();

        $this->assertFalse($this->fastParapheur->getAllHistoriqueInfo('1234-abcd'));
        $this->assertSame("L'historique du document n'a pas été trouvé", $this->fastParapheur->getLastError());
    }

    /**
     * @throws JsonException
     */
    public function testLastHistorique(): void
    {
        $this->fastParapheur = $this->getFastParapheur();

        $history = json_decode(
            json_encode([
                [
                    'userFullName' => 'Agent',
                    'date' => '2019-04-03T14:46:49.274+01:00',
                    'stateName' => 'Préparé'
                ],
                [
                    'userFullName' => 'Agent',
                    'date' => '2019-04-03T15:35:03.449+01:00',
                    'stateName' => 'Classé'
                ]
            ], JSON_THROW_ON_ERROR),
            false,
            512,
            JSON_THROW_ON_ERROR
        );
        static::assertSame(
            '03/04/2019 16:35:03 : [Classé]',
            $this->fastParapheur->getLastHistorique($history)
        );
    }

    /**
     * @throws JsonException
     */
    public function testDateSignature(): void
    {
        $this->fastParapheur = $this->getFastParapheur();

        $history = json_decode(
            json_encode([
                [
                    'userFullName' => 'Agent',
                    'date' => '2019-04-03T14:46:49.274+01:00',
                    'stateName' => 'Préparé'
                ],
                [
                    'userFullName' => 'Agent',
                    'date' => '2019-05-03T14:46:49.274+01:00',
                    'stateName' => 'Signé'
                ],
                [
                    'userFullName' => 'Agent',
                    'date' => '2019-06-03T14:46:49.274+01:00',
                    'stateName' => 'Signé'
                ],
                [
                    'userFullName' => 'Agent',
                    'date' => '2019-07-03T15:35:03.449+01:00',
                    'stateName' => 'Classé'
                ]
            ], JSON_THROW_ON_ERROR),
            false,
            512,
            JSON_THROW_ON_ERROR
        );
        static::assertSame(
            '2019-06-03',
            $this->fastParapheur->getDateSignature($history)
        );
    }

    /**
     * When getting the signed document
     *
     * @test
     */
    public function whenGettingTheSignedDocument()
    {
        $this->mockSoapClient(
            function ($soapMethod, $arguments) {
                if ($soapMethod === 'download') {
                    return json_decode(json_encode([
                        'return' => [
                            'documentId' => '1234-abcd',
                            'content' => 'signed file content'
                        ]
                    ]));
                }
                throw new UnrecoverableException("Unexpected call to SOAP method : $soapMethod");
            }
        );

        $this->fastParapheur = $this->getFastParapheur();

        $this->assertEquals('signed file content', $this->fastParapheur->getSignature('1234-abcd'));
    }

    /**
     * When getting the signature of a document that doesn't exist
     *
     * @test
     */
    public function whenGettingSignatureOfADocumentThatDoesNotExist()
    {
        $this->mockSoapClient(
            function ($soapMethod, $arguments) {
                if ($soapMethod === 'download') {
                    throw new Exception("Le document n'existe pas");
                }
                throw new UnrecoverableException("Unexpected call to SOAP method : $soapMethod");
            }
        );

        $this->fastParapheur = $this->getFastParapheur();

        $this->assertFalse($this->fastParapheur->getSignature('1234-abcd'));
        $this->assertSame("Le document n'existe pas", $this->fastParapheur->getLastError());
    }

    /**
     * When the document can't be downloaded
     *
     * @test
     */
    public function whenTheDocumentCannotBeDownloaded()
    {
        $this->mockSoapClient(
            function ($soapMethod, $arguments) {
                if ($soapMethod === 'download') {
                    return json_decode(json_encode([
                        'return' => [
                            'documentId' => '1234-abcd',
                            'content' => ''
                        ]
                    ]));
                }
                throw new UnrecoverableException("Unexpected call to SOAP method : $soapMethod");
            }
        );

        $this->fastParapheur = $this->getFastParapheur();

        $this->assertFalse($this->fastParapheur->getSignature('1234-abcd'));
        $this->assertSame("Le document n'a pas pu être téléchargé", $this->fastParapheur->getLastError());
    }

    /**
     * @throws Exception
     */
    public function testGetBordereauFromSignatureFast(): void
    {
        $this->fastParapheur = $this->getFastParapheur();
        $this->mockSoapClient(
            function ($soapMethod, $arguments) {
                if ($soapMethod === 'getFdc') {
                    return json_decode(
                        json_encode([
                            'return' => [
                                'content' => 'fdc-content',
                            ]
                        ], JSON_THROW_ON_ERROR),
                        false,
                        512,
                        JSON_THROW_ON_ERROR
                    );
                }
                throw new UnrecoverableException("Unexpected call to SOAP method : $soapMethod");
            }
        );

        $this->fastParapheur = $this->getFastParapheur();

        static::assertEquals(
            'fdc-content',
            $this->fastParapheur->getBordereauFromSignature('signature', '1234-abcd')->content
        );
    }

    /**
     * @throws Exception
     */
    public function testGetBordereauFromSignatureFastDownloadFail(): void
    {
        $this->fastParapheur = $this->getFastParapheur();
        $this->mockSoapClient(
            function ($soapMethod, $arguments) {
                if ($soapMethod === 'getFdc') {
                    return json_decode(
                        json_encode([
                            'return' => [
                                'content' => '',
                            ]
                        ], JSON_THROW_ON_ERROR),
                        false,
                        512,
                        JSON_THROW_ON_ERROR
                    );
                }
                throw new UnrecoverableException("Unexpected call to SOAP method : $soapMethod");
            }
        );

        $this->fastParapheur = $this->getFastParapheur();
        static::assertNull($this->fastParapheur->getBordereauFromSignature('signature', '1234-abcd'));
        static::assertSame("Le fichier de circulation n'a pas pu être téléchargé", $this->fastParapheur->getLastError());
    }

    public function testMaxNumberDaysInParapheur()
    {
        $connecteurConfig = $this->getDefaultConnectorConfig();
        $connecteurConfig->setData('parapheur_nb_jour_max', 1234);

        $this->fastParapheur = $this->getFastParapheur($connecteurConfig);

        $this->assertSame(
            1234,
            $this->fastParapheur->getNbJourMaxInConnecteur()
        );
    }

    public function testDefaultMaxNumberDaysInParapheur()
    {
        $this->fastParapheur = $this->getFastParapheur();

        $this->assertSame(
            30,
            $this->fastParapheur->getNbJourMaxInConnecteur()
        );
    }

    /**
     * When sending an helios document and the server returns an error
     *
     * @throws Exception
     */
    public function testWhenSendingAnHeliosDocumentWithAnError(): void
    {
        $this->mockSoapClient(
            function (string $soapMethod) {
                if ($soapMethod === 'upload') {
                    throw new Exception("Fichier refusé : un fichier PES avec le même nomfic a deja ete envoye");
                }
                throw new UnrecoverableException("Unexpected call to SOAP method : $soapMethod");
            }
        );

        $this->fastParapheur = $this->getFastParapheur();

        $this->assertFalse($this->fastParapheur->sendDossier($this->getFileToSign()));

        $this->assertSame(
            "Doublon | Fichier refusé : un fichier PES avec le même nomfic a deja ete envoye",
            $this->fastParapheur->getLastError()
        );
    }

    /**
     * When sending an helios document without error but without receiving its id on the parapheur
     *
     * @throws Exception
     */
    public function testWhenSendingAnHeliosDocumentWithoutReceivingItsId(): void
    {
        $this->mockSoapClient(
            function (string $soapMethod) {
                if ($soapMethod === 'upload') {
                    return json_decode(json_encode([
                        'return' => ''
                    ]));
                }
                throw new UnrecoverableException("Unexpected call to SOAP method : $soapMethod");
            }
        );

        $this->fastParapheur = $this->getFastParapheur();

        $this->assertFalse($this->fastParapheur->sendDossier($this->getFileToSign()));

        $this->assertSame(
            "Le parapheur n'a pas retourné d'identifiant de document : {\"return\":\"\"}",
            $this->fastParapheur->getLastError()
        );
    }

    /**
     * @test
     */
    public function whenDeletingARejectedFile()
    {
        $this->mockSoapClient(
            function ($soapMethod, $arguments) {
                $this->assertSame('delete', $soapMethod);
                $this->assertSame(
                    [
                        [
                            'documentId' => '1234-abcd'
                        ]
                    ],
                    $arguments
                );
                return [];
            }
        );

        $this->fastParapheur = $this->getFastParapheur();
        $this->fastParapheur->setLogger($this->getLogger());

        $this->assertSame(
            [],
            $this->fastParapheur->effacerDossierRejete('1234-abcd')
        );
    }

    /**
     * @test
     */
    public function whenDeletingARejectedFileException()
    {
        $this->mockSoapClient(
            function ($soapMethod, $arguments) {
                if ($soapMethod === 'delete') {
                    throw new Exception('exception message');
                }
                throw new UnrecoverableException("Unexpected call to SOAP method : $soapMethod");
            }
        );

        $this->fastParapheur = $this->getFastParapheur();
        $this->fastParapheur->setLogger($this->getLogger());

        $this->assertFalse($this->fastParapheur->effacerDossierRejete(""));
        $this->assertSame(
            'exception message',
            $this->fastParapheur->getLastError()
        );
    }

    public function testNotDeletingARejectedFile(): void
    {
        $connecteurConfig = $this->getDefaultConnectorConfig();
        $connecteurConfig->setData('parapheur_do_not_delete_on_rejection', true);
        $this->fastParapheur = $this->getFastParapheur($connecteurConfig);
        $this->assertTrue($this->fastParapheur->effacerDossierRejete(""));
    }

    /**
     * @throws Exception
     */
    public function testSendDossierWithCircuitOnTheFly()
    {
        $this->mockSoapClient(
            function ($soapMethod, $arguments) {
                return true;
            }
        );
        $this->mockCurl([
            sprintf(FastParapheur::CIRCUIT_ON_THE_FLY_URI, '1234') => 123
        ]);
        $this->fastParapheur = $this->getFastParapheur();
        $file = new FileToSign();
        $file->document = new Fichier();
        $file->document->filepath = __DIR__ . '/fixtures/empty.txt';
        $file->circuit_configuration = new Fichier();
        $file->circuit_configuration->content =
            file_get_contents(__DIR__ . '/fixtures/ok_circuit.json');

        $this->assertSame(123, $this->fastParapheur->sendDossier($file));
    }

    /**
     * @throws Exception
     */
    public function testSendDossierWithCircuitOnTheFlyWithAnError()
    {
        $this->mockSoapClient(
            function ($soapMethod, $arguments) {
                return true;
            }
        );
        $this->mockCurl([
            sprintf(FastParapheur::CIRCUIT_ON_THE_FLY_URI, '1234') => json_encode([
                'generation' => 1575639678830,
                'developerMessage' => 'Invalid step type',
                'userFriendlyMessage' => "Le type d'étape est incorrect",
                'errorCode' => 118
            ])
        ]);
        $this->fastParapheur = $this->getFastParapheur();
        $file = new FileToSign();
        $file->document = new Fichier();
        $file->document->filepath = __DIR__ . '/fixtures/empty.txt';
        $file->circuit_configuration = new Fichier();
        $file->circuit_configuration->content =
            file_get_contents(__DIR__ . '/fixtures/ko_circuit_unknown_step.json');

        $this->expectException(SignatureException::class);
        $this->expectExceptionMessage("Erreur 118 : Le type d'étape est incorrect (Invalid step type)");
        $this->fastParapheur->sendDossier($file);
    }

    public function testSendDossierWithoutCircuit()
    {
        $this->fastParapheur = $this->getFastParapheur();
        $file = new FileToSign();
        $file->document = new Fichier();
        $file->document->filepath = __DIR__ . '/fixtures/empty.txt';
        $file->circuit_configuration = new Fichier();

        $this->expectException(SignatureException::class);
        $this->expectExceptionMessage(
            "Le formulaire est incomplet : le champ 'Circuit sur le parapheur'" .
            " ou le champ 'Configuration du circuit à la volée' est obligatoire"
        );
        $this->fastParapheur->sendDossier($file);
    }

    /**
     * @throws NotFoundException
     * @throws Exception
     */
    public function testGetRefusalMessage(): void
    {
        $this->mockSoapClient(
            function ($soapMethod, $arguments) {
                if ($soapMethod === 'upload') {
                    return json_decode(
                        json_encode([
                            'return' => '1234-abed'
                        ], JSON_THROW_ON_ERROR),
                        false,
                        512,
                        JSON_THROW_ON_ERROR
                    );
                }
                if ($soapMethod === 'getRefusalMessage') {
                    return json_decode(
                        json_encode([
                            'return' => 'test message de refus'
                        ], JSON_THROW_ON_ERROR),
                        false,
                        512,
                        JSON_THROW_ON_ERROR
                    );
                }
                if ($soapMethod === 'history') {
                    return json_decode(
                        json_encode([
                            'return' => [
                                [
                                    'userFullName' => 'Agent',
                                    'date' => '2019-04-03T14:46:49.274+01:00',
                                    'stateName' => 'Refusé'
                                ]
                            ]
                        ], JSON_THROW_ON_ERROR),
                        false,
                        512,
                        JSON_THROW_ON_ERROR
                    );
                }
                throw new UnrecoverableException("Unexpected call to SOAP method : $soapMethod");
            }
        );

        $id_ce = $this->createConnector('fast-parapheur', 'fast-parapheur')['id_ce'];
        $this->configureConnector($id_ce, [
            'wsdl' => 'https://foo',
        ]);

        $this->associateFluxWithConnector($id_ce, 'ls-document-pdf', 'signature');

        $id_d = $this->createDocument('ls-document-pdf')['id_d'];
        $donneesFormulaire = $this->getDonneesFormulaireFactory()->get($id_d);
        $donneesFormulaire->setTabData([
            'iparapheur_type' => 'FOO',
            'iparapheur_sous_type' => 'BAR',
            'libelle' => 'LIBELLE',
            'fast_parapheur_circuit' => 'circuit',
            'fast_parapheur_circuit_configuration' => 'config'
        ]);

        $this->triggerActionOnDocument($id_d, 'send-iparapheur');
        $this->assertLastMessage('Le document a été envoyé au parapheur électronique');

        $this->triggerActionOnDocument($id_d, 'verif-iparapheur');
        $this->assertLastMessage('03/04/2019 15:46:49 : [Refusé] test message de refus');
    }
}
