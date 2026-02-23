<?php

use Mailsec\MailsecManager;
use Pastell\Service\Utilisateur\UserCreationService;
use Pastell\Utilities\Identifier\IdentifierGeneratorInterface;
use Pastell\Utilities\Identifier\UuidGenerator;
use Symfony\Component\HttpFoundation\Request;

class DocumentControlerTest extends ControlerTestCase
{
    use MailsecTestTrait;
    use TypeDossierLoaderTestTrait;

    /**
     * @throws Exception
     */
    public function testReindex()
    {
        $info = $this->getInternalAPI()->post("entite/1/document", ['type' => 'test']);

        $this->getInternalAPI()->patch(
            "entite/1/document/{$info['id_d']}",
            ['nom' => 'foo']
        );

        $result = $this->getInternalAPI()->get("entite/1/document?type=test&nom=foo");
        $this->assertEquals($info['id_d'], $result[0]['id_d']);

        $this->getSQLQuery()->query("DELETE FROM document_index");
        $result = $this->getInternalAPI()->get("entite/1/document?type=test&nom=foo");
        $this->assertEmpty($result);

        /** @var DocumentControler $documentController */
        $documentController = $this->getObjectInstancier()->getInstance(DocumentControler::class);
        $this->expectOutputString(
            "Nombre de documents : 1\nRéindexation du document  ({$info['id_d']})\n"
        );
        $documentController->reindex('test', 'nom');
        $result = $this->getInternalAPI()->get("entite/1/document?type=test&nom=foo");
        $this->assertEquals($info['id_d'], $result[0]['id_d']);
    }

    public function testActionActionNoRight()
    {
        $info = $this->getInternalAPI()->post("entite/1/document", ['type' => 'test']);

        $authentification = $this->getObjectInstancier()->getInstance(Authentification::class);
        $authentification->connexion('foo', 42);

        /** @var DocumentControler $documentController */
        $documentController = $this->getObjectInstancier()->getInstance(DocumentControler::class);
        try {
            $this->expectOutputRegex("#id_e=1#");
            $documentController->setServerInfo(['REQUEST_METHOD' => 'POST']);
            $documentController->setPostInfo(new Recuperateur(
                [
                    'id_e' => 1,
                    'id_d' => $info['id_d'],
                    'action' => 'no-way'
                ]
            ));
            $documentController->actionAction();
        } catch (Exception $e) {
        }
        $this->assertEquals(
            "Vous n'avez pas les droits nécessaires (1:test:edition) pour accéder à cette page",
            $documentController->getLastError()->getLastMessage()
        );
    }

    public function testActionAction()
    {
        $info = $this->getInternalAPI()->post("entite/1/document", ['type' => 'test']);

        $authentification = $this->getObjectInstancier()->getInstance(Authentification::class);
        $authentification->connexion('foo', 1);


        /** @var DocumentControler $documentController */
        $documentController = $this->getObjectInstancier()->getInstance(DocumentControler::class);
        try {
             $this->expectOutputRegex("#id_e=1#");
            $documentController->setServerInfo(['REQUEST_METHOD' => 'POST']);
            $documentController->setPostInfo(new Recuperateur(
                [
                    'id_e' => 1,
                    'id_d' => $info['id_d'],
                    'action' => 'no-way'
                ]
            ));
            $documentController->actionAction();
        } catch (Exception $e) {
        }
        $this->assertEquals(
            "L'action no-way a été executée sur le document",
            $documentController->getLastMessage()->getLastMessage()
        );
    }

    /**
     * @throws ForbiddenException
     * @throws NotFoundException
     * @throws UnrecoverableException
     */
    public function testTextareaReadOnly()
    {
        $info = $this->createDocument('test');

        /** @var DocumentControler $documentControler */
        $documentControler = $this->getControlerInstance(DocumentControler::class);

        $this->setGetInfo(['id_e' => 1,'id_d' => $info['id_d']]);

        $this->setOutputCallback(function ($output) {
            $this->assertDoesNotMatchRegularExpression(
                "#<textarea(.*)name='test_textarea'(.*)</textarea>#s",
                $output
            );

            $this->assertMatchesRegularExpression(
                "#<textarea(.*)name='test_textarea_read_write'(.*)</textarea>#s",
                $output
            );
        });
        $documentControler->editionAction();
    }

    public function testListDocument(): void
    {
        $this->expectOutputRegex('/Liste des dossiers Actes \(générique\) - déprécié pour Bourg-en-Bresse/');
        $documentController = $this->getControlerInstance(DocumentControler::class);
        $this->setGetInfo([
            'id_e' => 1,
            'type' => 'actes-generique',
            'filtre' => 'modification',
        ]);

        $documentController->listAction();

        $this->assertTrue($documentController->isViewParameter('url'));
        $this->assertSame(
            'id_e=1&search=&type=actes-generique&lastetat=modification',
            $documentController->getViewParameter()['url']
        );
    }

    public function testListDocumentRootEntity(): void
    {
        $this->expectException(LastMessageException::class);
        $site_base = $this->getObjectInstancier()->getInstance('site_base');
        $this->expectExceptionMessage('Redirection vers ' . $site_base . '/Document/index?id_e=0&type=actes-generique');
        $documentController = $this->getControlerInstance(DocumentControler::class);
        $this->setGetInfo([
            'type' => 'actes-generique',
        ]);

        $documentController->listAction();
    }

    /**
     * @throws ForbiddenException
     * @throws NotFoundException
     * @throws UnrecoverableException
     */
    public function testEditOnlyProperties()
    {
        $document_info = $this->createDocument('test');
        /** @var DocumentControler $documentController */
        $documentController = $this->getControlerInstance(DocumentControler::class);
        $this->setGetInfo([
            'id_d' => $document_info['id_d'],
            'id_e' => PastellTestCase::ID_E_COL
        ]);

        ob_start();
        $documentController->editionAction();
        $result = ob_get_contents();
        ob_end_clean();
        $this->assertMatchesRegularExpression("#test_edit_only#", $result);

        ob_start();
        $documentController->detailAction();
        $result = ob_get_contents();
        ob_end_clean();
        $this->assertDoesNotMatchRegularExpression("#test_edit_only#", $result);
    }

    public function testIndexActionAsNotSuperadmin(): void
    {
        $authentification = $this->getObjectInstancier()->getInstance(Authentification::class);

        $documentController = $this->getControlerInstance(DocumentControler::class);
        $authentification->connexion('eric', 2);

        ob_start();
        $documentController->indexAction();
        $result = ob_get_clean();

        $this->assertDoesNotMatchRegularExpression(
            '/Veuillez sélectionner une entité afin de pouvoir visualiser ses dossiers/',
            $result
        );
    }

    /**
     * @throws UnrecoverableException
     * @throws ConflictException
     */
    public function testIndexWithoutRight(): void
    {
        $userCreationService = $this->getObjectInstancier()->getInstance(UserCreationService::class);
        $id_u = $userCreationService->create('badguy', 'test@bar.baz', 'foo', 'foo');

        $roleUtilisateur = $this->getObjectInstancier()->getInstance(RoleUtilisateur::class);
        $roleUtilisateur->addRole($id_u, 'admin', 2);
        $this->getObjectInstancier()->getInstance(Authentification::class)->connexion('admin', $id_u);

        $documentController = $this->getObjectInstancier()->getInstance(DocumentControler::class);
        $documentController->setGetInfo(new Recuperateur(['id_e' => 1,]));
        try {
            ob_start(); //Very uggly...
            $documentController->indexAction();
            static::assertTrue(false);
        } catch (Exception $e) {
            /* Nothing to do */
        }
        ob_end_clean();
        static::assertEquals(
            "Vous n'avez pas les droits nécessaires pour accéder à cette page",
            $documentController->getLastError()->getLastError()
        );
    }

    /**
     * @throws UnrecoverableException
     * @throws ConflictException
     */
    public function testIndexWithTwoRoleOnTwoEntities(): void
    {
        $userCreationService = $this->getObjectInstancier()->getInstance(UserCreationService::class);
        $id_u = $userCreationService->create('badguy', 'test@bar.baz', 'foo', 'foo');

        $documentController = $this->getControlerInstance(DocumentControler::class);
        $roleUtilisateur = $this->getObjectInstancier()->getInstance(RoleUtilisateur::class);
        $roleUtilisateur->addRole($id_u, 'admin', 2);
        $roleUtilisateur->addRole($id_u, 'admin', 1);
        $this->getObjectInstancier()->getInstance(Authentification::class)->connexion('admin', $id_u);

        ob_start();
        $documentController->indexAction();
        $result = ob_get_clean();
        static::assertStringContainsString('Bourg-en-Bresse', $result);
        static::assertStringContainsString('CCAS', $result);
    }

    /**
     * @throws UnrecoverableException
     * @throws ConflictException
     */
    public function testIndexWithTwoDifferentRoleOnTwoEntities(): void
    {
        $userCreationService = $this->getObjectInstancier()->getInstance(UserCreationService::class);
        $id_u = $userCreationService->create('badguy', 'test@bar.baz', 'foo', 'foo');

        $roleSQL = $this->getObjectInstancier()->getInstance(RoleSQL::class);
        $roleSQL->addDroit('utilisateur', 'actes-generique:lecture');

        $roleUtilisateur = $this->getObjectInstancier()->getInstance(RoleUtilisateur::class);
        $roleUtilisateur->addRole($id_u, 'admin', 2);
        $roleUtilisateur->addRole($id_u, 'utilisateur', 1);
        $this->getObjectInstancier()->getInstance(Authentification::class)->connexion('admin', $id_u);

        $documentController = $this->getControlerInstance(DocumentControler::class);
        $documentController->setGetInfo(new Recuperateur(['id_e' => 1]));

        ob_start();
        $documentController->indexAction();
        $result = ob_get_clean();
        static::assertStringContainsString('Bourg-en-Bresse', $result);
        static::assertStringContainsString('CCAS', $result);
    }


    /**
     * @throws NotFoundException
     * @throws Exception
     */
    public function testDoEditionCallsOnChangeWhenUploadingAFile()
    {
        $document = $this->createDocument('helios-generique');
        $donneesFormulaire = $this->getDonneesFormulaireFactory()->get($document['id_d']);
        $donneesFormulaire->addFileFromCopy(
            'fichier_pes',
            'PES.xml',
            __DIR__ . '/../pastell-core/fixtures/HELIOS_SIMU_ALR2_1496987735_826268894.xml'
        );
        $donneesFormulaire = $this->getDonneesFormulaireFactory()->get($document['id_d']);
        $this->assertFalse($donneesFormulaire->get('objet'));

        $this->getObjectInstancier()->getInstance(Authentification::class)->connexion('admin', self::ID_U_ADMIN);

        $documentController = $this->getObjectInstancier()->getInstance(DocumentControler::class);
        $documentController->setPostInfo(new Recuperateur([
            'id_e' => self::ID_E_COL,
            'id_d' => $document['id_d'],
            'fieldSubmittedId' => 'fichier_pes'
        ]));

        try {
            ob_start();
            $documentController->doEditionAction();
        } catch (Exception $e) {
            /* Nothing to do */
        }
        ob_end_clean();
        $donneesFormulaire = $this->getDonneesFormulaireFactory()->get($document['id_d']);
        $this->assertSame(
            'HELIOS_SIMU_ALR2_1496987735_826268894.xml',
            $donneesFormulaire->get('objet')
        );
    }

    public function testChangeEtat(): void
    {
        $documentController = $this->getControlerInstance(DocumentControler::class);
        $document = $this->createDocument('test');
        $this->setPostInfo([
            'id_d' => $document['id_d'],
            'id_e' => PastellTestCase::ID_E_COL,
            'action' => 'unknown_action',
            'message' => 'message',
        ]);
        $this->expectException(LastErrorException::class);
        $this->expectExceptionMessageMatches("/L'action unknown_action n'existe pas/");

        $documentController->changeEtatAction();
    }

    /**
     * @throws Exception
     */
    public function testRecuperationFichierAction(): void
    {
        $mail_sec_info = $this->createMailSec('mailsec-bidir', 'envoi-mail');
        $key = $mail_sec_info['key'];

        $mailsecManager = $this->getObjectInstancier()->getInstance(MailsecManager::class);
        $mailsecInfo = $mailsecManager->getMailsecInfo($key, new Request());
        $mailsecInfo  = $mailsecManager->createDocumentResponse($mailsecInfo);

        $donneesFormulaire = $this->getDonneesFormulaireFactory()->get($mailsecInfo->id_d_reponse);
        $donneesFormulaire->addFileFromData('document_attache', 'foo.txt', 'bar');

        $documentController = $this->getControlerInstance(DocumentControler::class);

        $this->setGetInfo([
            'id_e' => 1,
            'id_d' => $mailsecInfo->id_d_reponse,
            'field' => 'document_attache',
        ]);

        ob_start();
        $documentController->recuperationFichierAction();
        $output = ob_get_clean();
        $this->assertEquals('Content-type: text/plain
Content-disposition: attachment; filename*=UTF-8\'\'foo.txt; filename=foo.txt
Expires: 0
Cache-Control: must-revalidate, post-check=0,pre-check=0
Pragma: public
bar', $output);
    }

    public function testEditionWithDefaultValue(): void
    {
        $this->loadTypeDossier(__DIR__ . '/../pastell-core/type-dossier/fixtures/test-default-value.json');
        $typeDossier = 'testdefaultvalue';
        $this->getObjectInstancier()->getInstance(RoleSQL::class)->addDroit('admin', "$typeDossier:lecture");
        $this->getObjectInstancier()->getInstance(RoleSQL::class)->addDroit('admin', "$typeDossier:edition");
        $documentID = $this->createDocument($typeDossier)['id_d'];
        $data = $this->getDonneesFormulaireFactory()->get($documentID, $typeDossier)->getRawDataWithoutPassword();
        $this->assertSame('Ma valeur par défaut !', $data['nomtest']);
        $this->assertSame(
            'Sur
Plusieurs
Lignes',
            $data['airedetexte']
        );
        $this->assertSame('on', $data['macheckbox']);
        $this->assertSame('3', $data['maselection']);
    }

    public function testNoJobLeftFatalErrorFromDocument(): void
    {
        $id_d = $this->createDocument('test')['id_d'];
        $this->triggerActionOnDocument($id_d, 'action-auto');
        $jobQueueSQL = $this->getObjectInstancier()->getInstance(JobQueueSQL::class);
        static::assertTrue($jobQueueSQL->hasDocumentJob(self::ID_E_COL, $id_d));
        $this->setPostInfo([
            'id_d' => $id_d,
            'action' => FatalError::ACTION_ID,
            'id_e' => self::ID_E_COL,
            'go' => 1,
        ]);
        try {
            $controller = $this->getControlerInstance(DocumentControler::class);
            $controller->setServerInfo(['REQUEST_METHOD' => 'POST']);
            $controller->actionAction();
        } catch (Exception) {
        }
        static::assertFalse($jobQueueSQL->hasDocumentJob(self::ID_E_COL, $id_d));
    }

    public function testCreateDocumentOnDeactivatedEntity(): void
    {
        $this->getInternalAPI()->post('/entite/1/deactivate');
        $this->expectException(ForbiddenException::class);
        $this->expectExceptionMessage("L'entité 1 est désactivée");
        $this->createDocument('test');
    }

    private function setUuidForDocument(): void
    {
        $this->getObjectInstancier()->setInstance(IdentifierGeneratorInterface::class, new UuidGenerator());
        $this->getObjectInstancier()->setInstance(DocumentSQL::class, new DocumentSQL(
            static::getSQLQuery(),
            $this->getObjectInstancier()->getInstance(IdentifierGeneratorInterface::class)
        ));
    }

    public function testCreateDocumentWithUUID(): void
    {
        $this->setUuidForDocument();
        $id_d = $this->createDocument('test')['id_d'];
        static::assertTrue(strlen($id_d) === 36);
    }

    public function testGetDocumentWithUUID(): void
    {
        $this->setUuidForDocument();
        $id_d = $this->createDocument('test')['id_d'];
        $documentSQL = $this->getObjectInstancier()->getInstance(DocumentSQL::class);
        $info = $documentSQL->getInfo($id_d);
        static::assertTrue($id_d === $info['id_d'] && 'test' === $info['type']);
    }
}
