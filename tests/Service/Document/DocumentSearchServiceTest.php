<?php

declare(strict_types=1);

namespace Pastell\Tests\Service\Document;

use DocumentIndexSQL;
use DocumentSQL;
use Pastell\Service\Document\DocumentSearchCriteria;
use Pastell\Service\Document\DocumentSearchService;
use PastellTestCase;

final class DocumentSearchServiceTest extends PastellTestCase
{
    private const TYPE = 'test';

    private DocumentSearchService $documentSearchService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->documentSearchService = $this->getObjectInstancier()->getInstance(DocumentSearchService::class);
    }

    private function createTitledDocument(string $titre): string
    {
        $id_d = $this->createDocument(self::TYPE)['id_d'];
        $this->getObjectInstancier()->getInstance(DocumentSQL::class)->setTitre($id_d, $titre);
        return $id_d;
    }

    private function getCriteria(array $last_actions = [], array $index_values = []): DocumentSearchCriteria
    {
        return new DocumentSearchCriteria(
            allowed_types: [self::TYPE],
            id_e: self::ID_E_COL,
            last_actions: $last_actions,
            index_values: $index_values,
        );
    }

    public function testSearch(): void
    {
        $id_d = $this->createTitledDocument('foo');

        $documents = $this->documentSearchService->search($this->getCriteria(), 0, 10);
        static::assertCount(1, $documents);
        static::assertSame($id_d, $documents[0]['id_d']);
        static::assertSame('creation', $documents[0]['last_action_display']);
        static::assertSame([], $documents[0]['entite']);
        static::assertSame(1, $this->documentSearchService->count($this->getCriteria()));
    }

    public function testSearchWithLastActions(): void
    {
        $this->createTitledDocument('foo');

        static::assertSame(1, $this->documentSearchService->count($this->getCriteria(['creation', 'other'])));
        static::assertSame(0, $this->documentSearchService->count($this->getCriteria(['other'])));
    }

    public function testSearchNotAllowedType(): void
    {
        $this->createTitledDocument('foo');

        static::assertSame(0, $this->documentSearchService->count(new DocumentSearchCriteria(allowed_types: [])));
    }

    public function testSearchSortedByTitre(): void
    {
        $this->createTitledDocument('b');
        $this->createTitledDocument('a');

        $documents = $this->documentSearchService->search($this->getCriteria(), 0, 10, 'titre', 'ASC');
        static::assertSame(['a', 'b'], array_column($documents, 'titre'));
    }

    public function testSearchByIndex(): void
    {
        $id_d = $this->createTitledDocument('foo');
        $this->createTitledDocument('bar');
        $this->getObjectInstancier()->getInstance(DocumentIndexSQL::class)->index($id_d, 'numero', '42');

        $documents = $this->documentSearchService->search($this->getCriteria([], ['numero' => '4']), 0, 10, 'numero');
        static::assertCount(1, $documents);
        static::assertSame($id_d, $documents[0]['id_d']);
    }

    public function testGetOffset(): void
    {
        $id_d = $this->createTitledDocument('foo');
        $this->createTitledDocument('bar');
        $this->getSQLQuery()->query(
            'UPDATE document_entite SET last_action_date = ? WHERE id_d = ?',
            '2000-01-01 00:00:00',
            $id_d
        );

        static::assertSame(0, $this->documentSearchService->getOffset($id_d, self::ID_E_COL, self::TYPE, 2));
        static::assertSame(1, $this->documentSearchService->getOffset($id_d, self::ID_E_COL, self::TYPE, 1));
        static::assertSame(0, $this->documentSearchService->getOffset('unknown', self::ID_E_COL, self::TYPE, 1));
    }
}
