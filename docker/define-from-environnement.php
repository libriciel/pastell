<?php

//Utilisé dans le cadre de PHPStorm qui ne permet pas de lancer des scripts après le démarrage du Docker
//et qui ne lance pas l'entrypoint
//Uniquement utilisé pour PHPUnit et Codeception donc

//Et bien sur, ca interagi mal avec gitlab-ci...

if (file_exists("/data/config/DockerSettings.php")) {
    require_once "/data/config/DockerSettings.php";
} elseif (! file_exists(__DIR__ . "/../LocalSettings.php")) {
    echo "LocalSettings n'existe pas : création à partir des variables d'environnement\n";
    $script = __DIR__ . "/app/docker-pastell-init";

    `/bin/bash $script > /tmp/DockerSettings.php`;

    require_once "/tmp/DockerSettings.php";
} else {
    require_once __DIR__ . "/../LocalSettings.php";
}
