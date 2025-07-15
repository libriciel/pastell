<?php

namespace Pastell\Tests\Service\Entite;

use ConnecteurEntiteSQL;
use DocumentEntite;
use DocumentSQL;
use EntiteSQL;
use FluxEntiteHeritageSQL;
use FluxEntiteSQL;
use Pastell\Service\Entite\EntiteDeletionService;
use PastellTestCase;
use RoleUtilisateur;
use UnrecoverableException;
use UtilisateurSQL;

class EntiteDeletionServiceTest extends PastellTestCase
{
    private int $id_entity_exemple = 2;
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

    public function testIsSupprimable(): void
    {
        $isSupprimable = $this->entiteDeletionService->canDelete($this->id_entity_exemple);
        self::assertTrue($isSupprimable);
    }

    public function testIsNotSupprimableBecauseHasEntiteFille(): void
    {
        self::getObjectInstancier()->getInstance(EntiteSQL::class)->create(
            'name-fake',
            'siren-fake',
            EntiteSQL::TYPE_COLLECTIVITE,
            $this->id_entity_exemple
        );

        $isSupprimable = $this->entiteDeletionService->canDelete($this->id_entity_exemple);
        self::assertFalse($isSupprimable);
    }

    public function testIsNotSupprimableBecauseHasUsersWithEntiteDeBase(): void
    {
        self::getObjectInstancier()->getInstance(UtilisateurSQL::class)->query(
            'UPDATE utilisateur SET id_e = ? WHERE id_u = 1',
            $this->id_entity_exemple
        );

        $isSupprimable = $this->entiteDeletionService->canDelete($this->id_entity_exemple);
        self::assertFalse($isSupprimable);
    }

    public function testIsNotSupprimableBecauseHasDocumentEntity(): void
    {
        $id_d = 'IDENTIFIANT-FAKE';
        self::getObjectInstancier()->getInstance(DocumentSQL::class)->save($id_d, 'type');
        self::getObjectInstancier()->getInstance(DocumentEntite::class)->addRole(
            $id_d,
            $this->id_entity_exemple,
            'ROLE-FAKE'
        );

        $isSupprimable = $this->entiteDeletionService->canDelete($this->id_entity_exemple);
        self::assertFalse($isSupprimable);
    }

    public function testIsNotSupprimableBecauseHasUser(): void
    {
        self::getObjectInstancier()->getInstance(RoleUtilisateur::class)->addRole(
            1,
            'role-fake',
            $this->id_entity_exemple
        );

        $isSupprimable = $this->entiteDeletionService->canDelete($this->id_entity_exemple);
        self::assertFalse($isSupprimable);
    }

    public function testIsNotSupprimableBecauseHasConnector(): void
    {
        self::getObjectInstancier()->getInstance(ConnecteurEntiteSQL::class)->addConnecteur(
            $this->id_entity_exemple,
            4,
            'type-fake',
            'libelle-fake',
            0
        );

        $isSupprimable = $this->entiteDeletionService->canDelete($this->id_entity_exemple);
        self::assertFalse($isSupprimable);
    }

    public function testIsNotSupprimableBecauseHasfluxEntity(): void
    {
        self::getObjectInstancier()->getInstance(FluxEntiteSQL::class)->addConnecteur(
            $this->id_entity_exemple,
            'flux-fake',
            'type-fake',
            4
        );

        $isSupprimable = $this->entiteDeletionService->canDelete($this->id_entity_exemple);
        self::assertFalse($isSupprimable);
    }

    public function testIsNotSupprimableBecauseHasfluxEntityHeritage(): void
    {
        self::getObjectInstancier()->getInstance(FluxEntiteHeritageSQL::class)->setInheritance(
            $this->id_entity_exemple,
            'flux-fake'
        );

        $isSupprimable = $this->entiteDeletionService->canDelete($this->id_entity_exemple);
        self::assertFalse($isSupprimable);
    }
}
