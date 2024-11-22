<?php

/** @deprecated Since 4.1.4, Use app:user:refresh-role-admin and app:user:create-admin-user command instead */

//Crée un admin (crée aussi le rôle admin et fixe les droits si il n'existe pas

use Pastell\Service\TokenGenerator;

require_once __DIR__ . '/../init.php';

$login = get_argv(1);
$email = get_argv(2);

$adminControler = ObjectInstancierFactory::getObjetInstancier()->getInstance(AdminControler::class);
$password = (new TokenGenerator())->generate();

$result = $adminControler->createAdmin($login, $password, $email);

if ($result) {
        echo "Administrateur $login créé avec succès\n";
        echo "Mot de passe : $password\n";
} else {
    echo $adminControler->getLastError()->getLastError() . "\n";
    echo "Usage : {$argv[0]} login email\n";
    exit;
}

$adminControler->fixDroit();
