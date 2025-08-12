<?php

/**
 * @var ObjectInstancier $objectInstancier
 */

use Pastell\Bootstrap\Bootstrap;

require_once __DIR__ . '/../../../docker/define-from-environnement.php';

/** TODO réinitialiser la base de données... */

require_once __DIR__ . '/../../../init.php';

$sqlQuery = $objectInstancier->getInstance(SQLQuery::class);
$sqlQuery->query(file_get_contents(__DIR__ . '/truncate_all.sql'));

require_once __DIR__ . '/../../../docker/app/init-docker.php';

$objectInstancier->setInstance('pastell_admin_login', 'admin');
$objectInstancier->setInstance('pastell_admin_email', 'test@libriciel.invalid');

$bootstrap = $objectInstancier->getInstance(Bootstrap::class);
$bootstrap->bootstrap();

$utilisateurSQL = $objectInstancier->getInstance(UtilisateurSQL::class);
$utilisateurSQL->setPassword(1, 'admin');

/** @var InternalAPI $internalAPI */
$internalAPI = $objectInstancier->getInstance(InternalAPI::class);
$internalAPI->setCallerType(InternalAPI::CALLER_TYPE_SCRIPT);
$internalAPI->setUtilisateurId(1);

/* Création de l'entité Bourg-en-Bresse*/
$info = $internalAPI->post(
    "/Entite",
    [
        "type" => EntiteSQL::TYPE_COLLECTIVITE,
        "denomination" => "Bourg-en-Bresse",
        "siren" => '000000000'
    ]
);

$id_e = $info['id_e'];

/* Création d'un connecteur bouchon de signature */
$info = $internalAPI->post(
    "/Entite/$id_e/Connecteur",
    [
        'libelle' => 'Bouchon de signature',
        'id_connecteur' => 'fakeIparapheur'
    ]
);
$id_ce = $info['id_ce'];

$internalAPI->patch(
    "/Entite/$id_e/Connecteur/$id_ce/content",
    [
        'iparapheur_type' => 'Actes',
        'iparapheur_envoi_status' => 'ok',
        'iparapheur_retour' => 'Archive'
    ]
);

$internalAPI->post(
    "/Entite/$id_e/Flux/actes-generique/connecteur/$id_ce",
    ["type" => "signature"]
);

/* Création d'un connecteur bouchon Tdt */
$info = $internalAPI->post(
    "/Entite/$id_e/Connecteur",
    [
        'libelle' => 'Bouchon Tdt',
        'id_connecteur' => 'fakeTdt'
    ]
);
$id_ce = $info['id_ce'];

$internalAPI->post(
    "/Entite/$id_e/Connecteur/$id_ce/file/classification_file",
    [
        'file_name' => 'classification.xml',
        'file_content' => file_get_contents(__DIR__ . "/_data/classification.xml")
    ]
);

$internalAPI->post(
    "/Entite/$id_e/Flux/actes-generique/connecteur/$id_ce",
    ["type" => "TdT"]
);

/* Création d'un connecteur bouchon GED */
$info = $internalAPI->post(
    "/Entite/$id_e/Connecteur",
    [
        'libelle' => 'Bouchon GED',
        'id_connecteur' => 'FakeGED'
    ]
);
$id_ce = $info['id_ce'];
$internalAPI->post(
    "/Entite/$id_e/Flux/actes-generique/connecteur/$id_ce",
    ["type" => "GED"]
);

/* Création du connecteur bouchon SAE */
$info = $internalAPI->post(
    "/Entite/$id_e/Connecteur",
    [
        'libelle' => 'Bouchon SAE',
        'id_connecteur' => 'fakeSAE'
    ]
);
$id_ce = $info['id_ce'];
$info = $internalAPI->post(
    "/Entite/$id_e/Flux/actes-generique/connecteur/$id_ce",
    ["type" => "SAE"]
);
