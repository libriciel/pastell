<?php

declare(strict_types=1);

namespace Pastell\Tests\Service\Document;

use DocumentEntiteSQL;
use DocumentSQL;
use Pastell\Service\Document\DocumentRoleService;
use PastellTestCase;

final class DocumentRoleServiceTest extends PastellTestCase
{
    private const ID_D = 'id-d-test';

    private DocumentRoleService $documentRoleService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->getObjectInstancier()->getInstance(DocumentSQL::class)->save(self::ID_D, 'document-type-test');
        $this->documentRoleService = $this->getObjectInstancier()->getInstance(DocumentRoleService::class);
    }

    public function testAddRole(): void
    {
        $this->documentRoleService->addRole(self::ID_D, self::ID_E_COL, DocumentRoleService::EDITEUR);
        static::assertTrue($this->documentRoleService->hasRole(self::ID_D, self::ID_E_COL, DocumentRoleService::EDITEUR));
        static::assertSame(DocumentRoleService::EDITEUR, $this->documentRoleService->getRole(self::ID_E_COL, self::ID_D));
    }

    public function testAddRoleTwice(): void
    {
        $this->documentRoleService->addRole(self::ID_D, self::ID_E_COL, DocumentRoleService::EDITEUR);
        $this->documentRoleService->addRole(self::ID_D, self::ID_E_COL, DocumentRoleService::EDITEUR);
        static::assertCount(
            1,
            $this->getObjectInstancier()->getInstance(DocumentEntiteSQL::class)->getEntites(self::ID_D)
        );
    }

    public function testEntiteIdWithRole(): void
    {
        static::assertNull($this->documentRoleService->getEntiteIdWithRole(self::ID_D, DocumentRoleService::EDITEUR));
        $this->documentRoleService->addRole(self::ID_D, self::ID_E_SERVICE, DocumentRoleService::EDITEUR);
        static::assertSame(
            self::ID_E_SERVICE,
            $this->documentRoleService->getEntiteIdWithRole(self::ID_D, DocumentRoleService::EDITEUR)
        );
    }
}
