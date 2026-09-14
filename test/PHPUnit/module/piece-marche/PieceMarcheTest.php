<?php

class PieceMarcheTest extends PastellMarcheTestCase
{
    /**
     * @dataProvider getMontantrovide
     */
    public function testMontant($montant, $expected_result)
    {

        $id_d = $this->createDocument('piece-marche')['id_d'];

        $info = $this->getInternalAPI()->patch("/Entite/1/document/$id_d", [
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
            "montant" => $montant
        ]);

        if ($expected_result) {
            $this->assertEquals("Le formulaire est incomplet : le champ «Document» est obligatoire.", $info['message']);
        } else {
            $this->assertEquals("Le champ «Montant (estimatif ou notifié)» est incorrect (Nombre décimal sans espace) ", $info['message']);
        }
    }

    public static function getMontantrovide()
    {
        return [
            ['',true],
            ['12.5',true],
            ['12,5',true],
            ['12',true],
            ['12 000',false],
            ['1,2,3',false],
            ['5.',false],
            [',1',false],
        ];
    }
}
