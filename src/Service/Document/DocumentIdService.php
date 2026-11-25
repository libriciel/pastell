<?php

declare(strict_types=1);

namespace Pastell\Service\Document;

use DocumentSQL;
use Pastell\Utilities\Identifier\IdentifierGeneratorInterface;
use UnrecoverableException;

final class DocumentIdService
{
    public const MAX_ATTEMPTS = 5;

    public function __construct(
        private readonly DocumentSQL $documentSQL,
        private readonly IdentifierGeneratorInterface $identifierGenerator,
    ) {
    }

    /**
     * @throws UnrecoverableException
     */
    public function generate(): string
    {
        for ($i = 0; $i < self::MAX_ATTEMPTS; $i++) {
            $id_d = $this->identifierGenerator->generate();
            if (!$this->documentSQL->exists($id_d)) {
                return $id_d;
            }
        }
        throw new UnrecoverableException('Impossible de trouver un numéro de transaction');
    }
}
