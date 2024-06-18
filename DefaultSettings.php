<?php

//Ce fichier contient les valeurs par défaut

use Pastell\Service\FeatureToggle\DisplayFeatureToggleInTestPage;
use Pastell\Service\FeatureToggle\TestingFeature;

$feature_toggle = [];

/** Exemple d'activation d'une fonctionnalité optionnelle */
$feature_toggle[TestingFeature::class] = true;
$feature_toggle[DisplayFeatureToggleInTestPage::class] = true;


if (file_exists(__DIR__ . "/LocalSettings.php")) {
    //Il est possible d'écraser les valeurs par défaut en
    //créant un fichier LocalSettings.php
    require_once(__DIR__ . "/LocalSettings.php");
}

foreach (glob("/data/config/*.php") as $file_name) {
    include_once($file_name);
}


if (!defined("PASTELL_PATH")) {
    define("PASTELL_PATH", __DIR__ . "/");
}

if (!defined("ADMIN_EMAIL")) {
    define("ADMIN_EMAIL", "test@libriciel.net");
}

if (!defined("PASTELL_ADMIN_EMAIL")) {
    define("PASTELL_ADMIN_EMAIL", "test@libriciel.net");
}

if (!defined("PASTELL_ADMIN_LOGIN")) {
    define("PASTELL_ADMIN_LOGIN", "admin");
}

//Emplacement du répertoire pour sauvegarder les fichiers temporaires
//ATTENTION : CE RÉPERTOIRE DOIT ÊTRE ACCESSIBLE EN ECRITURE
if (!defined("WORKSPACE_PATH")) {
    define("WORKSPACE_PATH", PASTELL_PATH . "/workspace");
}

//Définition de la connexion à la base de données
if (!defined("BD_DSN")) {
    define("BD_DSN", "mysql:dbname=pastell;host=127.0.0.1;port=3306;charset=utf8mb4");
}
if (!defined("BD_USER")) {
    define("BD_USER", "pastell");
}
if (!defined("BD_PASS")) {
    define("BD_PASS", "pastell");
}


//Définition de la connexion à la base de données pour les tests unitaires et les tests de validation
if (!defined("BD_DSN_TEST")) {
    define("BD_DSN_TEST", "mysql:dbname=pastell_test;host=localhost;port=8889");
}
if (!defined("BD_USER_TEST")) {
    define("BD_USER_TEST", "user");
}
if (!defined("BD_PASS_TEST")) {
    define("BD_PASS_TEST", "user");
}
if (!defined("BD_DBNAME_TEST")) {
    define("BD_DBNAME_TEST", "pastell_test");
}


//Attention, il faut une version d'openSSL > 1.0.0a
if (!defined("OPENSSL_PATH")) {
    //Une mauvaise définition du chemin entraîne une erreur fatale lors de la tentative de connexion.
    //OpenSSL est OBLIGATOIRE sur Pastell
    define("OPENSSL_PATH", "/usr/bin/openssl");
}

//Racine du site Pastell
//ex : http://pastell.libriciel.coop/
//ex : http://pastell.libriciel.coop/pastell/
if (!defined('SITE_BASE')) {
    define('SITE_BASE', 'https://127.0.0.1');
}

if (!defined("WEBSEC_BASE")) {
    define("WEBSEC_BASE", "http://localhost/pastell/web-mailsec/");
}

if (!defined("AGENT_FILE_PATH")) {
    define("AGENT_FILE_PATH", "/tmp/agent");
}
if (!defined("PRODUCTION")) {
    define("PRODUCTION", false);
}

if (!defined("PLATEFORME_MAIL")) {
    define("PLATEFORME_MAIL", "test@libriciel.net");
}

if (!defined("TEMPLATE_PATH")) {
    define("TEMPLATE_PATH", __DIR__ . "/template/");
}

if (!defined("TIMEZONE")) {
    define("TIMEZONE", "Europe/Paris");
}


//Information pour le démon Pastell
//les informations sont celles par défaut pour une Ubuntu 14.04LTS  et la plupart des sytêmes Linux
if (!defined("NB_WORKERS")) {
    define("NB_WORKERS", 5);
}

if (!defined("PHP_PATH")) {
    define("PHP_PATH", '/usr/bin/php');
}

if (!defined("TESTING_ENVIRONNEMENT")) {
    define("TESTING_ENVIRONNEMENT", false);
}

if (!defined("DISABLE_JOB_QUEUE")) {
    define("DISABLE_JOB_QUEUE", false);
}

if (!defined("DISABLE_JOURNAL_HORODATAGE")) {
    define("DISABLE_JOURNAL_HORODATAGE", false);
}

/** Permet de savoir ce que l'on va archiver dans la table journal_historique */
if (!defined("JOURNAL_MAX_AGE_IN_MONTHS")) {
    define("JOURNAL_MAX_AGE_IN_MONTHS", 2);
}

/* pour ne pas verrouiller les jobs qui ne se sont pas terminés correctement. */
/* suite à un arrêt brutal du serveur (ex: restart apache sans avoir arrêté le daemon avec des worker actifs) */
if (!defined("UNLOK_JOB_ERROR_AT_STARTUP")) {
    define("UNLOK_JOB_ERROR_AT_STARTUP", false);
}

date_default_timezone_set(TIMEZONE);

setlocale(LC_TIME, "fr_FR.UTF-8");

/* Par défaut on utilise pas de serveur redis */
if (!defined("REDIS_SERVER")) {
    define("REDIS_SERVER", "");
}

if (!defined("REDIS_PORT")) {
    define("REDIS_PORT", 6379);
}

// Time to live des élements liste de flux, liste de connecteur, liste d'extension, rôle dans le cache Redis
// On ne relie pas les donnée de définition sur le disque quand ils sont dans le cache
// En développement, on mettra 1
// En production, on peut mettre 10 ou beaucoup plus
// Attention 0 signifie que le cache n'expire jamais !
// Mettre -1 pour désactiver le cache
if (!defined("CACHE_TTL_IN_SECONDS")) {
    define("CACHE_TTL_IN_SECONDS", 10);
}

if (!defined("LOG_FILE")) {
    define("LOG_FILE", "/data/log/pastell.log");
}

if (!defined("LOG_LEVEL")) {
    define("LOG_LEVEL", Monolog\Logger::INFO);
}

if (empty($logger)) {
    if (empty($logger_system)) {
        $logger_system = "PASTELL";
    }
    $logger = new Monolog\Logger($logger_system);
    $logger->pushHandler(new Monolog\Handler\StreamHandler(LOG_FILE, LOG_LEVEL));
    $logger->pushProcessor(function ($record) {
        $record['extra']['pid'] = getmypid();
        return $record;
    });
}

if (!defined("UPLOAD_CHUNK_DIRECTORY")) {
    define("UPLOAD_CHUNK_DIRECTORY", "/data/upload_chunk");
}

# Nb Job / verrou
if (!defined("NB_JOB_PAR_VERROU")) {
    define("NB_JOB_PAR_VERROU", 1);
}

if (!defined("RGPD_PAGE_PATH")) {
    define("RGPD_PAGE_PATH", __DIR__ . "/template/RGPD.md");
}

if (!defined('LOGIN_PAGE_CONFIGURATION')) {
    define('LOGIN_PAGE_CONFIGURATION', 'login_page_configuration');
}

if (!defined('HTML_PURIFIER_CACHE_PATH')) {
    define('HTML_PURIFIER_CACHE_PATH', '/data/html_purifier');
}
if (!defined('LOGIN_PAGE_CONFIGURATION_LOCATION')) {
    define(
        'LOGIN_PAGE_CONFIGURATION_LOCATION',
        WORKSPACE_PATH . DIRECTORY_SEPARATOR . LOGIN_PAGE_CONFIGURATION . '.json'
    );
}

if (!defined('SENTRY_DSN')) {
    define('SENTRY_DSN', '');
}
if (!defined('SENTRY_ENVIRONMENT')) {
    define('SENTRY_ENVIRONMENT', 'dev');
}

# A modifier uniquement pour les environnement docker en local (dev)
if (! defined('PES_VIEWER_URL')) {
    define('PES_VIEWER_URL', 'http://pes-viewer:8888');
}

if (!defined("HTTP_PROXY_URL")) {
    define("HTTP_PROXY_URL", "");
}

// Liste de nom d'hôtes séparés pas des virgules qui n'utiliseront pas le proxy
if (!defined("NO_PROXY")) {
    define("NO_PROXY", "localhost,127.0.0.1,::1,seda-generator,pes-viewer");
}

if (!defined("LIST_PACK")) {
    define("LIST_PACK", [
        "pack_chorus_pro" => false,
        "pack_marche" => false,
        "pack_urbanisme" => false
    ]);
}

if (!defined("PASSWORD_MIN_ENTROPY")) {
    // Voir https://www.ssi.gouv.fr/administration/precautions-elementaires/calculer-la-force-dun-mot-de-passe/
    // pour fixer l'entropie
    define("PASSWORD_MIN_ENTROPY", 80);
}

if (!defined("MAILER_DSN")) {
    define("MAILER_DSN", "null://null");
}

if (!defined("USE_UUID_FOR_DOCUMENT")) {
    define("USE_UUID_FOR_DOCUMENT", false);
}

//Pour config object storage sur MinIO
if (!defined("S3_URL")) {
    define("S3_URL", "http://minio:9000/");
}

if (!defined("S3_KEY")) {
    define("S3_KEY", "minioadmin");
}

if (!defined("S3_SECRET")) {
    define("S3_SECRET", "minioadmin");
}

if (!defined("S3_BUCKET_JOURNAL")) {
    define("S3_BUCKET_JOURNAL", "journal-preuves");
}

if (!defined("USE_EXTERNAL_STORAGE_FOR_JOURNAL_PROOF")) {
    define("USE_EXTERNAL_STORAGE_FOR_JOURNAL_PROOF", false);
}

if (!defined('RATE_LIMITER_VARIABLE')) {
    define('RATE_LIMITER_VARIABLE', 'REMOTE_ADDR');
}
