<?php

class FakeSEDATest extends PastellTestCase
{
    /**
     * @throws Exception
     */
    public function testCoverAll(): void
    {
        $fakeSEDA = $this->getObjectInstancier()->getInstance(FakeSEDA::class);
        $fakeSEDA->setDataDir($this->getObjectInstancier()->getInstance('data_dir'));
        $donnesFormulaire = $this->getDonneesFormulaireFactory()->getNonPersistingDonneesFormulaire();
        $fakeSEDA->setConnecteurConfig($donnesFormulaire);
        $fluxData = new FluxDataSedaDefault($donnesFormulaire);

        $this->assertStringEqualsFile(
            $this->getObjectInstancier()->getInstance('data_dir') . '/connector/fakeSeda/bordereau.xml',
            $fakeSEDA->getBordereau($fluxData)
        );

        $this->assertTrue($fakeSEDA->validateBordereau(""));
        $this->assertEmpty($fakeSEDA->getLastValidationError());

        $tmpFolder = new TmpFolder();
        $tmp_folder = $tmpFolder->create();

        $fakeSEDA->generateArchive($fluxData, "$tmp_folder/toto");
        $this->assertFileExists("$tmp_folder/toto");
        $tmpFolder->delete($tmp_folder);
    }
}
