<?php

class ConnecteurCest
{
    public function createFakeSignature(AcceptanceTester $I)
    {
        $connecteur_name = "Bouchon signature " . date("YmdHis");
        $I->wantTo("créer un connecteur fake signature");
        $I->amLoggedAsAdmin();
        $I->amOnPage("/Entite/connecteur?global=0&id_e=1");
        $I->click("Ajouter");
        $I->see("Ajout d'un connecteur");
        $I->canSeeInCurrentUrl("/Connecteur/new?global=0&id_e=1");
        $I->fillField("Libellé de l'instance", $connecteur_name);
        $I->selectOption("Connecteur", "fakeIparapheur");
        $I->click("Créer");
        $I->see("Connecteur ajouté avec succès");
        $I->canSeeInCurrentUrl("/Entite/connecteur?global=0&id_e=1");
    }
}
