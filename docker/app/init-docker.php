<?php

declare(strict_types=1);

use Monolog\Logger;
use Pastell\Database\DatabaseUpdater;

try {
    echo "Initialisation de Pastell [DOCKER]\n";

# Première étape : Sans la connexion BD vu que celle-ci n'existe pas encore...
    require_once __DIR__ . '/../../init-no-db.php';

    echo \sprintf("Utilisation de la base %s avec l'utilisateur %s\n", BD_DSN, BD_USER);

    $sqlQuery = new SQLQuery(BD_DSN, BD_USER, BD_PASS);

    $sqlQuery->waitStarting(function ($message) {
        echo \sprintf("[%s][Pastell - wait for MySL] %s\n", date('Y-m-d H:i:s'), $message);
    });

    $query = 'SHOW TABLE STATUS WHERE Name = ?;';
    if (!$sqlQuery->queryOne($query, 'extension')) {
        /**
         * @var Logger $logger
         */
        $databaseUpdater = new DatabaseUpdater($sqlQuery, $logger);
        $databaseUpdater->update();
    }

# Deuxième étape : initialisation normale de Pastell
    require_once __DIR__ . '/../../init.php';
    ObjectInstancierFactory::getObjetInstancier()->getInstance(DatabaseUpdater::class)->update();
    $pastellBootstrap = ObjectInstancierFactory::getObjetInstancier()->getInstance(PastellBootstrap::class);
    $pastellBootstrap->bootstrap();
} catch (Exception $e) {
    echo $e->getMessage();
}
