<?php

class DossierMarcheTest extends PastellMarcheTestCase
{
    /**
     * @dataProvider getCodeCPVProvide
     */
    public function testCodeCPV($code_cpv, $expected_result)
    {
        $result = $this->getInternalAPI()->post(
            "/Document/" . PastellTestCase::ID_E_COL,
            ['type' => 'dossier-marche']
        );
        $id_d = $result['id_d'];

        $info = $this->getInternalAPI()->patch("/Entite/1/document/$id_d", [
            "date_debut" => '1981-01-01',
            "date_fin" => '1982-07-12',
            "date_notification" => '1982-07-13',
            "numero_consultation" => "123456",
            "numero_marche" => "123",
            "code_cpv" => $code_cpv,
            "type_consultation" =>  "MAPA",
            "type_marche" =>  "S",
            "infructueux" => 1,
            "attributaire" =>  "Libriciel SCOP\nAPI",
            "libelle" =>  "Achat d'un bus logiciel",
            "contenu_versement" => "premiere partie"
        ]);

        if ($expected_result) {
            $this->assertEquals("Le formulaire est incomplet : le champ «Fichier ZIP» est obligatoire.", $info['message']);
        } else {
            $this->assertEquals("Le champ «Code CPV» est incorrect (Le code CPV doit être de la forme : 12345678-9) ", $info['message']);
        }
    }

    public static function getCodeCPVProvide()
    {
        return [
            ['12345678-9',true],
            ['200001_X',false],
            ['',true],
            ['12345C78-A',false],
        ];
    }
}
