<?php

declare(strict_types=1);

namespace Pastell\Tests\Step\Tdt\Acte\Action;

use TypeDossierLoader;

final class DuplicateActeActionTest extends \PastellTestCase
{
    public const TDT_ACTES_ONLY = 'tdt-actes-only';
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
     * @throws \NotFoundException
     * @throws \Exception
     */
    private function getDocument(): string
    {
        $document = $this->createDocument(self::TDT_ACTES_ONLY);
        $documentId = $document['id_d'];
        $this->configureDocument($documentId, [
            'objet' => 'Objet',
            'envoi_tdt_actes' => true,
            'acte_nature' => '1',
            'numero_de_lacte' => 'AAAA',
            'date_de_lacte' => '2024-01-01',
            'document_papier' => true,
            'classification' => '1.1',
        ]);

        $donneesFormulaire = $this->getDonneesFormulaireFactory()->get($documentId);

        $donneesFormulaire->addFileFromData('actes', 'actes.pdf', '%PDF1-4');
        return $documentId;
    }

    /**
     * @throws \TypeDossierException
     * @throws \NotFoundException
     */
    public function testDuplicate(): void
    {
        $this->typeDossierLoader->createTypeDossierDefinitionFile(self::TDT_ACTES_ONLY);

        $documentId = $this->getDocument();
        self::assertTrue(
            $this->triggerActionOnDocument($documentId, 'duplicate')
        );
        $documentList = $this->getInternalAPI()->get('/entite/1/document');
        self::assertCount(2, $documentList);

        $duplicatedDocumentId = ($documentList[0]['id_d'] === $documentId) ?
            $documentList[1]['id_d'] :
            $documentList[0]['id_d'];

        self::assertNotSame($documentId, $duplicatedDocumentId);
        $oldDocumentForm = $this->getDonneesFormulaireFactory()->get($documentId);
        $duplicatedDocumentForm = $this->getDonneesFormulaireFactory()->get($duplicatedDocumentId);
        self::assertSame(
            $oldDocumentForm->get('objet'),
            $duplicatedDocumentForm->get('objet')
        );
        self::assertSame(
            $oldDocumentForm->get('acte_nature'),
            $duplicatedDocumentForm->get('acte_nature')
        );
        self::assertNotSame(
            $oldDocumentForm->get('numero_de_lacte'),
            $duplicatedDocumentForm->get('numero_de_lacte')
        );
        self::assertSame(
            $oldDocumentForm->get('date_de_lacte'),
            $duplicatedDocumentForm->get('date_de_lacte')
        );
        self::assertSame(
            $oldDocumentForm->get('document_papier'),
            $duplicatedDocumentForm->get('document_papier')
        );

        self::assertNotSame(
            $oldDocumentForm->get('actes'),
            $duplicatedDocumentForm->get('actes'),
        );

        $this->assertLastDocumentAction('modification', $documentId);
    }
}
