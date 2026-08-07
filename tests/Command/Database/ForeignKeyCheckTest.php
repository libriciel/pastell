<?php

declare(strict_types=1);

namespace Pastell\Tests\Command\Database;

use Exception;
use Pastell\Command\Database\ForeignKeyCheck;
use PastellTestCase;
use SQLQuery;
use Symfony\Component\Console\Tester\CommandTester;

class ForeignKeyCheckTest extends PastellTestCase
{
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        parent::setUp();
        $command = new ForeignKeyCheck(
            $this->getObjectInstancier()->getInstance(SQLQuery::class)
        );
        $this->commandTester = new CommandTester($command);
    }

    /**
     * @throws Exception
     */
    private function insertOrphanUtilisateur(int $id_u = 3): void
    {
        static::getSQLQuery()->query(
            "INSERT INTO utilisateur
                (id_u, email, login, password, mail_verif_password, date_inscription, mail_verifie, nom, prenom, id_e)
             VALUES (?, ?, ?, 'password', '', '0000-00-00 00:00:00', 1, 'Orphan', 'User', 999)",
            [$id_u, "orphan$id_u@libriciel.invalid", "orphan$id_u"]
        );
    }

    /**
     * @throws Exception
     */
    private function insertUtilisateurSansEntite(): void
    {
        static::getSQLQuery()->query(
            "INSERT INTO utilisateur
                (id_u, email, login, password, mail_verif_password, date_inscription, mail_verifie, nom, prenom, id_e)
             VALUES (3, 'noentite@libriciel.invalid', 'noentite', 'password', '', '0000-00-00 00:00:00', 1, 'No', 'Entite', 0)"
        );
    }

    /**
     * @throws Exception
     */
    private function insertOrphanEntite(): void
    {
        static::getSQLQuery()->query(
            "INSERT INTO entite (id_e, type, denomination, siren, date_inscription, entite_mere)
             VALUES (3, 'collectivite', 'Orpheline', '222222226', '0000-00-00 00:00:00', 999)"
        );
    }

    /**
     * @throws Exception
     */
    private function insertOrphanUtilisateurRole(): void
    {
        static::getSQLQuery()->query(
            "INSERT INTO utilisateur_role (id_u, role, id_e) VALUES (1, 'inexistant', 0)"
        );
    }

    /**
     * @throws Exception
     */
    private function insertOrphanConnecteurFrequence(): void
    {
        static::getSQLQuery()->query(
            "INSERT INTO connecteur_frequence
                (id_cf, type_connecteur, famille_connecteur, id_connecteur, id_ce, action_type, type_document, action, expression, id_verrou)
             VALUES (2, 'entite', '', 'i-parapheur', 42, 'document', 'actes-generique', 'verif-tdt', '30', '')"
        );
    }

    private function countOrphanUtilisateur(int $id_u = 3): int
    {
        return (int) static::getSQLQuery()->queryOne('SELECT COUNT(*) FROM utilisateur WHERE id_u = ?', [$id_u]);
    }

    public function testNoOrphan(): void
    {
        static::assertSame(0, $this->commandTester->execute([]));
        static::assertStringContainsString('Aucune ligne orpheline trouvée', $this->commandTester->getDisplay());
    }

    public function testFindsAnOrphanRow(): void
    {
        $this->insertOrphanUtilisateur();

        static::assertSame(1, $this->commandTester->execute([], ['interactive' => false]));

        $output = $this->commandTester->getDisplay();
        static::assertStringContainsString('utilisateur', $output);
        static::assertStringContainsString('id_e', $output);
        static::assertStringContainsString('entite', $output);
        static::assertStringContainsString('999', $output);
    }

    /**
     * @throws Exception
     */
    public function testIgnoresEmptyValues(): void
    {
        $this->insertUtilisateurSansEntite();

        static::assertSame(0, $this->commandTester->execute([]));
        static::assertStringContainsString('Aucune ligne orpheline trouvée', $this->commandTester->getDisplay());
    }

    /**
     * @throws Exception
     */
    public function testGroupsSeveralSameOrphan(): void
    {
        $this->insertOrphanUtilisateur();
        $this->insertOrphanUtilisateur(4);

        static::assertSame(1, $this->commandTester->execute([], ['interactive' => false]));
        static::assertStringContainsString('2 ligne(s) orpheline(s) trouvée(s)', $this->commandTester->getDisplay());
    }

    /**
     * @throws Exception
     */
    public function testFindsAnOrphanOnSelfReferencingRelation(): void
    {
        $this->insertOrphanEntite();

        static::assertSame(1, $this->commandTester->execute([], ['interactive' => false]));

        $output = $this->commandTester->getDisplay();
        static::assertStringContainsString('entite_mere', $output);
        static::assertStringContainsString('999', $output);
    }

    /**
     * @throws Exception
     */
    public function testFindsAnOrphanOnRoleRelation(): void
    {
        $this->insertOrphanUtilisateurRole();

        static::assertSame(1, $this->commandTester->execute([], ['interactive' => false]));

        $output = $this->commandTester->getDisplay();
        static::assertStringContainsString('utilisateur_role', $output);
        static::assertStringContainsString('inexistant', $output);
    }

    /**
     * @throws Exception
     */
    public function testFindsAnOrphanOnFrequenceRelation(): void
    {
        $this->insertOrphanConnecteurFrequence();

        static::assertSame(1, $this->commandTester->execute([], ['interactive' => false]));

        $output = $this->commandTester->getDisplay();
        static::assertStringContainsString('connecteur_frequence', $output);
        static::assertStringContainsString('42', $output);
    }

    /**
     * @throws Exception
     */
    public function testAsksForConfirmationBeforeDeleting(): void
    {
        $this->insertOrphanUtilisateur();

        static::assertSame(1, $this->commandTester->execute([], ['interactive' => false]));

        $output = $this->commandTester->getDisplay();
        static::assertStringContainsString('Abandon', $output);
        static::assertSame(1, $this->countOrphanUtilisateur());
    }

    /**
     * @throws Exception
     */
    public function testConfirmDeletes(): void
    {
        $this->insertOrphanUtilisateur();

        $this->commandTester->setInputs(['yes']);
        static::assertSame(0, $this->commandTester->execute([]));

        static::assertStringContainsString('supprimée', $this->commandTester->getDisplay());
        static::assertSame(0, $this->countOrphanUtilisateur());
    }

    /**
     * @throws Exception
     */
    public function testDeclineDoesNotDelete(): void
    {
        $this->insertOrphanUtilisateur();

        $this->commandTester->setInputs(['no']);
        static::assertSame(1, $this->commandTester->execute([]));

        static::assertSame(1, $this->countOrphanUtilisateur());
    }

    /**
     * @throws Exception
     */
    public function testForceSkipsConfirmation(): void
    {
        $this->insertOrphanUtilisateur();

        static::assertSame(0, $this->commandTester->execute(['--force' => true], ['interactive' => false]));

        static::assertStringContainsString('supprimée', $this->commandTester->getDisplay());
        static::assertSame(0, $this->countOrphanUtilisateur());
    }

    /**
     * @throws Exception
     */
    public function testDryRunDoesNotDelete(): void
    {
        $this->insertOrphanUtilisateur();

        static::assertSame(0, $this->commandTester->execute(['--dry-run' => true], ['interactive' => false]));

        static::assertStringContainsString('Dry run', $this->commandTester->getDisplay());
        static::assertSame(1, $this->countOrphanUtilisateur());
    }
}
