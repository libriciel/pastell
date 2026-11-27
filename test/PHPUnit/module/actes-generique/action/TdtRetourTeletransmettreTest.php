<?php

declare(strict_types=1);

final class TdtRetourTeletransmettreTest extends PastellTestCase
{
    private const TYPE = 'actes-generique';
    private const ACTION = 'return-teletransmission-tdt';

    /**
     * @throws Exception
     */
    public function testGoLot(): void
    {
        $this->associateFakeTdt();
        $firstDocument = $this->createDocument(self::TYPE);
        $secondDocument = $this->createDocument(self::TYPE);

        $actionExecutorFactory = $this->getObjectInstancier()->getInstance(ActionExecutorFactory::class);
        $actionExecutorFactory->executeLotDocument(
            self::ID_E_COL,
            self::ID_U_ADMIN,
            [$firstDocument['id_d'], $secondDocument['id_d']],
            self::ACTION
        );

        self::assertSame(
            "Le document « {$firstDocument['titre']} » a été télétransmis<br/>"
            . "Le document « {$secondDocument['titre']} » a été télétransmis<br/>",
            $actionExecutorFactory->getLastMessage()
        );
        $jobQueueSQL = $this->getObjectInstancier()->getInstance(JobQueueSQL::class);
        foreach ([$firstDocument['id_d'], $secondDocument['id_d']] as $id_d) {
            $this->assertLastDocumentAction('send-tdt', $id_d);
            self::assertTrue($jobQueueSQL->hasDocumentJob(self::ID_E_COL, $id_d));
        }
    }

    /**
     * @throws Exception
     */
    public function testGoLotWaitingTransaction(): void
    {
        $this->associateFakeTdt(TdtConnecteur::STATUS_ACTES_EN_ATTENTE_DE_POSTER);
        $document = $this->createDocument(self::TYPE);

        $actionExecutorFactory = $this->getObjectInstancier()->getInstance(ActionExecutorFactory::class);
        $actionExecutorFactory->executeLotDocument(self::ID_E_COL, self::ID_U_ADMIN, [$document['id_d']], self::ACTION);

        self::assertSame(
            "La transaction pour le document « {$document['titre']} » n'a pas le bon status : "
            . "En attente d'être postée trouvé<br/>",
            $actionExecutorFactory->getLastMessage()
        );
        $this->assertLastDocumentAction('creation', $document['id_d']);
    }

    private function associateFakeTdt(?int $status = null): void
    {
        $connector = $this->createConnector('fakeTdt', 'Fake TdT');
        if ($status !== null) {
            $this->configureConnector($connector['id_ce'], ['tdt_check_status' => $status]);
        }
        $this->associateFluxWithConnector($connector['id_ce'], self::TYPE, 'TdT');
    }
}
