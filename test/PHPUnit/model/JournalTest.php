<?php

use Pastell\Storage\StorageInterface;

class JournalTest extends PastellTestCase
{
    /**
     * @var Journal
     */
    private $journal;

    protected function setUp(): void
    {
        parent::setUp();

        $this->journal = $this->getJournal();
        $horodateur = new MockHorodateur($this->getObjectInstancier()->getInstance(OpensslTSWrapper::class));
        $this->journal->setHorodateur($horodateur);
    }

    public function getJournal()
    {

        //Ne pas utilise le journal de l'objectInstancier !
        $journal = new Journal(
            $this->getSQLQuery(),
            $this->getObjectInstancier()->getInstance(UtilisateurSQL::class),
            $this->getObjectInstancier()->getInstance(DocumentSQL::class),
            $this->getObjectInstancier()->getInstance(DocumentTypeFactory::class),
            $this->getLogger(),
            false,
            $this->getObjectInstancier()->getInstance('use_external_storage_for_journal_proof'),
            $this->getObjectInstancier()->getInstance(StorageInterface::class),
        );
        $journal->setId(1);
        return $journal;
    }


    public function testAddConsulter()
    {
        $id_j = $this->journal->addConsultation(1, 'XYZT', 1);
        $info = $this->journal->getInfo($id_j);
        $this->assertEquals("Eric Pommateau a consulté le dossier", $info['message']);
    }

    public function testAddSameConsulter()
    {
        $this->journal->addConsultation(1, 'XYZT', 1);
        $this->assertFalse($this->journal->addConsultation(1, 'XYZT', 1));
    }

    public function testAddActionAuto()
    {
        $id_j = $this->journal->addActionAutomatique(Journal::DOCUMENT_ACTION, 1, "XYZT", "test", "message de test");
        $info = $this->journal->getInfo($id_j);
        $this->assertEquals(0, $info['id_u']);
    }

    public function testAddSQL()
    {
        $id_j = $this->journal->addSQL(false, false, false, false, false, false);
        $info = $this->journal->getInfo($id_j);
        $this->assertEquals("MOCK TIMESTAMP", $info['preuve']);
    }

    public function testAddSQLWithObjectStorage()
    {
        $this->getObjectInstancier()->setInstance('use_external_storage_for_journal_proof', true);
        $this->setUp();
        $this->testAddSQL();
    }

    public function testAddSQLWihtoutHorodateur()
    {
        $journal = $this->getJournal();
        $id_j = $journal->addSQL(false, false, false, false, false, false);
        $info = $journal->getInfo($id_j);
        $this->assertEquals("", $info['preuve']);
    }

    public function testAddSQLWihtoutHorodateurwithObjectStorage()
    {
        $this->getObjectInstancier()->setInstance('use_external_storage_for_journal_proof', true);
        $this->setUp();
        $this->testAddSQLWihtoutHorodateur();
    }

    public function testGetAll()
    {
        $this->journal->addConsultation(1, "XYZ", 1);
        $info = $this->journal->getAll(1, false, false, 1, 0, 10, "consulté");
        $this->assertEquals("Eric Pommateau a consulté le dossier", $info[0]['message']);
    }

    public function testGetAllAllInfo()
    {
        $this->journal->addConsultation(1, "XYZ", 1);
        $info = $this->journal->getAll(1, "test", 1, 1, 0, 10, "consulté", "2015-01-01", "2015-01-02", true);
        $this->assertEmpty($info);
    }

    public function testCountAll()
    {
        $this->journal->addConsultation(1, "XYZ", 1);
        $this->assertEquals(1, $this->journal->countAll(1, false, false, 1, "consulté", false, false));
    }
    public function testCountAllEmpty()
    {
        $this->journal->addConsultation(1, "XYZ", 1);
        $this->assertEquals(0, $this->journal->countAll(1, "test", 1, 1, "consulté", "2015-01-01", "2015-01-02"));
    }

    public function testGetTypeAsString()
    {
        $this->assertEquals("Connexion", $this->journal->getTypeAsString(Journal::CONNEXION));
    }

    public function testGetAllInfo()
    {
        $id_j = $this->journal->addConsultation(1, "XYZ", 1);
        $info = $this->journal->getAllInfo($id_j);
        $this->assertEquals("Eric Pommateau a consulté le dossier", $info['message']);
    }

    public function testGetAllInfoNotExisting()
    {
        $this->assertFalse($this->journal->getAllInfo(42));
    }

    public function testHorodateAllNoHorodateur()
    {
        $journal = $this->getJournal();
        $this->expectException("Exception");
        $this->expectExceptionMessage("Aucun horodateur configuré");
        $journal->horodateAll();
    }

    public function testHorodateAll()
    {
        $journal = $this->getJournal();
        $id_j = $journal->addConsultation(1, "XYZ", 1);

        $info = $journal->getInfo($id_j);
        $this->assertEquals("", $info['preuve']);

        $this->expectOutputString("1 horodaté : 1977-02-18 08:40:00\n");
        $this->journal->horodateAll();

        $info = $journal->getInfo($id_j);
        $this->assertEquals("MOCK TIMESTAMP", $info['preuve']);
    }

    public function testHorodateAllWithObjectStorage()
    {
        $this->getObjectInstancier()->setInstance('use_external_storage_for_journal_proof', true);
        $this->setUp();
        $this->testHorodateAll();
    }

    public function testGetNbLine()
    {
        $this->journal->addConsultation(1, "XYZ", 1);
        $this->assertEquals(1, $this->journal->getNbLine());
        $this->assertEquals(0, $this->journal->getNbLineHistorique());
        $this->assertNotEmpty($this->journal->getFirstLineDate());
    }

    public function testPurgeToHistorique()
    {

        $id_j = $this->journal->addConsultation(1, "XYZ", 1);
        $this->getSQLQuery()->queryOne("UPDATE journal SET date=? WHERE id_j=?", "1977-02-18", $id_j);

        $this->assertEquals(1, $this->journal->getNbLine());
        $this->assertEquals(0, $this->journal->getNbLineHistorique());

        $this->journal->purgeToHistorique();

        $this->assertEquals(0, $this->journal->getNbLine());
        $this->assertEquals(1, $this->journal->getNbLineHistorique());

        $this->assertEquals("Purge de l'enregitrement id_j $id_j", $this->getLogRecords()[1]['message']);
    }

    public function testPurgeToHistoriqueWhenDuplicate()
    {
        $id_j = $this->journal->addConsultation(1, "XYZ", 1);
        $this->getSQLQuery()->queryOne("UPDATE journal SET date=? WHERE id_j=?", "1977-02-18", $id_j);

        $sql_insert = "INSERT INTO journal_historique SELECT * FROM journal WHERE id_j=?";
        $this->getSQLQuery()->queryOne($sql_insert, $id_j);

        $this->journal->purgeToHistorique();

        $this->assertEquals(0, $this->journal->getNbLine());
        $this->assertEquals(1, $this->journal->getNbLineHistorique());
    }
}
