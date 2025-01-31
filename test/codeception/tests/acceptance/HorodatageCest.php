<?php

class HorodatageCest
{
    public function horodatageConfigurerEtFoncionnel(AcceptanceTester $I)
    {
        $I->wantTo("m'assurer que l'horodatage est fonctionnel");
        $I->amLoggedAsAdmin();
        $I->amOnPage("/Entite/connecteur?global=1&id_e=0");
        $I->see("Horodateur interne par défaut	");
        $I->amOnPage("/Flux/index?id_e=0");
        $I->see("Horodateur interne par défaut");
        $I->click("Horodateur interne par défaut");
        $I->click("Tester la création d'un token");
        $I->see("Connexion OpenSign OK:");
        $I->click("Créer et vérifier un token");
        $I->see("Vérification: OK");
        $I->click("Enregistrer un test dans le journal");
        $I->see("Enregistrement de la ligne #");
        $I->see("Vérification: OK");
        $I->amOnPage("/Journal/index");
        $I->see("Ceci est une ligne de test");
    }
}
