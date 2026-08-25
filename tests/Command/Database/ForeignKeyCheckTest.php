<?php

declare(strict_types=1);

namespace Pastell\Tests\Command\Database;

use EntiteSQL;
use Exception;
use Pastell\Command\Database\ForeignKeyCheck;
use PastellLogger;
use PastellTestCase;
use RoleUtilisateur;
use SQLQuery;
use Symfony\Component\Console\Tester\CommandTester;

class ForeignKeyCheckTest extends PastellTestCase
{
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        parent::setUp();
        $command = new ForeignKeyCheck(
            $this->getObjectInstancier()->getInstance(SQLQuery::class),
            $this->getObjectInstancier()->getInstance(EntiteSQL::class),
            $this->getObjectInstancier()->getInstance(PastellLogger::class)
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
    private function insertOrphanEntiteWithChild(): void
    {
        static::getSQLQuery()->query(
            "INSERT INTO entite (id_e, type, denomination, siren, date_inscription, entite_mere)
             VALUES (3, 'collectivite', 'Orpheline', '222222226', '0000-00-00 00:00:00', 999)"
        );
        static::getSQLQuery()->query(
            "INSERT INTO entite (id_e, type, denomination, siren, date_inscription, entite_mere)
             VALUES (4, 'collectivite', 'Enfant', '222222227', '0000-00-00 00:00:00', 3)"
        );
    }

    /**
     * @throws Exception
     */
    private function insertOrphanJournal(): void
    {
        static::getSQLQuery()->query(
            "INSERT INTO journal (id_j, type, id_e, id_u, id_d, action, message, date, preuve, date_horodatage, message_horodate, document_type)
             VALUES (999, 1, 888, 0, '', 'action', '', '2020-01-01 00:00:00', '', '2020-01-01 00:00:00', '', '')"
        );
    }

    /**
     * id_e et id_u existent : seule la colonne id_d (par ailleurs supprimable) est orpheline.
     *
     * @throws Exception
     */
    private function insertDocumentActionWithOrphanDocument(): void
    {
        static::getSQLQuery()->query(
            "INSERT INTO document_action (id_a, id_d, action, date, id_e, id_u)
             VALUES (999, 'document-inexistant', 'creation', '2020-01-01 00:00:00', 1, 1)"
        );
    }

    private function countDocumentAction(int $id_a = 999): int
    {
        return (int) static::getSQLQuery()->queryOne('SELECT COUNT(*) FROM document_action WHERE id_a = ?', [$id_a]);
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

    /**
     * @throws Exception
     */
    private function insertUtilisateurRoleAucunDroit(): void
    {
        static::getSQLQuery()->query(
            "INSERT INTO utilisateur_role (id_u, role, id_e) VALUES (1, ?, 0)",
            [RoleUtilisateur::AUCUN_DROIT]
        );
    }

    /**
     * @throws Exception
     */
    private function insertOrphanConnecteurEntiteWithDependentFrequence(): void
    {
        static::getSQLQuery()->query(
            "INSERT INTO connecteur_entite (id_ce, id_e, libelle, id_connecteur, type, id_verrou, global)
             VALUES (42, 999, 'Orphelin', 'i-parapheur', 'entite', '', 0)"
        );
        static::getSQLQuery()->query(
            "INSERT INTO connecteur_frequence
                (id_cf, type_connecteur, famille_connecteur, id_connecteur, id_ce, action_type, type_document, action, expression, id_verrou)
             VALUES (2, 'entite', '', 'i-parapheur', 42, 'document', 'actes-generique', 'verif-tdt', '30', '')"
        );
    }

    private function countConnecteurEntite(int $id_ce = 42): int
    {
        return (int) static::getSQLQuery()->queryOne('SELECT COUNT(*) FROM connecteur_entite WHERE id_ce = ?', [$id_ce]);
    }

    private function countOrphanUtilisateur(int $id_u = 3): int
    {
        return (int) static::getSQLQuery()->queryOne('SELECT COUNT(*) FROM utilisateur WHERE id_u = ?', [$id_u]);
    }

    private function getUtilisateurIdE(int $id_u = 3): int
    {
        return (int) static::getSQLQuery()->queryOne('SELECT id_e FROM utilisateur WHERE id_u = ?', [$id_u]);
    }

    private function countOrphanEntite(int $id_e = 3): int
    {
        return (int) static::getSQLQuery()->queryOne('SELECT COUNT(*) FROM entite WHERE id_e = ?', [$id_e]);
    }

    private function getEntiteMere(int $id_e): int
    {
        return (int) static::getSQLQuery()->queryOne('SELECT entite_mere FROM entite WHERE id_e = ?', [$id_e]);
    }

    private function countEntiteAncetre(int $id_e, int $id_e_ancetre): int
    {
        return (int) static::getSQLQuery()->queryOne(
            'SELECT COUNT(*) FROM entite_ancetre WHERE id_e = ? AND id_e_ancetre = ?',
            [$id_e, $id_e_ancetre]
        );
    }

    private function countOrphanJournal(int $id_j = 999): int
    {
        return (int) static::getSQLQuery()->queryOne('SELECT COUNT(*) FROM journal WHERE id_j = ?', [$id_j]);
    }

    private function countOrphanConnecteurFrequence(int $id_cf = 2): int
    {
        return (int) static::getSQLQuery()->queryOne('SELECT COUNT(*) FROM connecteur_frequence WHERE id_cf = ?', [$id_cf]);
    }

    public function testNoOrphan(): void
    {
        static::assertSame(0, $this->commandTester->execute([]));
        static::assertStringContainsString('Aucune ligne orpheline trouvée', $this->commandTester->getDisplay());
    }

    public function testFindsOrphan(): void
    {
        $this->insertOrphanUtilisateur();

        static::assertSame(0, $this->commandTester->execute(['--dry-run' => true], ['interactive' => false]));

        $output = $this->commandTester->getDisplay();
        static::assertStringContainsString('utilisateur', $output);
        static::assertStringContainsString('id_e', $output);
        static::assertStringContainsString('entite', $output);
        static::assertStringContainsString('999', $output);
        static::assertSame(999, $this->getUtilisateurIdE());
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
    public function testGroupsSameOrphan(): void
    {
        $this->insertOrphanUtilisateur();
        $this->insertOrphanUtilisateur(4);

        static::assertSame(0, $this->commandTester->execute(['--dry-run' => true], ['interactive' => false]));
        static::assertStringContainsString('2 ligne(s) orpheline(s) trouvée(s)', $this->commandTester->getDisplay());
    }

    /**
     * @throws Exception
     */
    public function testFindsSelfReferenceOrphan(): void
    {
        $this->insertOrphanEntite();

        static::assertSame(0, $this->commandTester->execute(['--dry-run' => true], ['interactive' => false]));

        $output = $this->commandTester->getDisplay();
        static::assertStringContainsString('entite_mere', $output);
        static::assertStringContainsString('999', $output);
        static::assertStringContainsString('rattachement entité racine', $output);
    }

    /**
     * @throws Exception
     */
    public function testFindsRoleOrphan(): void
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
    public function testFindsFrequenceOrphan(): void
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
    public function testAsksConfirmation(): void
    {
        $this->insertOrphanConnecteurFrequence();

        static::assertSame(1, $this->commandTester->execute([], ['interactive' => false]));

        $output = $this->commandTester->getDisplay();
        static::assertStringContainsString('Abandon', $output);
        static::assertSame(1, $this->countOrphanConnecteurFrequence());
    }

    /**
     * @throws Exception
     */
    public function testConfirmDeletes(): void
    {
        $this->insertOrphanConnecteurFrequence();

        $this->commandTester->setInputs(['yes']);
        static::assertSame(0, $this->commandTester->execute([]));

        static::assertStringContainsString('supprimée', $this->commandTester->getDisplay());
        static::assertSame(0, $this->countOrphanConnecteurFrequence());
    }

    /**
     * @throws Exception
     */
    public function testDeclineKeeps(): void
    {
        $this->insertOrphanConnecteurFrequence();

        $this->commandTester->setInputs(['no']);
        static::assertSame(1, $this->commandTester->execute([]));

        static::assertSame(1, $this->countOrphanConnecteurFrequence());
    }

    /**
     * @throws Exception
     */
    public function testForceDeletes(): void
    {
        $this->insertOrphanConnecteurFrequence();

        static::assertSame(0, $this->commandTester->execute(['--force' => true], ['interactive' => false]));

        static::assertStringContainsString('supprimée', $this->commandTester->getDisplay());
        static::assertSame(0, $this->countOrphanConnecteurFrequence());
    }

    /**
     * @throws Exception
     */
    public function testDryRun(): void
    {
        $this->insertOrphanConnecteurFrequence();

        static::assertSame(0, $this->commandTester->execute(['--dry-run' => true], ['interactive' => false]));

        static::assertStringContainsString('Dry run', $this->commandTester->getDisplay());
        static::assertSame(1, $this->countOrphanConnecteurFrequence());
    }

    /**
     * @throws Exception
     */
    public function testReattachesUser(): void
    {
        $this->insertOrphanUtilisateur();

        static::assertSame(0, $this->commandTester->execute(['--force' => true], ['interactive' => false]));

        $output = $this->commandTester->getDisplay();
        static::assertStringContainsString('rattachement entité racine', $output);
        static::assertStringContainsString('1 rattachée(s)', $output);
        static::assertSame(1, $this->countOrphanUtilisateur());
        static::assertSame(0, $this->getUtilisateurIdE());
    }

    /**
     * @throws Exception
     */
    public function testReattachesEntity(): void
    {
        $this->insertOrphanEntite();

        static::assertSame(0, $this->commandTester->execute(['--force' => true], ['interactive' => false]));

        $output = $this->commandTester->getDisplay();
        static::assertStringContainsString('rattachement entité racine', $output);
        static::assertStringContainsString('1 rattachée(s)', $output);
        static::assertSame(1, $this->countOrphanEntite());
        static::assertSame(0, $this->getEntiteMere(3));
    }

    /**
     * @throws Exception
     */
    public function testReattachRebuildsAncestors(): void
    {
        $this->insertOrphanEntiteWithChild();

        static::assertSame(0, $this->commandTester->execute(['--force' => true], ['interactive' => false]));

        static::assertSame(0, $this->getEntiteMere(3));
        static::assertSame(0, $this->countEntiteAncetre(4, 999));
        static::assertSame(1, $this->countEntiteAncetre(4, 0));
    }

    /**
     * @throws Exception
     */
    public function testProtectedTableKept(): void
    {
        $this->insertOrphanJournal();

        static::assertSame(0, $this->commandTester->execute(['--force' => true], ['interactive' => false]));

        $output = $this->commandTester->getDisplay();
        static::assertStringContainsString('signalement seul', $output);
        static::assertStringContainsString('Aucune ligne orpheline à traiter automatiquement', $output);
        static::assertSame(1, $this->countOrphanJournal());
    }

    /**
     * Une table de traçabilité n'est jamais supprimée, même lorsque l'orpheline
     * porte sur une colonne qui serait par ailleurs supprimable (ici id_d).
     *
     * @throws Exception
     */
    public function testProtectedTableKeptOnDeletableColumn(): void
    {
        $this->insertDocumentActionWithOrphanDocument();

        static::assertSame(0, $this->commandTester->execute(['--force' => true], ['interactive' => false]));

        $output = $this->commandTester->getDisplay();
        static::assertStringContainsString('signalement seul', $output);
        static::assertStringContainsString('Aucune ligne orpheline à traiter automatiquement', $output);
        static::assertSame(1, $this->countDocumentAction());
    }

    /**
     * @throws Exception
     */
    public function testIgnoresAucunDroit(): void
    {
        $this->insertUtilisateurRoleAucunDroit();

        static::assertSame(0, $this->commandTester->execute([]));
        static::assertStringContainsString('Aucune ligne orpheline trouvée', $this->commandTester->getDisplay());
    }

    /**
     * @throws Exception
     */
    public function testCascadeChainedOrphans(): void
    {
        $this->insertOrphanConnecteurEntiteWithDependentFrequence();

        static::assertSame(0, $this->commandTester->execute(
            ['--force' => true, '--cascade' => true],
            ['interactive' => false]
        ));

        $output = $this->commandTester->getDisplay();
        static::assertStringContainsString('Passe n°2', $output);
        static::assertStringContainsString('Passe n°3', $output);
        static::assertStringNotContainsString('Passe n°4', $output);
        static::assertStringContainsString('2 ligne(s) orpheline(s) traitée(s) au total en 3 passe(s)', $output);
        static::assertSame(0, $this->countConnecteurEntite());
        static::assertSame(0, $this->countOrphanConnecteurFrequence());
    }

    /**
     * @throws Exception
     */
    public function testCascadeStops(): void
    {
        $this->insertOrphanConnecteurFrequence();

        static::assertSame(0, $this->commandTester->execute(
            ['--force' => true, '--cascade' => true],
            ['interactive' => false]
        ));

        $output = $this->commandTester->getDisplay();
        static::assertStringContainsString('Passe n°2', $output);
        static::assertStringNotContainsString('Passe n°3', $output);
        static::assertStringContainsString('1 ligne(s) orpheline(s) traitée(s) au total en 2 passe(s)', $output);
        static::assertSame(0, $this->countOrphanConnecteurFrequence());
    }

    /**
     * @throws Exception
     */
    public function testCascadeDryRun(): void
    {
        $this->insertOrphanConnecteurEntiteWithDependentFrequence();

        static::assertSame(0, $this->commandTester->execute(
            ['--dry-run' => true, '--cascade' => true],
            ['interactive' => false]
        ));

        static::assertStringNotContainsString('Passe n°2', $this->commandTester->getDisplay());
        static::assertSame(1, $this->countConnecteurEntite());
        static::assertSame(1, $this->countOrphanConnecteurFrequence());
    }

    /**
     * @throws Exception
     */
    public function testCascadeAsksEachPass(): void
    {
        $this->insertOrphanConnecteurEntiteWithDependentFrequence();

        $this->commandTester->setInputs(['yes', 'yes']);
        static::assertSame(0, $this->commandTester->execute(['--cascade' => true]));

        $output = $this->commandTester->getDisplay();
        static::assertStringContainsString('Passe n°2', $output);
        static::assertSame(0, $this->countConnecteurEntite());
        static::assertSame(0, $this->countOrphanConnecteurFrequence());
    }
}
