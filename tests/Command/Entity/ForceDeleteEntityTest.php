<?php

declare(strict_types=1);

namespace Pastell\Tests\Command\Entity;

use ConflictException;
use ConnecteurEntiteSQL;
use ConnecteurException;
use EntiteSQL;
use Pastell\Command\Entity\ForceDeleteEntity;
use Pastell\Service\Connecteur\ConnecteurCreationService;
use Pastell\Service\Entite\EntityCreationService;
use Pastell\Service\Utilisateur\UserCreationService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use UnrecoverableException;
use UtilisateurListe;
use PastellTestCase;

final class ForceDeleteEntityTest extends PastellTestCase
{
    private CommandTester $tester;
    private EntiteSQL $entiteSQL;
    private ConnecteurEntiteSQL $connecteurEntiteSQL;
    private UtilisateurListe $utilisateurListe;
    private EntityCreationService $entityCreationService;
    private ConnecteurCreationService $connecteurCreationService;
    private UserCreationService $userCreationService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->entiteSQL = $this->getObjectInstancier()->getInstance(EntiteSQL::class);
        $this->connecteurEntiteSQL = $this->getObjectInstancier()->getInstance(ConnecteurEntiteSQL::class);
        $this->utilisateurListe = $this->getObjectInstancier()->getInstance(UtilisateurListe::class);
        $this->entityCreationService = $this->getObjectInstancier()->getInstance(EntityCreationService::class);
        $this->connecteurCreationService = $this->getObjectInstancier()->getInstance(ConnecteurCreationService::class);
        $this->userCreationService = $this->getObjectInstancier()->getInstance(UserCreationService::class);

        $command = $this->getObjectInstancier()->getInstance(ForceDeleteEntity::class);

        $this->tester = new CommandTester($command);
    }

    private function exec(string $id_e, bool $do): int
    {
        $inputs = [
            ForceDeleteEntity::class => [],
        ];

        return $this->tester->execute(
            [
                'id_e' => $id_e,
                '--do' => $do,
            ],
            $inputs
        );
    }

    public function testFailsOnUnknownEntity(): void
    {
        $unknown_id = '99999999';
        self::assertFalse((bool)$this->entiteSQL->getInfo($unknown_id));
        $status = $this->exec($unknown_id, true);
        self::assertSame(Command::FAILURE, $status);
        self::assertStringContainsString("L'entité {$unknown_id} n'existe pas", $this->tester->getDisplay());
    }

    /**
     * @throws UnrecoverableException
     * @throws ConnecteurException
     * @throws ConflictException
     */
    public function testDryRunDoesNotDelete(): void
    {
        $id_e = $this->entityCreationService->create('test', '',);
        $id_ce = $this->connecteurCreationService->createConnecteur('test-conn', '', 0, $id_e);
        $id_u = $this->userCreationService->create('test-user', 'test@libriciel.invalid', 'Test', 'User', $id_e);

        $connsBefore = $this->connecteurEntiteSQL->getAll($id_e);
        $usersBefore = $this->utilisateurListe->getAllUtilisateurSimple($id_e);
        self::assertContains($id_ce, array_column($connsBefore, 'id_ce'));
        self::assertContains($id_u, array_column($usersBefore, 'id_u'));

        $status = $this->exec((string)$id_e, false);

        self::assertSame(Command::SUCCESS, $status);

        $connsAfter = $this->connecteurEntiteSQL->getAll($id_e);
        $usersAfter = $this->utilisateurListe->getAllUtilisateurSimple($id_e);
        self::assertContains($id_ce, array_column($connsAfter, 'id_ce'));
        self::assertContains($id_u, array_column($usersAfter, 'id_u'));

        $out = $this->tester->getDisplay();
        self::assertStringContainsString('Suppression d’entité (dry-run: oui)', $out);
    }

    /**
     * @throws UnrecoverableException
     * @throws ConnecteurException
     * @throws ConflictException
     */
    public function testDeleteEntity(): void
    {
        $id_e = $this->entityCreationService->create('test', '',);
        $child_id = $this->entityCreationService->create('test-child', '', EntiteSQL::TYPE_COLLECTIVITE, $id_e);
        self::assertNotFalse($this->entiteSQL->getInfo((string)$child_id));

        $id_ce = $this->connecteurCreationService->createConnecteur('test-conn', '', 0, $child_id);
        $id_u = $this->userCreationService->create('test-user', 'test@libriciel.invalid', 'Test', 'User', $child_id);
        $connsBefore = $this->connecteurEntiteSQL->getAll($child_id);
        $usersBefore = $this->utilisateurListe->getAllUtilisateurSimple($child_id);
        self::assertContains($id_ce, array_column($connsBefore, 'id_ce'));
        self::assertContains($id_u, array_column($usersBefore, 'id_u'));

        $status = $this->exec((string)$child_id, true);
        self::assertSame(Command::SUCCESS, $status);

        self::assertFalse((bool)$this->entiteSQL->getInfo((string)$child_id));
        $connsAfter = $this->connecteurEntiteSQL->getAll($child_id);
        self::assertNotContains($id_ce, array_column($connsAfter, 'id_ce'));
        $usersAfter = $this->utilisateurListe->getAllUtilisateurSimple($child_id);
        self::assertNotContains($id_u, array_column($usersAfter, 'id_u'));
        self::assertStringContainsString('Suppressions effectuées.', $this->tester->getDisplay());
    }
}
