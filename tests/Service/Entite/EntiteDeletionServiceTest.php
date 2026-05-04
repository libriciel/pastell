<?php

namespace Pastell\Tests\Service\Entite;

use AnnuaireGroupe;
use AnnuaireRoleSQL;
use AnnuaireSQL;
use ConnecteurEntiteSQL;
use DocumentEntite;
use DocumentSQL;
use EntiteSQL;
use FluxEntiteHeritageSQL;
use FluxEntiteSQL;
use Notification;
use Pastell\Service\Entite\EntiteDeletionService;
use PastellTestCase;
use RoleUtilisateur;
use SQLQuery;
use UnrecoverableException;
use UtilisateurSQL;

class EntiteDeletionServiceTest extends PastellTestCase
{
    private int $entityId = 2;
    private EntiteDeletionService $entiteDeletionService;

    public function setUp(): void
    {
        parent::setUp();
        $this->entiteDeletionService = $this->getObjectInstancier()->getInstance(EntiteDeletionService::class);
    }

    /**
     * @throws UnrecoverableException
     */
    public function testDelete(): void
    {
        $entiteSQL = $this->getObjectInstancier()->getInstance(EntiteSQL::class);
        static::assertTrue($entiteSQL->exists(2));

        $entiteDeletionService = $this->getObjectInstancier()->getInstance(EntiteDeletionService::class);
        $entiteDeletionService->delete(2);
        $journal_message = $this->getJournal()->getAll()[0]['message'];
        $expected_journal_message = file_get_contents(
            __DIR__ . '/../fixtures/entite_delete_service_journal_message.txt'
        );
        static::assertSame(
            $expected_journal_message,
            $journal_message
        );
        static::assertFalse($entiteSQL->exists(2));
        $log_message = $this->getLogRecords()[0]['message'];
        static::assertMatchesRegularExpression(
            "#^Ajout au journal \(id_j=1\): 3 - 2 - 1 - 0 - Supprimé - $expected_journal_message#",
            $log_message
        );
    }

    public function testCanDelete(): void
    {
        $isSupprimable = $this->entiteDeletionService->canDelete($this->entityId);
        self::assertTrue($isSupprimable);
    }

    public function testCannotDeleteWhenHasChildEntity(): void
    {
        $this->getObjectInstancier()->getInstance(EntiteSQL::class)->create(
            'name-fake',
            'siren-fake',
            EntiteSQL::TYPE_COLLECTIVITE,
            $this->entityId
        );

        $isSupprimable = $this->entiteDeletionService->canDelete($this->entityId);
        self::assertFalse($isSupprimable);
    }

    public function testCannotDeleteWhenHasUsersWithBaseEntity(): void
    {
        $this->getObjectInstancier()->getInstance(UtilisateurSQL::class)->query(
            'UPDATE utilisateur SET id_e = ? WHERE id_u = 1',
            $this->entityId
        );

        $isSupprimable = $this->entiteDeletionService->canDelete($this->entityId);
        self::assertFalse($isSupprimable);
    }

    public function testCannotDeleteWhenHasDocumentEntity(): void
    {
        $id_d = 'IDENTIFIANT-FAKE';
        $this->getObjectInstancier()->getInstance(DocumentSQL::class)->save($id_d, 'type');
        $this->getObjectInstancier()->getInstance(DocumentEntite::class)->addRole(
            $id_d,
            $this->entityId,
            'ROLE-FAKE'
        );
        $isSupprimable = $this->entiteDeletionService->canDelete($this->entityId);
        self::assertFalse($isSupprimable);
    }

    public function testCannotDeleteWhenHasUser(): void
    {
        $this->getObjectInstancier()->getInstance(RoleUtilisateur::class)->addRole(
            1,
            'role-fake',
            $this->entityId
        );
        $isSupprimable = $this->entiteDeletionService->canDelete($this->entityId);
        self::assertFalse($isSupprimable);
    }

    public function testCannotDeleteWhenHasConnector(): void
    {
        $this->getObjectInstancier()->getInstance(ConnecteurEntiteSQL::class)->addConnecteur(
            $this->entityId,
            4,
            'type-fake',
            'libelle-fake',
            0
        );
        $isSupprimable = $this->entiteDeletionService->canDelete($this->entityId);
        self::assertFalse($isSupprimable);
    }

    public function testCannotDeleteWhenHasFluxEntity(): void
    {
        $this->getObjectInstancier()->getInstance(FluxEntiteSQL::class)->addConnecteur(
            $this->entityId,
            'flux-fake',
            'type-fake',
            4
        );

        $isSupprimable = $this->entiteDeletionService->canDelete($this->entityId);
        self::assertFalse($isSupprimable);
    }

    public function testCannotDeleteWhenHasFluxEntityInheritance(): void
    {
        $this->getObjectInstancier()->getInstance(FluxEntiteHeritageSQL::class)->setInheritance(
            $this->entityId,
            'flux-fake'
        );
        $isSupprimable = $this->entiteDeletionService->canDelete($this->entityId);
        self::assertFalse($isSupprimable);
    }

    public function testCannotDeleteWhenHasDirectoryContacts(): void
    {
        $this->getObjectInstancier()->getInstance(AnnuaireSQL::class)->add(
            $this->entityId,
            'Contact Test',
            'contact@test.fr'
        );

        $isSupprimable = $this->entiteDeletionService->canDelete($this->entityId);
        self::assertFalse($isSupprimable);
    }

    public function testCannotDeleteWhenHasDirectoryGroup(): void
    {
        $annuaireGroupe = new AnnuaireGroupe(
            $this->getObjectInstancier()->getInstance(SQLQuery::class),
            $this->entityId
        );
        $annuaireGroupe->add('Groupe Test');

        $isSupprimable = $this->entiteDeletionService->canDelete($this->entityId);
        self::assertFalse($isSupprimable);
    }

    /**
     * @throws UnrecoverableException
     */
    public function testDeleteRemovesDirectoryRoles(): void
    {
        $annuaireRoleSQL = $this->getObjectInstancier()->getInstance(AnnuaireRoleSQL::class);
        $annuaireRoleSQL->add('Rôle Test', $this->entityId, $this->entityId, 'role-fake');

        $this->entiteDeletionService->delete($this->entityId);

        self::assertEmpty($annuaireRoleSQL->getAll($this->entityId));
    }

    /**
     * @throws UnrecoverableException
     */
    public function testDeleteRemovesNotifications(): void
    {
        $notification = $this->getObjectInstancier()->getInstance(Notification::class);
        $notification->add(1, $this->entityId, 'type-fake', 'action-fake', 0);
        self::assertNotEmpty($notification->getAll(1));

        $this->entiteDeletionService->delete($this->entityId);

        self::assertEmpty($notification->getAll(1));
    }
}
