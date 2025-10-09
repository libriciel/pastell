<?php

namespace Pastell\Tests\Step\SAE\Action;

use DocumentActionEntite;
use DocumentEntite;
use NotFoundException;
use Pastell\Step\SAE\Enum\SAEActionsEnum;
use PastellTestCase;
use TypeDossierLoader;

final class SAESendArchiveActionTest extends PastellTestCase
{
    public const SAE_ONLY = 'sae-only';
    private TypeDossierLoader $typeDossierLoader;
    private DocumentEntite $documentEntite;
    private DocumentActionEntite $documentActionEntite;

    protected function setUp(): void
    {
        parent::setUp();
        $this->typeDossierLoader = $this->getObjectInstancier()->getInstance(TypeDossierLoader::class);
        $this->documentEntite = $this->getObjectInstancier()->getInstance(DocumentEntite::class);
        $this->documentActionEntite = $this->getObjectInstancier()->getInstance(DocumentActionEntite::class);
    }

    protected function tearDown(): void
    {
        $this->typeDossierLoader->unload();
        parent::tearDown();
    }

    /**
     * @throws NotFoundException
     * @throws \Exception
     */
    private function getDocument(): string
    {
        $document = $this->createDocument(self::SAE_ONLY);
        $donneesFormulaire = $this->getDonneesFormulaireFactory()->get($document['id_d']);
        $donneesFormulaire->setTabData([
            'titre' => 'Foo',
            'date' => '1977-02-18',
            'select' => 'B',
        ]);
        $donneesFormulaire->addFileFromData('fichier', 'fichier.txt', 'bar');
        return $document['id_d'];
    }

    public function testSendArchive(): void
    {
        $this->typeDossierLoader->createTypeDossierDefinitionFile(self::SAE_ONLY);

        $sedaConnector = $this->createConnector('FakeSEDA', 'Bordereau SEDA');
        $this->associateFluxWithConnector($sedaConnector['id_ce'], self::SAE_ONLY, 'Bordereau SEDA');

        $saeConnector = $this->createConnector('fakeSAE', 'SAE');
        $this->associateFluxWithConnector($saeConnector['id_ce'], self::SAE_ONLY, 'SAE');

        $documentId = $this->getDocument();
        $this->assertTrue(
            $this->triggerActionOnDocument($documentId, 'orientation')
        );
        $this->assertLastMessage("sélection automatique de l'action suivante");

        $result = $this->triggerActionOnDocument($documentId, SAEActionsEnum::GENERATE_SIP->value);
        $this->assertTrue($result);

        $result = $this->triggerActionOnDocument($documentId, SAEActionsEnum::SEND_ARCHIVE->value);
        $this->assertTrue($result);

        $this->assertLastMessage('Le document a été envoyé au SAE');
        $this->assertLastDocumentAction(SAEActionsEnum::SEND_ARCHIVE->value, $documentId);
    }

    public function testSendArchiveError(): void
    {
        $this->typeDossierLoader->createTypeDossierDefinitionFile(self::SAE_ONLY);

        $sedaConnector = $this->createConnector('FakeSEDA', 'Bordereau SEDA');
        $this->associateFluxWithConnector($sedaConnector['id_ce'], self::SAE_ONLY, 'Bordereau SEDA');

        $saeConnector = $this->createConnector('fakeSAE', 'SAE');
        $this->configureConnector($saeConnector['id_ce'], [
            'result_send' => 2,
        ]);
        $this->associateFluxWithConnector($saeConnector['id_ce'], self::SAE_ONLY, 'SAE');

        $documentId = $this->getDocument();
        $this->assertTrue(
            $this->triggerActionOnDocument($documentId, 'orientation')
        );
        $this->assertLastMessage("sélection automatique de l'action suivante");

        $result = $this->triggerActionOnDocument($documentId, SAEActionsEnum::GENERATE_SIP->value);
        $this->assertTrue($result);

        $result = $this->triggerActionOnDocument($documentId, SAEActionsEnum::SEND_ARCHIVE->value);
        $this->assertFalse($result);
        $this->assertLastMessage(
            "Ce connecteur bouchon est configuré pour renvoyer une erreur - L'envoi du bordereau a échoué : "
        );
        $this->assertLastDocumentAction(SAEActionsEnum::SEND_ARCHIVE_ERROR->value, $documentId);

        /*
         * test : Il n'y a plus de modification du dernier état (table document_entite) lors de plusieurs
         * tentatives d'action identique en erreur sur un document #2333
         */
        $this->assertSame(
            $this->documentEntite->getFromAction(
                self::SAE_ONLY,
                SAEActionsEnum::SEND_ARCHIVE_ERROR->value
            )[0]['last_action_date'],
            $this->documentActionEntite->getLastActionInfo(self::ID_E_COL, $documentId)['date']
        );

        sleep(1);
        $this->triggerActionOnDocument($documentId, SAEActionsEnum::SEND_ARCHIVE->value);

        $this->assertLessThan(
            $this->documentEntite->getFromAction(
                self::SAE_ONLY,
                SAEActionsEnum::SEND_ARCHIVE_ERROR->value
            )[0]['last_action_date'],
            $this->documentActionEntite->getLastActionInfo(self::ID_E_COL, $documentId)['date']
        );
    }
}
