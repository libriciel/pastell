<?php

class ActesGeneriqueCheminementChangeTest extends PastellTestCase
{
    /**
     * @dataProvider changeProvider
     */
    public function testChange($envoi_tdt, $envoi_ged, $envoi_sae, $has_information_complementaire_expected)
    {

        $result = $this->getInternalAPI()->post(
            "/Document/1",
            ['type' => 'actes-generique']
        );
        $id_d = $result['id_d'];

        $this->getInternalAPI()->patch(
            "/Entite/1/document/$id_d",
            [
                'envoi_tdt' => $envoi_tdt,
                'envoi_ged' => $envoi_ged,
                'envoi_sae' => $envoi_sae,
                'has_information_complementaire' => 0
            ]
        );

        $actionExecutorFactory = $this->getObjectInstancier()->getInstance(ActionExecutorFactory::class);

        $result = $actionExecutorFactory->executeOnDocument(1, 0, $id_d, 'envoi-cheminement-change');

        $this->assertEquals(1, $result);

        $info = $this->getInternalAPI()->get("/Entite/1/document/$id_d");

        $this->assertEquals(
            $has_information_complementaire_expected,
            (bool)$info['data']['has_information_complementaire']
        );
    }

    public static function changeProvider()
    {
        return [
            [false,false,false,false],
            [true,false,false,false],
            [false,true,false,true],
            [false,false,true,true],
            [false,true,true,true],
            [true,false,true,false],
            [true,true,true,false],
        ];
    }
}
