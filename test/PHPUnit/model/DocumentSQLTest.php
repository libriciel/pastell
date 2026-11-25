<?php

class DocumentSQLTest extends PastellTestCase
{
    /**
     * @var DocumentSQL
     */
    private $documentSql;

    protected function setUp(): void
    {
        $this->documentSql = $this->getObjectInstancier()->getInstance(DocumentSQL::class);
        parent::setUp();
    }

    public function testGetDocumentsLastActionByTypeEntityAndCreationDate(): void
    {
        $doc = $this->createDocument('test');

        $documents = $this->documentSql->getDocumentsLastActionByTypeEntityAndCreationDate(
            self::ID_E_COL,
            'test',
            date('Y-m-d'),
            date('Y-m-d', strtotime('+1 day'))
        );

        $this->assertSame($doc['id_d'], $documents[0]['id_d']);
    }

    public function testExists(): void
    {
        $id_d = $this->createDocument('test')['id_d'];

        self::assertTrue($this->documentSql->exists($id_d));
        self::assertFalse($this->documentSql->exists('unknown'));
    }

    public function testSetTitre(): void
    {
        $id_d = $this->createDocument('test')['id_d'];
        $this->documentSql->getInfo($id_d);

        $this->documentSql->setTitre($id_d, 'Mon titre');

        self::assertSame('Mon titre', $this->documentSql->getInfo($id_d)['titre']);
    }

    public function testDelete(): void
    {
        $id_d = $this->createDocument('test')['id_d'];

        $this->documentSql->delete($id_d);

        self::assertFalse($this->documentSql->exists($id_d));
        self::assertSame(
            [],
            $this->getObjectInstancier()->getInstance(DocumentEntiteSQL::class)->getEntites($id_d)
        );
    }

    public function testGetAllByType(): void
    {
        $id_d = $this->createDocument('test')['id_d'];

        self::assertSame([$id_d], array_column($this->documentSql->getAllByType('test'), 'id_d'));
        self::assertSame(
            [['id_d' => $id_d, 'id_e' => self::ID_E_COL]],
            $this->documentSql->getAllIdByType('test')
        );
    }

    public function testFixModule(): void
    {
        $id_d = $this->createDocument('test')['id_d'];

        $this->documentSql->fixModule('test', 'actes-generique');

        self::assertSame('actes-generique', $this->documentSql->getInfo($id_d)['type']);
        self::assertSame(['actes-generique'], $this->documentSql->getAllType());
        self::assertFalse($this->documentSql->isTypePresent('test'));
        self::assertSame($id_d, $this->documentSql->isTypePresent('actes-generique')['id_d']);
    }

    public function testGetEntiteWhichUsedDocument(): void
    {
        $this->createDocument('test');
        $this->createDocument('test');

        self::assertSame(
            [['id_e' => self::ID_E_COL, 'denomination' => 'Bourg-en-Bresse', 'nb_documents' => 2]],
            $this->documentSql->getEntiteWhichUsedDocument('test')
        );
    }
}
