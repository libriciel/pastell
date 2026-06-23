<?php

class ActesGeneriqueCest
{
    public function testall(AcceptanceTester $I)
    {
        $I->wantTo("faire une boucle Actes générique complète");
        $I->amLoggedAsAdmin();
        $I->amOnPage("/");
        $I->amOnPage("/Document/index?type=&id_e=1");
        $I->click("Actes (générique)");
        $I->see('Liste des dossiers Actes (générique) - déprécié pour Bourg-en-Bresse');
        $I->canSeeInCurrentUrl("/Document/list?type=actes-generique&id_e=1");
        $I->click("Créer");
        $I->see('Modification du dossier « Actes (générique) - déprécié » ( Bourg-en-Bresse )');
        $I->canSeeInCurrentUrl("/Document/edition?id_e=1");
        $I->selectOption("Nature de l'acte", "1");
        $I->fillField("Numéro de l'acte", date("YmdHis"));
        $I->fillField("Objet", "Délibération de test");
        $I->click("Enregistrer");
        $I->click("Cheminement");
        $I->click("Modifier");
        $I->click("Sélectionner dans la classification en matière et sous-matière");
        $I->click("1.1 - Marches publics");
        $I->see("1.1 Marches publics");


        $I->checkOption("Transmission à la signature");
        $I->checkOption("Transmission au contrôle de légalité");
        $I->checkOption("Transmission à la GED");
        $I->checkOption("Transmission au SAE");
        $I->click("Enregistrer");
        $I->see("Sous-type iparapheur	");


        $id_d = $I->grabFromCurrentUrl("#id_d=([^&]*)&#");

        /* Horrible hack car codeception et flowjs ca fait deux ... */
        $ob = ObjectInstancierFactory::getObjetInstancier();
        $internalAPI = $ob->getInstance(InternalAPI::class);
        $internalAPI->setUtilisateurId(0);
        $ob->getInstance(DaemonManager::class)->stop();

        $internalAPI->post(
            "/entite/1/document/$id_d/file/arrete",
            [
                'file_name' => 'actes.pdf',
                'file_content' => file_get_contents(__DIR__ . "/../_data/vide.pdf")
            ]
        );
        /* Fin du hack */

        $I->click("Sélectionner un sous-type");
        $I->see("Choix d'un type de dossier");
        $I->selectOption("Sous-type iparapheur", "Arrêté individuel");
        $I->click("valider");
        $I->see("Actes");
        $I->click("Enregistrer");

        $I->click("Cheminement");
        $I->click("Modifier");
        $I->click("Sélectionner des types de pièces");
        $I->selectOption("#type_0", "12_AD");
        $I->click("#form_sign button[type=submit]");
        $I->click("Enregistrer");


        $I->click("Transmettre au parapheur");
        $I->see("Le document a été envoyé au parapheur électronique");
        $I->click("Parapheur");
        $I->click("Vérifier le statut de signature");
        $I->see("Signature récupérée");
        $I->click("Transmettre au TdT");
        $I->see("Le document a été envoyé au contrôle de légalité");
        $I->click("Vérifier le statut de la transaction");
        $I->see("Acquitté par la préfecture");
        $I->click("Verser à la GED");
        $I->see("Le dossier Délibération de test a été versé sur le dépôt");
        /*$I->click("Verser au SAE");
        $I->see("Le document a été envoyé au SAE");*/
    }
}
