<?php

use Pastell\File\Chunk\ChunkRequest;
use Pastell\Service\Utilisateur\UserCreationService;

class DocumentAPIControllerTest extends PastellTestCase
{
    use SoapUtilitiesTestTrait;

    private function createTestDocument()
    {
        $info = $this->createDocument('test');
        return $info['id_d'];
    }

    public function testList(): void
    {
        $id_d = $this->createTestDocument();
        $list = $this->getInternalAPI()->get('entite/1/document');
        static::assertSame(
            [
                'id_d' => $id_d,
                'id_e' => '1',
                'role' => 'editeur',
                'last_action' => 'creation',
                'last_action_date' => $list[0]['last_action_date'],
                'last_type' => 'test',
                'type' => 'test',
                'titre' => '',
                'creation' =>  $list[0]['creation'],
                'modification' =>  $list[0]['modification'],
                'denomination' => 'Bourg-en-Bresse',
                'siren' => '000000000',
                'date_inscription' => '0000-00-00 00:00:00',
                'entite_mere' => '0',
                'centre_de_gestion' => '0',
                'is_active' => true,
                'entite_base' => 'Bourg-en-Bresse',
                'entite' => [],
                'last_action_display' => 'creation',
            ],
            $list[0]
        );
    }

    public function testDetail()
    {
        $id_d = $this->createTestDocument();
        $info = $this->getInternalAPI()->get("entite/1/document/$id_d");
        $this->assertEquals('test', $info['info']['type']);
    }

    public function testDetailAll()
    {
        $id_d_1 = $this->createTestDocument();
        $id_d_2 = $this->createTestDocument();
        $list = $this->getInternalAPI()->get("entite/1/document/?id_d[]=$id_d_1&id_d[]=$id_d_2");
        $this->assertEquals($id_d_1, $list[$id_d_1]['info']['id_d']);
        $this->assertEquals($id_d_2, $list[$id_d_2]['info']['id_d']);
    }

    public function testDetailAllFail()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Le paramètre id_d[] ne semble pas valide");

        $this->getInternalAPI()->get("entite/1/document/?id_d=42");
    }

    public function testRecherche()
    {
        $id_d = $this->createTestDocument();
        $list = $this->getInternalAPI()->get("entite/1/document?date_in_fr=true");
        $this->assertEquals($id_d, $list[0]['id_d']);
    }

    public function testRechercheNoIdEntite()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("id_e est obligatoire");

        $this->getInternalAPI()->get("entite/0/document");
    }

    public function testRechercheIndexedField()
    {
        $id_d = $this->createTestDocument();
        $this->getInternalAPI()->patch("entite/1/document/$id_d", ['test1' => 'toto']);
        $list = $this->getInternalAPI()->get("entite/1/document?test1=toto");
        $this->assertEquals($id_d, $list[0]['id_d']);
    }

    public function testRechercheIndexedDateField()
    {
        $id_d = $this->createTestDocument();
        $this->getInternalAPI()->patch("entite/1/document/$id_d", ['date_indexed' => '2001-09-11']);
        $list = $this->getInternalAPI()->get("entite/1/document?type=test&date_in_fr=true&date_indexed=2001-09-11");
        $this->assertEquals($id_d, $list[0]['id_d']);
    }

    public function testRechercheNotEtatTransitField(): void
    {
        $id_d1 = $this->createTestDocument();
        $this->triggerActionOnDocument($id_d1, 'no-way');
        $id_d2 = $this->createTestDocument();
        $this->triggerActionOnDocument($id_d2, 'no-way');
        $list = $this->getInternalAPI()->get('entite/1/document?type=test&notEtatTransit=editable');

        self::assertCount(2, $list);

        $this->triggerActionOnDocument($id_d1, 'editable');
        $list = $this->getInternalAPI()->get('entite/1/document?type=test&notEtatTransit=editable');
        self::assertCount(1, $list);
    }

    public function testExternalData()
    {
        $id_d = $this->createTestDocument();
        $list = $this->getInternalAPI()->get("entite/1/document/$id_d/externalData/test_external_data");
        $this->assertEquals("Spock", $list[4]);
    }

    public function testExternalDataFaild()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Type test42 introuvable");

        $id_d = $this->createTestDocument();
        $this->getInternalAPI()->get("entite/1/document/$id_d/externalData/test42");
    }

    public function testPatchExternalData()
    {
        $id_d = $this->createTestDocument();
        $info = $this->getInternalAPI()->patch(
            "entite/1/document/$id_d/externalData/test_external_data",
            ['choix' => 'foo']
        );
        $this->assertEquals('foo', $info['data']['test_external_data']);
    }

    public function testPatchExternalDataFailed()
    {
        $id_d = $this->createTestDocument();
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Type test_external_data_not_existing introuvable");
        $this->getInternalAPI()->patch(
            "entite/1/document/$id_d/externalData/test_external_data_not_existing",
            ['choix' => 'foo']
        );
    }

    public function testEditAction()
    {
        $id_d = $this->createTestDocument();
        $info = $this->getInternalAPI()->patch("entite/1/document/$id_d", ['test1' => 'toto']);
        $this->assertEquals("toto", $info['content']['data']['test1']);
    }

    private function sendFile($id_d, $fileNumber = 0)
    {
        $info = $this->getInternalAPI()->post(
            "entite/1/document/$id_d/file/fichier/$fileNumber",
            [
                'file_name' => 'toto.txt',
                'file_content' => 'xxxx'
            ]
        );
        return $info;
    }

    public function testSendFile()
    {
        $id_d = $this->createTestDocument();
        $info = $this->sendFile($id_d);
        $this->assertEquals("toto.txt", $info['content']['data']['fichier'][0]);
    }

    public function testReceiveFile()
    {
        $id_d = $this->createTestDocument();
        $this->sendFile($id_d);
        $info = $this->getInternalAPI()->get("entite/1/document/$id_d/file/fichier?receive=true");
        $this->assertEquals("xxxx", $info['file_content']);
    }

    public function testAction()
    {
        $id_d = $this->createTestDocument();
        $info = $this->getInternalAPI()->post("entite/1/document/$id_d/action/ok");
        $this->assertEquals("OK !", $info['message']);
    }

    public function testActionNotPossible()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("L'action « not-possible »  n'est pas permise : role_id_e n'est pas vérifiée");

        $id_d = $this->createTestDocument();
        $this->getInternalAPI()->post("entite/1/document/$id_d/action/not-possible");
    }

    public function testActionFailed()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Raté !");

        $id_d = $this->createTestDocument();
        $this->getInternalAPI()->post("entite/1/document/$id_d/action/fail");
    }

    public function testEditOnChange()
    {
        $id_d = $this->createTestDocument();
        $info = $this->getInternalAPI()->patch("entite/1/document/$id_d", ['test_on_change' => 'foo']);
        $this->assertEquals("foo", $info['content']['data']['test2']);
    }

    public function testEditCantModify()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("L'action « modification »  n'est pas permise");

        $id_d = $this->createTestDocument();
        $this->getInternalAPI()->post("entite/1/document/$id_d/action/no-way");
        $this->getInternalAPI()->patch("entite/1/document/$id_d", ['test2' => 'ok']);
    }

    public function testRecuperationFichier()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Exit called with code 0");

        $id_d = $this->createTestDocument();
        $this->sendFile($id_d);
        $this->expectOutputRegex("#xxxx#");
        $this->getInternalAPI()->get("entite/1/document/$id_d/file/fichier");
    }

    public function testRecuperationFichierFailed()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Ce fichier n'existe pas");

        $id_d = $this->createTestDocument();
        $this->getInternalAPI()->get("entite/1/document/$id_d/file/fichier");
    }

    public function testLengthOfDocumentObject()
    {
        $info = $this->createDocument('actes-generique');
        $id_d = $info['id_d'];
        $info = $this->configureDocument($id_d, [
            'acte_nature' => '4',
            'numero_de_lacte' => 'D443_2017A',
            'date_de_lacte' => '2018-12-10',
            'objet' => 'Ceci est un message qui fait 498 caractères.' .
                'Ceci est un message qui fait 498 caractères.' .
                'Ceci est un message qui fait 498 caractères.' .
                'Ceci est un message qui fait 498 caractères.' .
                'Ceci est un message qui fait 498 caractères.' .
                'Ceci est un message qui fait 498 caractères.' .
                'Ceci est un message qui fait 498 caractères.' .
                'Ceci est un message qui fait 498 caractères.' .
                'Ceci est un message qui fait 498 caractères.' .
                "Ceci est un message qui fait 498 caractères mais avec &quot; il en fait 503 lorsqu'il est encodé",
        ]);
        $this->assertEquals("Le formulaire est incomplet : le champ «Acte» est obligatoire.", $info['message']);
    }

    public function testCount(): void
    {
        $this->createDocument('actes-generique');
        $info = $this->getInternalAPI()->get('document/count', ['id_e' => 1,'type' => 'actes-generique']);
        static::assertSame(
            [
                1 => [
                    'flux' => [
                        'actes-generique' => [
                            'creation' => 1,
                        ],
                    ],
                    'info' => [
                        'id_e' => '1',
                        'type' => 'collectivite',
                        'denomination' => 'Bourg-en-Bresse',
                        'siren' => '000000000',
                        'date_inscription' => '0000-00-00 00:00:00',
                        'entite_mere' => '0',
                        'centre_de_gestion' => '0',
                        'is_active' => true,
                    ],
                ],
            ],
            $info
        );
    }

    /**
     * @throws Exception
     */
    public function testDeleteFile()
    {
        $id_d = $this->createDocument('actes-generique')['id_d'];
        $donneesFormulaire = $this->getDonneesFormulaireFactory()->get($id_d);
        $donneesFormulaire->addFileFromData('arrete', 'arrete.txt', 'test');

        $info = $this->getInternalAPI()->get("entite/1/document/$id_d");
        $this->assertEquals('arrete.txt', $info['data']['arrete'][0]);
        $this->getInternalAPI()->delete("/entite/1/document/$id_d/file/arrete/0");
        $info = $this->getInternalAPI()->get("entite/1/document/$id_d");
        $this->assertTrue(empty($info['data']['arrete']));

        $journal = $this->getObjectInstancier()->getInstance(Journal::class);
        $this->assertEquals(
            "Modification du document",
            $journal->getAll(false, false, false, false, 0, 100)[0]['message']
        );
    }


    public function testUploadFileWithoutActionPossible()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("L'action « modification »  n'est pas permise");
        $id_d = $this->createTestDocument();

        $this->sendFile($id_d);
        $this->sendFile($id_d, 1);

        $this->getInternalAPI()->post("entite/1/document/$id_d/action/no-way");
        $this->sendFile($id_d, 2);
    }
/* Ce test ne passe pas car il manque une exeption à postFile
    public function testUploadFileWithoutFieldBeingEditable()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Le champ « fichier »  n'est pas modifiable");
        $id_d = $this->createTestDocument();

        $this->sendFile($id_d);
        $this->sendFile($id_d, 1);

        $this->getInternalAPI()->post("entite/1/document/$id_d/action/editable");
        $this->sendFile($id_d, 2);
    }
*/

    public function testDocumentShouldNotBeVisibleFromAnotherEntite()
    {
        $id_d = $this->createTestDocument();

        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage("Le document $id_d n'appartient pas à l'entité 2");

        $this->getInternalAPI()->get("entite/2/document/$id_d");
    }

    /**
     * @throws UnrecoverableException
     * @throws ConflictException
     */
    public function testPatchExternalDataWithoutEditPermission(): void
    {
        $this->expectException(ForbiddenException::class);
        $this->expectExceptionMessage('Acces interdit id_e=1, droit=test:edition,id_u=3');
        $roleSql = $this->getObjectInstancier()->getInstance(RoleSQL::class);
        $roleSql->edit('readonly', 'readonly');
        $roleSql->addDroit('readonly', 'entite:lecture');
        $roleSql->addDroit('readonly', 'test:lecture');
        $userId = $this->getObjectInstancier()->getInstance(UserCreationService::class)
            ->create(
                'readonly',
                'readonly@example.org',
                'readonly',
                'readonly'
            );
        $this->getObjectInstancier()->getInstance(RoleUtilisateur::class)->addRole($userId, 'readonly', self::ID_E_COL);

        $id_d = $this->createTestDocument();

        $this->getInternalAPIAsUser($userId)->patch(
            "entite/1/document/$id_d/externalData/test_external_data",
            ['choix' => 'foo']
        );
    }

    public function testMailSecBidir()
    {
        $id_d = $this->createDocument('mailsec-bidir')['id_d'];
        $this->assertTrue(true);

        $documentEmail = $this->getObjectInstancier()->getInstance(DocumentEmail::class);
        $key = $documentEmail->add($id_d, "foo@bar.com", "to");
        $id_de = $documentEmail->getInfoFromKey($key)['id_de'];
        $id_d_reponse = $this->createTestDocument();
        $documentEmailResponse = $this->getObjectInstancier()->getInstance(DocumentEmailReponseSQL::class);
        $documentEmailResponse->addDocumentReponseId($id_de, $id_d_reponse);
        $documentEmailResponse->validateReponse($id_de);


        $info = $this->getInternalAPI()->get("/entite/1/document/$id_d");

        $info['info']['id_d'] = 'NOT TESTABLE';
        $info['info']['creation'] = 'NOT TESTABLE';
        $info['info']['modification'] = 'NOT TESTABLE';
        $info['last_action']['date'] = 'NOT TESTABLE';

        $info['email_info'][0]['id_d'] = "NOT TESTABLE";
        $info['email_info'][0]['date_envoie'] = "NOT TESTABLE";
        $info['email_reponse'][1]['date_reponse'] = "NOT TESTABLE";

        $this->assertEquals(
            [
                'info' =>
                     [
                        'id_d' => 'NOT TESTABLE',
                        'type' => 'mailsec-bidir',
                        'titre' => '',
                        'creation' => 'NOT TESTABLE',
                        'modification' => 'NOT TESTABLE',
                    ],
                'data' =>
                     [],
                'email_info' =>
                     [
                        0 =>
                             [
                                'id_de' => '1',
                                'id_d' => 'NOT TESTABLE',
                                'email' => 'foo@bar.com',
                                'lu' => '0',
                                'date_envoie' => 'NOT TESTABLE',
                                'date_lecture' => '1970-01-01 00:00:00',
                                'type_destinataire' => 'to',
                                'date_renvoi' => '0000-00-00 00:00:00',
                                'nb_renvoi' => '0',
                                'reponse' => '',
                                'has_error' => '0',
                                'last_error' => '',
                                'non_recu' => '0',
                            ],
                    ],
                'email_reponse' =>
                     [
                        1 =>
                             [
                                'id_de' => '1',
                                'id_d_reponse' => $id_d_reponse,
                                'is_lu' => '0',
                                'titre' => '',
                                'date_reponse' => 'NOT TESTABLE',
                                'has_date_reponse' => '1',
                            ],
                    ],
                'action_possible' =>
                     [
                        0 => 'modification',
                        1 => 'supression',
                    ],
                'action-possible' =>
                     [
                        0 => 'modification',
                        1 => 'supression',
                    ],
                'last_action' =>
                     [
                        'action' => 'creation',
                        'message' => 'Création du document',
                        'date' => 'NOT TESTABLE',
                    ],
             ],
            $info
        );
    }

    public function testDeleteDocument(): void
    {
        $documentId = $this->createTestDocument();
        $this->getInternalAPI()->delete("/entite/1/document/$documentId");
        $this->expectOutputRegex('/HTTP\/1.1 204 No Content/');
    }

    public function testCreateDocumentOnDeactivatedEntity(): void
    {
        $this->getInternalAPI()->post('/entite/1/deactivate');
        $this->expectException(ForbiddenException::class);
        $this->expectExceptionMessage("L'entité 1 est désactivée");
        $this->createTestDocument();
    }

    private function configureTestPatchExternalDataIParapheurSousType(
        string $id_connecteur,
        string $key_sous_type
    ): string {

        $connector = $this->createConnector($id_connecteur, $id_connecteur);
        $id_co = $connector['id_ce'];
        $this->configureConnector($id_co, [$key_sous_type => 'TEST'], 1);
        $this->associateFluxWithConnector($id_co, 'actes-generique', 'signature');

        $document = $this->createDocument('actes-generique');
        return $document['id_d'];
    }

    public function testPatchExternalDataIParapheurSousTypeWithFastSignatureFailedWrongValue(): void
    {
        $this->mockSoapClient(
            function ($soapMethod, $arguments) {
                if ($soapMethod === 'getCircuits') {
                    return json_decode(
                        json_encode([
                            'return' => [
                                '0' => ['circuitId' => 'PES'],
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
        $id_d = $this->configureTestPatchExternalDataIParapheurSousType('fast-parapheur', 'circuits');
        $this->expectException(UnrecoverableException::class);
        $this->expectExceptionMessage(
            "Le circuit \"test\" n'existe pas ou est mal orthographié"
        );
        $this->getInternalAPI()->patch(
            "entite/1/document/$id_d/externalData/fast_parapheur_circuit",
            ['fast_parapheur_circuit' => 'test']
        );
    }

    public function testPatchExternalDataIParapheurSousTypeWithFastSignatureFailedWrongKey(): void
    {
        $this->mockSoapClient(
            function ($soapMethod, $arguments) {
                if ($soapMethod === 'getCircuits') {
                    return json_decode(
                        json_encode([
                            'return' => [
                                '0' => ['circuitId' => 'PES'],
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
        $id_d = $this->configureTestPatchExternalDataIParapheurSousType('fast-parapheur', 'circuits');
        $this->expectException(UnrecoverableException::class);
        $this->expectExceptionMessage("Le circuit \"\" n'existe pas ou est mal orthographié");
        $this->getInternalAPI()->patch(
            "entite/1/document/$id_d/externalData/fast_parapheur_circuit",
            ['fast_parapheur_circui' => 'foo']
        );
    }

    public function testPatchExternalDataIParapheurSousTypeWithFastSignatureFailedEmptyValue(): void
    {
        $this->mockSoapClient(
            function ($soapMethod, $arguments) {
                if ($soapMethod === 'getCircuits') {
                    return json_decode(
                        json_encode([
                            'return' => [
                                '0' => ['circuitId' => 'PES'],
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
        $id_d = $this->configureTestPatchExternalDataIParapheurSousType('fast-parapheur', 'circuits');
        $this->expectException(UnrecoverableException::class);
        $this->expectExceptionMessage("Le circuit \"\" n'existe pas ou est mal orthographié");
        $this->getInternalAPI()->patch(
            "entite/1/document/$id_d/externalData/fast_parapheur_circuit",
            ['fast_parapheur_circuit' => '']
        );
    }

    public function testPatchExternalDataIParapheurSousTypeFailedWrongKey(): void
    {
        $id_d = $this->configureTestPatchExternalDataIParapheurSousType('fakeIparapheur', 'iparapheur_sous_type');
        $this->expectException(UnrecoverableException::class);
        $this->expectExceptionMessage("Le sous-type \"\" n'existe pas pour le type configuré");
        $this->getInternalAPI()->patch(
            "entite/1/document/$id_d/externalData/iparapheur_sous_type",
            ['iparapheur_sous_typ' => 'foo']
        );
    }

    public function testPatchExternalDataIParapheurSousTypeFailedEmptyValue(): void
    {
        $id_d = $this->configureTestPatchExternalDataIParapheurSousType('fakeIparapheur', 'iparapheur_sous_type');
        $this->expectException(UnrecoverableException::class);
        $this->expectExceptionMessage("Le sous-type \"\" n'existe pas pour le type configuré");
        $this->getInternalAPI()->patch(
            "entite/1/document/$id_d/externalData/iparapheur_sous_type",
            ['iparapheur_sous_type' => '']
        );
    }

    public function testPatchExternalDataIParapheurSousTypeFailedWrongValue(): void
    {
        $id_d = $this->configureTestPatchExternalDataIParapheurSousType('fakeIparapheur', 'iparapheur_sous_type');
        $this->expectExceptionMessage(
            "Le sous-type \"test\" n'existe pas pour le type configuré"
        );
        $this->getInternalAPI()->patch(
            "entite/1/document/$id_d/externalData/fast_parapheur_circuit",
            ['iparapheur_sous_type' => 'test']
        );
    }

    public function testEditActionWithFileInputWithStringValue()
    {
        $id_d = $this->createTestDocument();
        $this->expectException('DonneesFormulaireException');
        $this->expectExceptionMessage("Le champ 'fichier' n'est pas autorisé sur un PATCH");
        $this->getInternalAPI()->patch("entite/1/document/$id_d", ['fichier' => 'toto']);
    }


    public function testPostChunk(): void
    {
        $id_d = $this->createTestDocument();
        $config = new \Flow\Config();
        $chunkNumber = 3;

        for ($i = 1; $i <= $chunkNumber; $i++) {
            $tmpFile = new TmpFile();
            $tmp = $tmpFile->copyToTmpDir(__DIR__ . '/fixtures/chunk' . $i, 'chunk' . $i);

            $_SERVER['REQUEST_METHOD'] = 'POST';
            $_REQUEST['file_name'] = 'toto.txt';
            $_REQUEST['chunk_number'] = $i;
            $_REQUEST['total_chunks'] = $chunkNumber;
            $_REQUEST['total_size'] = $chunkNumber;
            $_FILES['file'] = [
                'name' => 'chunk' . $i,
                'type' => 'text/plain',
                'full_path' => __DIR__ . '/fixtures/chunk' . $i,
                'error' => UPLOAD_ERR_OK,
                'size' => 1,
                'tmp_name' => $tmp,
            ];

            $source = $_FILES['file']['tmp_name'];

            $chunkRequest = new ChunkRequest();
            $chunkName = call_user_func($config->getHashNameCallback(), $chunkRequest->getRequest()) . "_$i";

            $chunkDirectory = $this->getObjectInstancier()->getInstance('upload_chunk_directory');
            $destination = $chunkDirectory . '/' . $chunkName;

            if (copy($source, $destination)) {
                echo "Le fichier a été déplacé avec succès.\n";
            } else {
                echo "Erreur lors du déplacement du fichier.\n";
            }

            $response = $this->getInternalAPI()->post(
                "entite/1/document/$id_d/chunk/fichier/0"
            );

            static::assertSame('success', $response['result']);
            static::assertSame($i < $chunkNumber ? 'Chunk uploaded' : 'File uploaded', $response['message']);
        }
        static::assertSame('123', $this->getDonneesFormulaireFactory()->get($id_d)->getFileContent('fichier', '0'));
    }
}
