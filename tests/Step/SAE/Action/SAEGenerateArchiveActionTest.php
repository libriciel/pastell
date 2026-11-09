<?php

declare(strict_types=1);

namespace Pastell\Tests\Step\SAE\Action;

use NotFoundException;
use Pastell\Step\SAE\Enum\SAEActionsEnum;
use PastellTestCase;
use TypeDossierException;
use TypeDossierLoader;

final class SAEGenerateArchiveActionTest extends PastellTestCase
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
    private function getDocument(?string $saeCongig = null): string
    {
        $document = $this->createDocument(self::SAE_ONLY);
        $donneesFormulaire = $this->getDonneesFormulaireFactory()->get($document['id_d']);
        $donneesFormulaire->setTabData([
            'titre' => 'Foo',
            'date' => '1977-02-18',
            'select' => 'B',
        ]);
        $donneesFormulaire->addFileFromData('fichier', 'fichier.txt', 'bar');
        if ($saeCongig !== null) {
            $donneesFormulaire->addFileFromData('sae_config', 'metadata.json', $saeCongig);
        }
        return $document['id_d'];
    }

    /**
     * @throws TypeDossierException
     * @throws NotFoundException
     */
    public function testGenerateArchive(): void
    {
        $this->typeDossierLoader->createTypeDossierDefinitionFile(self::SAE_ONLY);

        $sedaConnector = $this->createConnector('FakeSEDA', 'Bordereau SEDA');
        $this->associateFluxWithConnector($sedaConnector['id_ce'], self::SAE_ONLY, 'Bordereau SEDA');

        $saeConnector = $this->createConnector('fakeSAE', 'SAE');
        $this->associateFluxWithConnector($saeConnector['id_ce'], self::SAE_ONLY, 'SAE');

        $documentId = $this->getDocument('{}');
        $this->assertTrue(
            $this->triggerActionOnDocument($documentId, 'orientation')
        );
        $this->assertLastMessage("sélection automatique de l'action suivante");

        $result = $this->triggerActionOnDocument($documentId, SAEActionsEnum::GENERATE_SIP->value);
        $this->assertTrue($result);

        $this->assertLastMessage("L'archive a été générée");
        $this->assertLastDocumentAction(SAEActionsEnum::GENERATE_SIP->value, $documentId);
    }

    /**
     * @throws TypeDossierException
     * @throws NotFoundException
     */
    public function testGenerateArchiveWithInvalidJsonConfig(): void
    {
        $this->typeDossierLoader->createTypeDossierDefinitionFile(self::SAE_ONLY);

        $sedaConnector = $this->createConnector('FakeSEDA', 'Bordereau SEDA');
        $this->associateFluxWithConnector($sedaConnector['id_ce'], self::SAE_ONLY, 'Bordereau SEDA');

        $saeConnector = $this->createConnector('fakeSAE', 'SAE');
        $this->associateFluxWithConnector($saeConnector['id_ce'], self::SAE_ONLY, 'SAE');

        $documentId = $this->getDocument('not a json conf');
        $this->assertTrue(
            $this->triggerActionOnDocument($documentId, 'orientation')
        );
        $this->assertLastMessage("sélection automatique de l'action suivante");

        $result = $this->triggerActionOnDocument($documentId, SAEActionsEnum::GENERATE_SIP->value);
        $this->assertFalse($result);

        $this->assertLastMessage('Fichier de configuration SAE : Syntax error');
        $this->assertLastDocumentAction(SAEActionsEnum::GENERATE_SIP_ERROR->value, $documentId);
    }

    /**
     * @throws TypeDossierException
     * @throws NotFoundException
     */
    public function testGenerateArchiveError(): void
    {
        $this->typeDossierLoader->createTypeDossierDefinitionFile(self::SAE_ONLY);

        $sedaConnector = $this->createConnector('FakeSEDA', 'Bordereau SEDA');
        $this->associateFluxWithConnector($sedaConnector['id_ce'], self::SAE_ONLY, 'Bordereau SEDA');
        $this->configureConnector($sedaConnector['id_ce'], [
            'seda_archive_generation_response' => 'error',
        ]);

        $saeConnector = $this->createConnector('fakeSAE', 'SAE');
        $this->associateFluxWithConnector($saeConnector['id_ce'], self::SAE_ONLY, 'SAE');

        $documentId = $this->getDocument();
        $this->assertTrue(
            $this->triggerActionOnDocument($documentId, 'orientation')
        );
        $this->assertLastMessage("sélection automatique de l'action suivante");

        $result = $this->triggerActionOnDocument($documentId, SAEActionsEnum::GENERATE_SIP->value);
        $this->assertFalse($result);

        $this->assertLastMessage('FakeSEDA: Erreur provoquée par le simulateur');
        $this->assertLastDocumentAction(SAEActionsEnum::GENERATE_SIP_ERROR->value, $documentId);
    }

    /**
     * @throws TypeDossierException
     * @throws NotFoundException
     */
    public function testGenerateArchiveErrorOnBordereau(): void
    {
        $this->typeDossierLoader->createTypeDossierDefinitionFile(self::SAE_ONLY);

        $sedaConnector = $this->createConnector('FakeSEDA', 'Bordereau SEDA');
        $this->associateFluxWithConnector($sedaConnector['id_ce'], self::SAE_ONLY, 'Bordereau SEDA');
        $this->configureConnector($sedaConnector['id_ce'], [
            'seda_bordereau_generation_response' => 'error',
        ]);

        $saeConnector = $this->createConnector('fakeSAE', 'SAE');
        $this->associateFluxWithConnector($saeConnector['id_ce'], self::SAE_ONLY, 'SAE');

        $documentId = $this->getDocument();
        $this->assertTrue(
            $this->triggerActionOnDocument($documentId, 'orientation')
        );
        $this->assertLastMessage("sélection automatique de l'action suivante");

        $result = $this->triggerActionOnDocument($documentId, SAEActionsEnum::GENERATE_SIP->value);
        $this->assertFalse($result);

        $this->assertLastMessage('FakeSEDA: Invalid bordereau : <br/><br/>FakeSEDA: Error 1<br/>');
        $this->assertLastDocumentAction(SAEActionsEnum::GENERATE_SIP_ERROR->value, $documentId);
    }
}
