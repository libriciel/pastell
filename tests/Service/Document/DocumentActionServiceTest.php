<?php

declare(strict_types=1);

namespace Pastell\Tests\Service\Document;

use ActionChange;
use Exception;
use FatalError;
use JobQueueSQL;
use NotFoundException;
use Pastell\Service\Document\DocumentActionService;
use PastellTestCase;
use UnrecoverableException;

final class DocumentActionServiceTest extends PastellTestCase
{
    private DocumentActionService $documentActionService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->documentActionService = $this->getObjectInstancier()->getInstance(DocumentActionService::class);
    }

    /**
     * @throws UnrecoverableException
     */
    public function testExecute(): void
    {
        $id_d = $this->createDocument('test')['id_d'];

        self::assertSame(
            'OK !',
            $this->documentActionService->execute(self::ID_E_COL, self::ID_U_ADMIN, $id_d, 'ok')
        );
    }

    /**
     * @throws UnrecoverableException
     */
    public function testExecuteFailure(): void
    {
        $id_d = $this->createDocument('test')['id_d'];

        $this->expectException(UnrecoverableException::class);
        $this->expectExceptionMessage('Raté !');
        $this->documentActionService->execute(self::ID_E_COL, self::ID_U_ADMIN, $id_d, 'fail');
    }

    /**
     * @throws UnrecoverableException
     */
    public function testFatalErrorDeletesJobs(): void
    {
        $id_d = $this->createDocument('test')['id_d'];
        $this->triggerActionOnDocument($id_d, 'action-auto');
        $jobQueueSQL = $this->getObjectInstancier()->getInstance(JobQueueSQL::class);
        self::assertTrue($jobQueueSQL->hasDocumentJob(self::ID_E_COL, $id_d));

        $this->documentActionService->execute(self::ID_E_COL, self::ID_U_ADMIN, $id_d, FatalError::ACTION_ID);

        self::assertFalse($jobQueueSQL->hasDocumentJob(self::ID_E_COL, $id_d));
    }

    /**
     * @throws NotFoundException
     */
    public function testExecuteOnChange(): void
    {
        $id_d = $this->createDocument('test')['id_d'];
        $this->getDonneesFormulaireFactory()->get($id_d)->setData('test_on_change', 'bar');

        $this->documentActionService->executeOnChange(self::ID_E_COL, self::ID_U_ADMIN, $id_d, 'test_on_change');

        $this->getDonneesFormulaireFactory()->clearCache();
        self::assertSame('bar', $this->getDonneesFormulaireFactory()->get($id_d)->get('test2'));
    }

    /**
     * @throws UnrecoverableException
     */
    public function testChangeState(): void
    {
        $id_d = $this->createDocument('test')['id_d'];

        $this->documentActionService->changeState(self::ID_E_COL, self::ID_U_ADMIN, $id_d, 'ok', 'message');

        $this->assertLastDocumentAction('ok', $id_d);
    }

    /**
     * @throws UnrecoverableException
     */
    public function testChangeStateUnknownAction(): void
    {
        $id_d = $this->createDocument('test')['id_d'];

        $this->expectException(UnrecoverableException::class);
        $this->expectExceptionMessage("L'action unknown n'existe pas");
        $this->documentActionService->changeState(self::ID_E_COL, self::ID_U_ADMIN, $id_d, 'unknown', 'message');
    }

    /**
     * @throws UnrecoverableException
     */
    public function testChangeStateAnotherEntity(): void
    {
        $id_d = $this->createDocument('test', self::ID_E_SERVICE)['id_d'];

        $this->expectException(UnrecoverableException::class);
        $this->expectExceptionMessage("Le document $id_d n'appartient pas à l'entité " . self::ID_E_COL);
        $this->documentActionService->changeState(self::ID_E_COL, self::ID_U_ADMIN, $id_d, 'ok', 'message');
    }

    /**
     * @throws UnrecoverableException
     * @throws Exception
     */
    public function testRefusesOtherType(): void
    {
        $id_d = $this->createDocument('test')['id_d'];

        $this->expectException(UnrecoverableException::class);
        $this->expectExceptionMessage("n'est pas de type « actes-generique »");
        $this->documentActionService->executeTeletransmissionReturn(
            self::ID_E_COL,
            self::ID_U_ADMIN,
            'actes-generique',
            [$id_d],
            'return-teletransmission-tdt'
        );
    }

    /**
     * @throws UnrecoverableException
     * @throws Exception
     */
    public function testReturnRefusesOtherAction(): void
    {
        $id_d = $this->createDocument('actes-generique')['id_d'];

        $this->expectException(UnrecoverableException::class);
        $this->expectExceptionMessage("Cette action n'est pas un retour de télétransmission");
        $this->documentActionService->executeTeletransmissionReturn(
            self::ID_E_COL,
            self::ID_U_ADMIN,
            'actes-generique',
            [$id_d],
            'supression'
        );
    }

    /**
     * @throws UnrecoverableException
     * @throws Exception
     */
    public function testTeletransmissionReturn(): void
    {
        $connector = $this->createConnector('fakeTdt', 'Fake TdT');
        $this->associateFluxWithConnector($connector['id_ce'], 'actes-generique', 'TdT');
        $possibleDocument = $this->createDocument('actes-generique');
        $this->getObjectInstancier()->getInstance(ActionChange::class)->addAction(
            $possibleDocument['id_d'],
            self::ID_E_COL,
            self::ID_U_ADMIN,
            'teletransmission-tdt',
            'Télétransmission ordonnée'
        );
        $impossibleDocument = $this->createDocument('actes-generique');

        $message = $this->documentActionService->executeTeletransmissionReturn(
            self::ID_E_COL,
            self::ID_U_ADMIN,
            'actes-generique',
            [$possibleDocument['id_d'], $impossibleDocument['id_d']],
            'return-teletransmission-tdt'
        );

        self::assertSame(
            "L'action « Ordre de télétransmission envoyé sur le TDT » n'est pas possible pour le document "
            . "« {$impossibleDocument['id_d']} »<br/>"
            . "Le document « {$possibleDocument['titre']} » a été télétransmis<br/>",
            $message
        );
        $this->assertLastDocumentAction('send-tdt', $possibleDocument['id_d']);
        $this->assertLastDocumentAction('creation', $impossibleDocument['id_d']);
    }

    /**
     * @throws UnrecoverableException
     */
    public function testBatchRefusesImpossibleAction(): void
    {
        $id_d = $this->createDocument('test')['id_d'];

        $this->expectException(UnrecoverableException::class);
        $this->expectExceptionMessage("L'action « not-possible » n'est pas possible pour le document");
        $this->documentActionService->getDocumentsForBatchAction(
            self::ID_E_COL,
            self::ID_U_ADMIN,
            'test',
            [$id_d],
            'not-possible'
        );
    }

    /**
     * @throws UnrecoverableException
     */
    public function testBatchRefusesScheduledAction(): void
    {
        $id_d = $this->createDocument('test')['id_d'];
        $this->triggerActionOnDocument($id_d, 'action-auto');

        $this->expectException(UnrecoverableException::class);
        $this->expectExceptionMessage('Il y a déjà une action programmée pour le document');
        $this->documentActionService->getDocumentsForBatchAction(
            self::ID_E_COL,
            self::ID_U_ADMIN,
            'test',
            [$id_d],
            'ok'
        );
    }

    /**
     * @throws UnrecoverableException
     */
    public function testGetBatchDocuments(): void
    {
        $id_d = $this->createDocument('test')['id_d'];

        $document_list = $this->documentActionService->getDocumentsForBatchAction(
            self::ID_E_COL,
            self::ID_U_ADMIN,
            'test',
            [$id_d],
            'ok'
        );

        self::assertSame([$id_d], array_column($document_list, 'id_d'));
    }

    /**
     * @throws UnrecoverableException
     * @throws Exception
     */
    public function testExecuteBatch(): void
    {
        $id_d = $this->createDocument('test')['id_d'];

        $message = $this->documentActionService->executeBatch(
            self::ID_E_COL,
            self::ID_U_ADMIN,
            'test',
            [$id_d],
            'ok'
        );

        self::assertSame("L'action « ok » est programmée pour le document « $id_d »<br/>", $message);
        self::assertTrue(
            $this->getObjectInstancier()->getInstance(JobQueueSQL::class)->hasDocumentJob(self::ID_E_COL, $id_d)
        );
    }

    public function testGetAllBatchActions(): void
    {
        $document_list = [
            ['action_possible' => ['ok', 'fail']],
            ['action_possible' => ['ok', 'supression']],
        ];

        self::assertSame(
            ['ok', 'fail', 'supression'],
            array_values($this->documentActionService->getAllPossibleBatchActions($document_list))
        );
    }

    /**
     * @throws UnrecoverableException
     * @throws Exception
     */
    public function testBatchRefusesEmptyList(): void
    {
        $this->expectException(UnrecoverableException::class);
        $this->expectExceptionMessage('Vous devez sélectionner au moins un document');
        $this->documentActionService->executeBatch(self::ID_E_COL, self::ID_U_ADMIN, 'test', [], 'ok');
    }
}
