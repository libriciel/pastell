<?php

/**
 * @var ObjectInstancier $objectInstancier
 */

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Pastell\Service\Connecteur\ConnecteurAssociationService;
use Pastell\Service\Connecteur\ConnecteurDeletionService;

require_once __DIR__ . '/../init.php';

$id_e = get_argv(1);
$do = get_argv(2);

$logger = $objectInstancier->getInstance(Logger::class);
$logger->pushHandler(
    new  StreamHandler('php://stdout')
);

if (! $id_e) {
    echo "{$argv[0]} : Supprimer une entité avec toutes ses entitées filles (récursivement) ainsi que tous ces documents, connecteurs, associations, utilisateurs\n";
    echo "Usage : {$argv[0]} id_e [do]\n";
    echo "Il faut mettre un do pour réellement effectuer l'action\n";
    echo "Ne prends pas en compte les rôles et/ou notification déposé en dehors de l'entité à supprimer\n";
    exit;
}

$entiteSQL = $objectInstancier->getInstance(EntiteSQL::class);
$documentEntite = $objectInstancier->getInstance(DocumentEntite::class);
$document = $objectInstancier->getInstance(DocumentSQL::class);
$donneesFormulaireFactory = $objectInstancier->getInstance(DonneesFormulaireFactory::class);
$connecteurEntiteSQL = $objectInstancier->getInstance(ConnecteurEntiteSQL::class);
$fluxEntiteSQL = $objectInstancier->getInstance(FluxEntiteSQL::class);
$utilisateurListe = $objectInstancier->getInstance(UtilisateurListe::class);
$utilisateur = $objectInstancier->getInstance(UtilisateurSQL::class);
$utilisateurRoleService = $objectInstancier->getInstance(\Pastell\Service\Utilisateur\UtilisateurRoleService::class);
$jobManager = $objectInstancier->getInstance(JobManager::class);
$connecteurDeletionService = $objectInstancier->getInstance(ConnecteurDeletionService::class);
$connecteurAssociationService = $objectInstancier->getInstance(ConnecteurAssociationService::class);


$entite_list = $entiteSQL->getFille($id_e);

$id_e_list = array_reverse(array_map(function ($a) {
    return $a['id_e'];
}, $entite_list));
$id_e_list [] = $id_e;

$logger->info("Liste des entités à supprimer : ", $id_e_list);

foreach ($id_e_list as $id_e) {
    $logger->info("Traitement de l'entité $id_e");

    $all_doc = $documentEntite->getAll($id_e);
    $id_d_list = array_map(function ($a) {
        return $a['id_d'];
    }, $all_doc);

    $logger->info("Liste des document à supprimer : ", $id_d_list);

    foreach ($id_d_list as $id_d) {
        if ($do) {
            $donneesFormulaireFactory->get($id_d)->delete();
            $document->delete($id_d);
            $jobManager->deleteDocument($id_e, $id_d);
        }
        $logger->info("Suppression du document $id_d : " . ($do ? "[OK]" : "[PASS]"));
    }



    $all_connecteur = $connecteurEntiteSQL->getAll($id_e);
    $id_ce_list = array_map(function ($a) {
        return $a['id_ce'];
    }, $all_connecteur);

    $logger->info("Liste des connecteur à supprimer : ", $id_ce_list);
    foreach ($id_ce_list as $id_ce) {
        $all_flux = $fluxEntiteSQL->getUsedByConnecteur($id_ce);
        $id_fe_list = array_map(function ($a) {
            return $a['id_fe'];
        }, $all_flux);
        $logger->info("Liste des association à supprimer : ", $id_fe_list);
        foreach ($id_fe_list as $id_fe) {
            if ($do) {
                $connecteurAssociationService->deleteConnecteurAssociationById_fe($id_fe);
            }
            $logger->info("Suppression du l'association $id_fe : " . ($do ? "[OK]" : "[PASS]"));
        }

        if ($do) {
            $connecteurDeletionService->deleteConnecteur($id_ce);
        }
        $logger->info("Suppression du connecteur $id_ce : " . ($do ? "[OK]" : "[PASS]"));
    }

    $all_utilisateur = $utilisateurListe->getAllUtilisateurSimple($id_e);
    $id_u_list = array_map(function ($a) {
        return $a['id_u'];
    }, $all_utilisateur);

    $logger->info("Liste des utilisateurs à supprimer : ", $id_u_list);
    foreach ($id_u_list as $id_u) {
        if ($do) {
            $utilisateurRoleService->removeAllRoles($id_u);
            $utilisateur->desinscription($id_u);
        }
        $logger->info("Suppression du l'utilisateur $id_u : " . ($do ? "[OK]" : "[PASS]"));
    }

    if ($do) {
        $entiteSQL->removeEntite($id_e);
    }
    $logger->info("Suppression de l'entite $id_e : " . ($do ? "[OK]" : "[PASS]"));
}
