<?php

declare(strict_types=1);

namespace Pastell\Client\S2low\Normalizer;

use Pastell\Client\S2low\Model\ActeListResponse;
use Pastell\Client\S2low\Model\Transaction;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

final class TransactionListDenormalizer implements DenormalizerInterface, DenormalizerAwareInterface
{
    use DenormalizerAwareTrait;

    public function denormalize(
        mixed $data,
        string $type,
        ?string $format = null,
        array $context = [],
    ): ActeListResponse {
        $transactions = array_map(
            function (array $transaction) {
                return $this->denormalizer->denormalize($transaction, Transaction::class);
            },
            $data['transactions']
        );
        $acteListResponse = new ActeListResponse();
        $acteListResponse->statusId = $data['status_id'];
        $acteListResponse->authorityId = $data['authority_id'];
        $acteListResponse->limit = $data['limit'];
        $acteListResponse->offset = $data['offset'];
        $acteListResponse->transactions = $transactions;
        return $acteListResponse;
    }

    public function supportsDenormalization(mixed $data, string $type, ?string $format = null): bool
    {
        return $type === ActeListResponse::class;
    }
}
