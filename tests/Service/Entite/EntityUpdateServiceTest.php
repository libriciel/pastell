<?php

declare(strict_types=1);

namespace Pastell\Tests\Service\Entite;

use EntiteSQL;
use Pastell\Service\Journal\JournalEntryService;
use Pastell\Service\Entite\EntityUpdateService;
use Pastell\Validator\EntityValidator;
use PastellTestCase;
use UnrecoverableException;

class EntityUpdateServiceTest extends PastellTestCase
{
    private function entityUpdateService(): EntityUpdateService
    {
        return new EntityUpdateService(
            $this->getObjectInstancier()->getInstance(EntiteSQL::class),
            $this->getObjectInstancier()->getInstance(JournalEntryService::class),
            $this->getObjectInstancier()->getInstance(EntityValidator::class),
        );
    }

    /**
     * @throws UnrecoverableException
     */
    public function testUpdate(): void
    {
        $newName = 'New name';
        $this->entityUpdateService()->update(self::ID_E_COL, $newName, '');

        static::assertSame(
            $newName,
            $this->getObjectInstancier()->getInstance(EntiteSQL::class)->getDenomination(self::ID_E_COL)
        );
    }
}
