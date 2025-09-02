<?php

class CPPVerifConnectiviteTest extends ExtensionCppTestCase
{
    private const FICHIER_CSV_INTERPRETE = __DIR__ . "/../../../../connecteur/chorus-par-csv/fixtures/chorus-csv-interprete.csv";

    protected function setUp(): void
    {
        parent::setUp();
        $this->getObjectInstancier()->getInstance(ConfigurationSQL::class)->setAdminEmails(['test@libriciel.invalid']);
    }
    /**
     * @throws Exception
     */
    public function testCPPVerifConnectivite()
    {
        $cppWrapper = $this->getMockBuilder(CPPWrapper::class)
            ->disableOriginalConstructor()
            ->getMock();

        $erreur = 'Utilisateur DEV_DESTTAA035@cpp2017.fr' . "\n";
        $erreur .= 'Erreur code HTTP: 401' . "\n";
        $erreur .= '{' . "\n";
        $erreur .= '"codeRetour": 401,' . "\n";
        $erreur .= '"libelle" : "Impossible d\'authentifier l\'utilisateur dans le LDAP."' . "\n";
        $erreur .= '}' . "\n";

        $cppWrapper->expects($this->exactly(3))
            ->method('testConnexion')
            ->willReturnOnConsecutiveCalls(
                true,
                true,
                $this->throwException(new Exception($erreur))
            );


        $cppWrapperFactory = $this->getMockBuilder(CPPWrapperFactory::class)
            ->disableOriginalConstructor()
            ->getMock();
        $cppWrapperFactory->expects($this->any())->method('newInstance')->willReturn($cppWrapper);

        $this->getObjectInstancier()->setInstance(CPPWrapperFactory::class, $cppWrapperFactory);


        $info = $this->createConnector('cpp', "CPP-Global", 0);
        $id_ce_chorus_global = $info['id_ce'];

        $info = $this->createConnector('cpp', "Chorus", self::ID_E_COL);
        $id_ce_chorus = $info['id_ce'];
        $connecteurDonneesFormulaire = $this->getDonneesFormulaireFactory()->getConnecteurEntiteFormulaire($id_ce_chorus);
        $connecteurDonneesFormulaire->setData('url', 'cpp url');
        $connecteurDonneesFormulaire->setData('user_login', "DEV_DESTTAA074@cpp2017.fr");
        $connecteurDonneesFormulaire->setData('user_password', "Riuxdnup64167[");

        $info = $this->createConnector('chorus-par-csv', "ChorusParCSV", self::ID_E_COL);
        $id_ce_chorus_csv = $info['id_ce'];
        $connecteurDonneesFormulaire = $this->getDonneesFormulaireFactory()->getConnecteurEntiteFormulaire($id_ce_chorus_csv);
        $connecteurDonneesFormulaire->setData('url', 'cpp url');
        $connecteurDonneesFormulaire->addFileFromCopy(
            "fichier_csv_interprete",
            "chorus-csv-interprete.csv",
            self::FICHIER_CSV_INTERPRETE
        );


        $this->triggerActionOnConnector($id_ce_chorus_global, 'verification_connectivite');

        $this->assertLastMessage($this->getLastMessage());
    }

    public function getLastMessage()
    {
        $last_message = 'Connecteurs Chorus Pro:<br />' . "\n";
        $last_message .= '<br />' . "\n";
        $last_message .= 'Nombre de connexions Chorus Pro ok : 1<br />' . "\n";
        $last_message .= 'Nombre de connexions Chorus Pro en erreur : 0<br />' . "\n";
        $last_message .= '<br />' . "\n";
        $last_message .= '<br />' . "\n";
        $last_message .= '<br />' . "\n";
        $last_message .= 'Connecteurs Chorus Pro par CSV:<br />' . "\n";
        $last_message .= '<br />' . "\n";
        $last_message .= 'Bourg-en-Bresse - ChorusParCSV : ' . $this->getSiteBase() . '/Connecteur/edition?id_ce=16<br />' . "\n";
        $last_message .= 'Nombre de connexions Chorus Pro par CSV ok: 1<br />' . "\n";
        $last_message .= 'Nombre de connexions Chorus Pro par CSV en erreur : 1<br />' . "\n";
        $last_message .= '<br />' . "\n";
        $last_message .= 'Connexions en erreur:<br />' . "\n";
        $last_message .= 'Utilisateur DEV_DESTTAA035@cpp2017.fr<br />' . "\n";
        $last_message .= 'Erreur code HTTP: 401<br />' . "\n";
        $last_message .= '{<br />' . "\n";
        $last_message .= '"codeRetour": 401,<br />' . "\n";
        $last_message .= '"libelle" : "Impossible d\'authentifier l\'utilisateur dans le LDAP."<br />' . "\n";
        $last_message .= '}<br />' . "\n";
        $last_message .= '<br />' . "\n";
        $last_message .= '<br />' . "\n";
        $last_message .= '<br />' . "\n";
        $last_message .= '<br />' . "\n";
        $last_message .= '<br />' . "\n";
        $last_message .= '<br />' . "\n";
        $last_message .= '<br />' . "\n";
        $last_message .= ' mail envoyé à ' .
            \implode(',', $this->getObjectInstancier()->getInstance(ConfigurationSQL::class)->getAdminEmails());

        return $last_message;
    }
}
