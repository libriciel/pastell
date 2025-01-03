<?php

class ConnecteurFrequenceCest
{
    public function displayConnecteurFrequenceInConnecteurEdition(AcceptanceTester $I)
    {
        $I->wantTo("vérifier que la fréquence du connecteur s'affiche sur la page connecteur");
        $I->amLoggedAsAdmin();
        $I->amOnPage("Daemon/frequenceConfig");
        $I->click("Ajouter");
        $I->fillField("Expression", "10 X 2");
        $I->selectOption("Type de connecteur", "Connecteurs globaux");
        $I->fillField("Verrou", "VERROU");
        $I->click("#daemonedit-frequence-enregistrer");
        $I->see("Détail sur la fréquence d'un connecteur");
        $I->see("Toutes les 10 minutes (2 fois)");
        $I->see("Suspendre le travail");
        $I->see("VERROU");

        $I->amOnPage("/Entite/connecteur?global=1&id_e=0");
        $I->see("Liste des connecteurs");

        $I->click("Ajouter");
        $I->fillField("Libellé de l'instance", "LDAP");
        $I->selectOption("Connecteur", "ldap-verification");
        $I->click("Créer");
        $I->see("Connecteur ajouté avec succès");

        $I->click("//tr[contains(td, 'Vérification')]//a");
        //$I->click("Configurer");

        $I->see("Toutes les 10 minutes (2 fois)");
        $I->see("Suspendre le travail");
        $I->see("VERROU");
    }
}
