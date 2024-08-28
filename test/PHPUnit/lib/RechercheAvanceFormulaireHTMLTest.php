<?php

class RechercheAvanceFormulaireHTMLTest extends PastellTestCase
{
    /**
     * @throws Exception
     */
    public function testWhenHaveExternalDataField(): void
    {
        $id_ce = $this->createConnector('fakeIparapheur', 'I-Parapheur')['id_ce'];
        $this->configureConnector($id_ce, [
            'iparapheur_type' => 'Document',
        ]);

        $this->associateFluxWithConnector($id_ce, 'pdf-generique', 'signature');

        $this->getObjectInstancier()->getInstance(Authentification::class)->connexion('admin', 1);
        $rechercheAvancerFormulaireHTML  = $this->getObjectInstancier()->getInstance(
            RechercheAvanceFormulaireHTML::class
        );
        $rechercheAvancerFormulaireHTML->setRecuperateur(new Recuperateur(['type' => 'pdf-generique','id_e' => 1]));

        ob_start();
        $rechercheAvancerFormulaireHTML->display();
        $contents = ob_get_contents();
        ob_end_clean();

        $this->assertStringContainsString("<select name='iparapheur_sous_type' class=\"form-select col-md-8\">
            <option value=''></option>
                            <option                         value='Courrier'>Courrier</option>
                            <option                         value='Commande'>Commande</option>
                            <option                         value='Facture'>Facture</option>", $contents);
    }

    /**
     * @throws Exception
     */
    public function testWhenHaveExternalDataFieldAndNoAssociation()
    {

        $this->getObjectInstancier()->getInstance(Authentification::class)->connexion('admin', 1);
        $rechercheAvancerFormulaireHTML  = $this->getObjectInstancier()->getInstance(
            RechercheAvanceFormulaireHTML::class
        );
        $rechercheAvancerFormulaireHTML->setRecuperateur(new Recuperateur(['type' => 'pdf-generique','id_e' => 1]));

        ob_start();
        $rechercheAvancerFormulaireHTML->display();
        $contents = ob_get_contents();
        ob_end_clean();

        $this->assertStringContainsString("<select name='iparapheur_sous_type' class=\"form-select col-md-8\">
            <option value=''></option>
                    </select>", $contents);
    }
}
