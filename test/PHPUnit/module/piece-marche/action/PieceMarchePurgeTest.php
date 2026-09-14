<?php

class PieceMarchePurgeTest extends PastellMarcheTestCase
{
    public const PIECES_MARCHE = 'piece-marche';

    public static function getPurgeDataProvider()
    {

        return [
            'PiecesMarcheEnvoiSAETrue' => [
                self::PIECES_MARCHE,
                "modification",
                Purge::GO_TROUGH_STATE,
                "send-archive",
                "envoi_sae: on",
                ["modification", "termine"],
                true,
                ""
            ],
            'PiecesMarcheEnvoiSAEFalse' => [
                self::PIECES_MARCHE,
                "termine",
                Purge::IN_STATE,
                "send-archive",
                "envoi_sae: on",
                ["modification", "send-archive", "termine"],
                false,
                "#action impossible : or_1 n'est pas vérifiée#"
            ],
            'PiecesMarchePrepareSAEFalse' => [
                self::PIECES_MARCHE,
                "termine",
                Purge::IN_STATE,
                "preparation-send-sae",
                "envoi_sae: on",
                ["modification", "send-archive", "termine"],
                false,
                "#action impossible : role_id_e n'est pas vérifiée#"
            ],
            'PiecesMarcheEnvoiGEDFalse' => [
                self::PIECES_MARCHE,
                "termine",
                Purge::IN_STATE,
                "send-ged",
                "envoi_ged: on",
                ["modification", "termine"],
                false,
                "#action impossible : content n'est pas vérifiée#"
            ],
        ];
    }

    /**
     * @param $document_type
     * @param $document_etat
     * @param $passer_par_l_etat
     * @param $document_etat_cible
     * @param $modification
     * @param $liste_etats
     * @param $expected_true
     * @param $message
     * @dataProvider getPurgeDataProvider
     * @throws NotFoundException
     * @throws Exception
     */
    public function testPurgeDocument($document_type, $document_etat, $passer_par_l_etat, $document_etat_cible, $modification, $liste_etats, $expected_true, $message)
    {

        $id_d = $this->createDocument($document_type)['id_d'];

        $donneesFormulaire = $this->getDonneesFormulaireFactory()->get($id_d);
        $donneesFormulaire->setTabData([
            "date_document" => "2018-01-01",
            "libelle" => "mon marché",
            "numero_marche" => "1234",
            "type_marche" => "T",
            "numero_consultation" => "12",
            "type_consultation" => "MAPA",
            "etape" => "EB",
            "type_piece_marche" => "AC",
            "libelle_piece" => "pièce",
            "soumissionnaire" => "toto",
        ]);
        $donneesFormulaire->addFileFromCopy('document', 'vide.pdf', __DIR__ . "/../fixtures/vide.pdf");

        $actionChange = $this->getObjectInstancier()->getInstance(ActionChange::class);
        foreach ($liste_etats as $etat) {
            $actionChange->addAction($id_d, 1, 0, $etat, 'test');
        }

        $purge = $this->getObjectInstancier()->getInstance(Purge::class);

        $connecteurConfig = $this->getDonneesFormulaireFactory()->getNonPersistingDonneesFormulaire();
        $connecteurConfig->setTabData([
            'actif' => 1,
            'document_type' => $document_type,
            'document_etat' => $document_etat,
            'passer_par_l_etat' => $passer_par_l_etat,
            'document_etat_cible' => $document_etat_cible,
            'modification' => $modification
        ]);

        $purge->setConnecteurInfo(['id_e' => 1, 'id_ce' => 42]);
        $purge->setConnecteurConfig($connecteurConfig);

        $jobManager = $this->getObjectInstancier()->getInstance(JobManager::class);
        $this->assertFalse($jobManager->hasActionProgramme(1, $id_d));
        $purge->purger();

        if ($expected_true) {
            $this->assertTrue($jobManager->hasActionProgramme(1, $id_d));
            $sql = "SELECT * FROM job_queue ";
            $result = $this->getSQLQuery()->query($sql);
            $this->assertEquals($document_etat_cible, $result[0]['etat_cible']);
            $this->assertMatchesRegularExpression("#$id_d#", $purge->getLastMessage());
        } else {
            $this->assertFalse($jobManager->hasActionProgramme(1, $id_d));
            $this->assertMatchesRegularExpression($message, $purge->getLastMessage());
        }
    }
}
