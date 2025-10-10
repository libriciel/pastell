<?php

namespace Pastell\Tests\Step\SAE\Action;

use NotFoundException;
use Pastell\Step\SAE\Enum\SAEActionsEnum;
use PastellTestCase;
use TypeDossierLoader;

final class SAESendArchiveActionTest extends PastellTestCase
{
    public const SAE_ONLY = 'sae-only';
    private TypeDossierLoader $typeDossierLoader;

    protected function setUp(): void
    {
        parent::setUp();
        $this->typeDossierLoader = $this->getObjectInstancier()->getInstance(TypeDossierLoader::class);
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
    }
}
