<?php

declare(strict_types=1);

namespace Pastell\Tests\Client\S2low\Normalizer;

use Pastell\Client\S2low\Normalizer\TransactionListDenormalizer;
use Pastell\Client\S2low\Model\ActeListResponse;
use Pastell\Client\S2low\Model\Transaction;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

class TransactionListDenormalizerTest extends TestCase
{
    private DenormalizerInterface&MockObject $innerDenormalizer;
    private TransactionListDenormalizer $denormalizer;

    protected function setUp(): void
    {
        $this->innerDenormalizer = $this->createMock(DenormalizerInterface::class);
        $this->denormalizer = new TransactionListDenormalizer();
        $this->denormalizer->setDenormalizer($this->innerDenormalizer);
    }

    public function testSupportsNormalization(): void
    {
        static::assertTrue($this->denormalizer->supportsDenormalization([], ActeListResponse::class));
        static::assertFalse($this->denormalizer->supportsDenormalization([], \stdClass::class));
    }

    /**
     * @return Transaction[]
     */
    public function getTransactionsObjects(array $transactions): array
    {
        $objects = [];
        foreach ($transactions as $transaction) {
            $transac = new Transaction();
            $transac->id = $transaction['id'];
            $transac->subject = $transaction['subject'];
            $transac->number = $transaction['number'];
            $transac->date = $transaction['date'];
            $transac->natureDescr = $transaction['nature_descr'];
            $transac->classification = $transaction['classification'];
            $transac->type = $transaction['type'];
            $objects[] = $transac;
        }
        return $objects;
    }

    /**
     * @throws ExceptionInterface
     */
    public function testDenormalize(): void
    {
        $data = [
            'status_id' => '1',
            'authority_id' => '1',
            'limit' => '100',
            'offset' => '0',
            'transactions' => [
                [
                    'id' => '1',
                    'subject' => 'test',
                    'number' => '123',
                    'date' => '2024-01-01',
                    'nature_descr' => 'Actes individuels',
                    'classification' => '3.4',
                    'type' => '1',
                ],
                [
                    'id' => '2',
                    'subject' => 'test2',
                    'number' => '321',
                    'date' => '2024-01-02',
                    'nature_descr' => 'Actes individuels',
                    'classification' => '3.4',
                    'type' => '1',
                ],
            ],
        ];

        $transactions = $this->getTransactionsObjects($data['transactions']);

        $this->innerDenormalizer
            ->method('denormalize')
            ->willReturnOnConsecutiveCalls(...\array_values($transactions));

        $transactionsList = $this->denormalizer->denormalize($data, ActeListResponse::class, 'json');

        $expectedTransactionsList = new ActeListResponse();
        $expectedTransactionsList->statusId = $data['status_id'];
        $expectedTransactionsList->authorityId = $data['authority_id'];
        $expectedTransactionsList->limit = $data['limit'];
        $expectedTransactionsList->offset = $data['offset'];
        $expectedTransactionsList->transactions = $transactions;
        self::assertEquals($expectedTransactionsList, $transactionsList);
    }
}
