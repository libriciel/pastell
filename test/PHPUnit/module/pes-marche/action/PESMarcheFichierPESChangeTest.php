<?php

class PESMarcheFichierPESChangeTest extends PastellMarcheTestCase
{
    private const FILENAME = "exemple_marche_contrat_initial_nov2017.xml";
    private const NOMFIC = "PASTELLTEST9";
    private const OBJET = 'foo-bar';

    private function createPesMarche()
    {
        $info = $this->getInternalAPI()->post(
            "/entite/1/document",
            ['type' => 'pes-marche']
        );

        return $info['id_d'];
    }

    private function postPES($id_d)
    {
        $this->getInternalAPI()->post(
            "/entite/1/document/$id_d/file/fichier_pes",
            [
                'file_name' => self::FILENAME,
                'file_content' =>
                    file_get_contents(__DIR__ . "/../fixtures/" . self::FILENAME)
            ]
        );
        $actionExecutorFactory = $this->getObjectInstancier()->getInstance(ActionExecutorFactory::class);
        $actionExecutorFactory->executeOnDocument(1, 0, $id_d, 'fichier_pes_change');
    }

    public function testObjetBecomeFilenameIfEmpty()
    {
        $id_d = $this->createPesMarche();
        $this->postPES($id_d);

        $info = $this->getInternalAPI()->get("/entite/1/document/$id_d");
        $this->assertEquals(self::NOMFIC, $info['data']['objet']);
        $this->assertEquals(self::NOMFIC, $info['info']['titre']);
    }

    public function testObjetDidNotBecomeFilenameIfNotEmpty()
    {
        $id_d = $this->createPesMarche();

        $this->getInternalAPI()->patch(
            "/entite/1/document/$id_d/",
            [
                    'objet' => self::OBJET
                ]
        );

        $this->postPES($id_d);

        $info = $this->getInternalAPI()->get("/entite/1/document/$id_d");
        $this->assertEquals(self::OBJET, $info['data']['objet']);
        $this->assertEquals(self::OBJET, $info['info']['titre']);
    }

    public function testAffectationIsEditable()
    {
        $id_d = $this->createPesMarche();
        $this->postPES($id_d);

        $this->triggerActionOnDocument($id_d, 'affectation');

        $donneesFormulaire = $this->getDonneesFormulaireFactory()->get($id_d);

        $this->assertTrue($donneesFormulaire->isEditable('objet'));
    }

    public function testAffectationPesPJDoesNotFailOnNullIdContrat()
    {
        $id_d = $this->createPesMarche();

        $donneesFormulaire = $this->getDonneesFormulaireFactory()->get($id_d);
        $donneesFormulaire->addFileFromCopy(
            'fichier_pes',
            'PES_PJ_TEST',
            __DIR__ . "/../fixtures/PES_PJ_TEST"
        );

        $this->assertTrue($this->triggerActionOnDocument($id_d, 'affectation'));
        $this->assertLastMessage("Les données du PES Aller ont été extraites");
    }
}
