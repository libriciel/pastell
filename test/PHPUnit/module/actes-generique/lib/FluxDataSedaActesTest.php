<?php

class FluxDataSedaActesTest extends PastellTestCase
{
    public static function getRestrictionAccessDataProvider()
    {
        return [
                [1,"1.1","AR038"],
                [3,"1.1","AR038"],
                [3,"4.2","AR048"],
                [4,"4.2","AR048"],
                [4,"8.2","AR038"],
                [3,"8.2","AR048"],
        ];
    }

    /**
     * @dataProvider getRestrictionAccessDataProvider
     */
    public function testget_restriction_acces($actes_nature, $classification, $restriction_access_expected)
    {
        $donneesFormulaire = $this->getDonneesFormulaireFactory()->getNonPersistingDonneesFormulaire();
        $donneesFormulaire->setData('acte_nature', $actes_nature);
        $donneesFormulaire->setData('classification', $classification);
        $fluxDataSedaActes = new FluxDataSedaActes($donneesFormulaire);
        $this->assertEquals($restriction_access_expected, $fluxDataSedaActes->get_restriction_acces());
    }


    public static function getProducteurDataProvider()
    {
        return [
            [1,"1.1","FOO"],
            [3,"1.1","FOO"],
            [3,"4.2","BAR"]
        ];
    }

    /**
     * @dataProvider getProducteurDataProvider
     * @throws UnrecoverableException
     */
    public function testget_id_producteur($actes_nature, $classification, $producteur_identifier)
    {
        $donneesFormulaire = $this->getDonneesFormulaireFactory()->getNonPersistingDonneesFormulaire();
        $donneesFormulaire->setData('acte_nature', $actes_nature);
        $donneesFormulaire->setData('classification', $classification);
        $fluxDataSedaActes = new FluxDataSedaActes($donneesFormulaire);
        $fluxDataSedaActes->setConnecteurContent([
            'id_producteur_hors_rh' => 'FOO',
            'id_producteur_rh' => 'BAR',
            'libelle_producteur_hors_rh' => 'L_FOO',
            'libelle_producteur_rh' => 'L_BAR'
        ]);
        $this->assertEquals($producteur_identifier, $fluxDataSedaActes->get_id_producteur());
        $this->assertEquals("L_$producteur_identifier", $fluxDataSedaActes->get_libelle_producteur());
    }

    /**
     * @throws Exception
     */
    public function testGet_date_aractes()
    {
        $donneesFormulaire = $this->getDonneesFormulaireFactory()->getNonPersistingDonneesFormulaire();
        $donneesFormulaire->addFileFromCopy('aractes', "aractes.xml", __DIR__ . "/../fixtures/aractes.xml");
        $fluxDataSedaActes = new FluxDataSedaActes($donneesFormulaire);
        $this->assertEquals("2017-12-27", $fluxDataSedaActes->get_date_aractes());
    }

    /**
     * @throws Exception
     */
    public function testGet_date_aractesFailed()
    {
        $donneesFormulaire = $this->getDonneesFormulaireFactory()->getNonPersistingDonneesFormulaire();
        $donneesFormulaire->addFileFromData('aractes', "aractes.xml", "foo");
        $fluxDataSedaActes = new FluxDataSedaActes($donneesFormulaire);
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("n'est pas un XML correct");
        $fluxDataSedaActes->get_date_aractes();
    }

    /**
     * @throws Exception
     */
    public function testGet_date_aractesDateNotFound()
    {
        $donneesFormulaire = $this->getDonneesFormulaireFactory()->getNonPersistingDonneesFormulaire();
        $donneesFormulaire->addFileFromData('aractes', "aractes.xml", "<foo></foo>");
        $fluxDataSedaActes = new FluxDataSedaActes($donneesFormulaire);
        $this->expectException(UnrecoverableException::class);
        $this->expectExceptionMessage("Impossible de récupérer la date de l'AR Acte");
        $fluxDataSedaActes->get_date_aractes();
    }

    /**
     * @throws Exception
     */
    public function test_get_date_de_laste()
    {
        $donneesFormulaire = $this->getDonneesFormulaireFactory()->getNonPersistingDonneesFormulaire();
        $donneesFormulaire->addFileFromData('aractes', "aractes.xml", "<foo></foo>");
        $donneesFormulaire->setData('date_de_lacte', '2019-01-01 15:38:22');
        $fluxDataSedaActes = new FluxDataSedaActes($donneesFormulaire);

        $this->assertEquals('2019-01-01', $fluxDataSedaActes->getData('date_de_lacte'));
    }
}
