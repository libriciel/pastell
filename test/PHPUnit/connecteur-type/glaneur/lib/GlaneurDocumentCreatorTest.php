<?php

class GlaneurDocumentCreatorTest extends PastellTestCase
{
    private const HELIOS_AUTOMATIQUE = 'helios-automatique';
    private const IMPORTATION = 'importation';

    private $tmpFolder;
    private $workspace_path;
    private $tmp_folder;

    /** @throws Exception */
    protected function setUp(): void
    {
        parent::setUp();
        $this->tmpFolder = new TmpFolder();
        $this->workspace_path = $this->tmpFolder->create();
        $this->tmp_folder = $this->tmpFolder->create();
        $this->getObjectInstancier()->setInstance('workspacePath', $this->workspace_path);
        copy(__DIR__ . "/../fixtures/pes_exemple/test.xml", $this->tmp_folder . "/test.xml");
    }

    protected function tearDown(): void
    {
        $this->tmpFolder->delete($this->workspace_path);
        $this->tmpFolder->delete($this->tmp_folder);
    }

    /**
     * @throws Exception
     */
    public function testCreateDocument()
    {
        $notification = $this->getObjectInstancier()->getInstance(Notification::class);
        $notification->add(1, 1, self::HELIOS_AUTOMATIQUE, self::IMPORTATION, false);

        $glaneurLocalDocumentCreator = $this->getObjectInstancier()->getInstance(GlaneurDocumentCreator::class);

        $glaneurLocalDocumentInfo = new GlaneurDocumentInfo(1);
        $glaneurLocalDocumentInfo->nom_flux = self::HELIOS_AUTOMATIQUE;
        $glaneurLocalDocumentInfo->metadata = ['objet' => 'test_pes'];
        $glaneurLocalDocumentInfo->element_files_association = [
            'fichier_pes' => ['test.xml']
        ];
        $glaneurLocalDocumentInfo->action_ok = self::IMPORTATION;
        $glaneurLocalDocumentInfo->action_ko = 'erreur_import';


        $this->assertNotEmpty(
            $glaneurLocalDocumentCreator->create(
                $glaneurLocalDocumentInfo,
                $this->tmp_folder
            )
        );
        $journal_logs = $this->getJournalConsultationService()->getList(1, "", "", "", 0, 100);
        $this->assertEquals(
            "notification envoyée à eric@sigmalis.com",
            $journal_logs[0]['message']
        );
    }

    /**
     * @throws Exception
     */
    public function testCreateDocumentFailed()
    {
        $glaneurLocalDocumentCreator = $this->getObjectInstancier()->getInstance(GlaneurDocumentCreator::class);

        $glaneurLocalDocumentInfo = new GlaneurDocumentInfo(1);
        $glaneurLocalDocumentInfo->nom_flux = self::HELIOS_AUTOMATIQUE;
        $glaneurLocalDocumentInfo->metadata = [];
        $glaneurLocalDocumentInfo->element_files_association = [
            'fichier_pes' => ['test.xml']
        ];
        $glaneurLocalDocumentInfo->action_ok = self::IMPORTATION;
        $glaneurLocalDocumentInfo->action_ko = 'erreur_import';

        $this->assertNotEmpty(
            $glaneurLocalDocumentCreator->create(
                $glaneurLocalDocumentInfo,
                $this->tmp_folder
            )
        );
    }
}
