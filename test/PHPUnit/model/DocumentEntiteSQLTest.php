<?php

declare(strict_types=1);

class DocumentEntiteSQLTest extends PastellTestCase
{
    private const TYPE = 'document-type-test';

    private DocumentEntiteSQL $documentEntiteSQL;
    private string $id_d;

    /**
     * @throws UnrecoverableException
     */
    protected function setUp(): void
    {
        parent::setUp();
        $documentSQL = $this->getObjectInstancier()->getInstance(DocumentSQL::class);
        $this->id_d = $documentSQL->getNewId();
        $documentSQL->save($this->id_d, self::TYPE);
        $this->documentEntiteSQL = new DocumentEntiteSQL(self::getSQLQuery());
        $this->documentEntiteSQL->insert($this->id_d, self::ID_E_COL, 'editeur');
        $this->getObjectInstancier()->getInstance(DocumentActionSQL::class)
            ->add($this->id_d, self::ID_E_COL, 1, 'action-test');
    }

    public function testInsertSetsLastType(): void
    {
        static::assertSame(self::TYPE, $this->documentEntiteSQL->getEntites($this->id_d)[0]['last_type']);
    }

    public function testCountRole(): void
    {
        static::assertSame(1, $this->documentEntiteSQL->countRole($this->id_d, self::ID_E_COL, 'editeur'));
        static::assertSame(0, $this->documentEntiteSQL->countRole($this->id_d, self::ID_E_COL, 'lecteur'));
    }

    public function testGetRole(): void
    {
        static::assertSame('editeur', $this->documentEntiteSQL->getRole(self::ID_E_COL, $this->id_d));
        static::assertNull($this->documentEntiteSQL->getRole(self::ID_E_SERVICE, $this->id_d));
    }

    public function testEntiteIdWithRole(): void
    {
        static::assertSame(self::ID_E_COL, $this->documentEntiteSQL->getEntiteIdWithRole($this->id_d, 'editeur'));
        static::assertNull($this->documentEntiteSQL->getEntiteIdWithRole($this->id_d, 'lecteur'));
    }

    public function testGetEntiteId(): void
    {
        static::assertSame(self::ID_E_COL, $this->documentEntiteSQL->getEntiteId($this->id_d));
        static::assertNull($this->documentEntiteSQL->getEntiteId('unknown'));
    }

    public function testGetDocumentsByType(): void
    {
        $documents = $this->documentEntiteSQL->getDocumentsByType(self::ID_E_COL, self::TYPE);
        static::assertSame($this->id_d, $documents[0]['id_d']);
    }

    public function testCountDocuments(): void
    {
        static::assertSame(1, $this->documentEntiteSQL->countDocuments(self::ID_E_COL));
        static::assertSame(1, $this->documentEntiteSQL->countDocumentsByType(self::ID_E_COL, self::TYPE));
        static::assertSame(0, $this->documentEntiteSQL->countDocumentsByType(self::ID_E_COL, 'other'));
    }

    public function testCountByLastAction(): void
    {
        $count = $this->documentEntiteSQL->countByLastAction(self::ID_E_COL, self::TYPE);
        static::assertCount(1, $count);
        static::assertSame('action-test', $count[0]['last_action']);
        static::assertEmpty($this->documentEntiteSQL->countByLastAction(self::ID_E_SERVICE, null));
    }

    public function testCountByIndexFilters(): void
    {
        $documentIndexSQL = $this->getObjectInstancier()->getInstance(DocumentIndexSQL::class);
        $documentIndexSQL->index($this->id_d, 'objet', 'Arrêté 100%');
        $documentIndexSQL->index($this->id_d, 'numero', '42');

        static::assertSame(1, $this->documentEntiteSQL->countByIndexFilters(self::ID_E_COL, self::TYPE, []));
        static::assertSame(
            1,
            $this->documentEntiteSQL->countByIndexFilters(self::ID_E_COL, self::TYPE, ['objet' => '100%', 'numero' => '4'])
        );
        static::assertSame(
            0,
            $this->documentEntiteSQL->countByIndexFilters(self::ID_E_COL, self::TYPE, ['objet' => '1000'])
        );
    }

    public function testLastActionUntil(): void
    {
        $documents = $this->documentEntiteSQL->getDocumentsWithLastActionUntil(date('Y-m-d'), self::TYPE, null);
        static::assertSame($this->id_d, $documents[0]['id_d']);
        static::assertEmpty($this->documentEntiteSQL->getDocumentsWithLastActionUntil('2000-01-01', null, null));
    }

    public function testUpdateLastAction(): void
    {
        $this->documentEntiteSQL->updateLastAction(self::ID_E_COL, $this->id_d, 'action-to', '2020-01-01 00:00:00');
        $document = $this->documentEntiteSQL->getDocuments(self::ID_E_COL)[0];
        static::assertSame('action-to', $document['last_action']);
        static::assertSame('2020-01-01 00:00:00', $document['last_action_date']);
    }
}
