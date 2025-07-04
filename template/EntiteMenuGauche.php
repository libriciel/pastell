<?php

/**
 * @var Gabarit $this
 * @var string $menu_gauche_select
 * @var bool $droit_lecture_on_connecteur
 * @var bool $droitLectureOnUtilisateur
 * @var int $id_e
 * @var bool $permission_on_import_export
 * @var bool $droitLectureAnnuaire
 * @var bool $daemon_lecture
 * @var bool $daemon_exists
 */

$admin_tabs = ['Entite/detail' => 'Informations (entités)'];

if ($droitLectureOnUtilisateur) {
    $admin_tabs['Entite/utilisateur'] = 'Utilisateurs';
}

if ($droit_lecture_on_connecteur) {
    $admin_tabs['Entite/connecteur?global=0'] = 'Connecteurs' . ($id_e ? '' : " d'entités");
    if ($id_e === 0) {
        $admin_tabs['Entite/connecteur?global=1'] = 'Connecteurs globaux';
    }
    $admin_tabs['Flux/index'] = $id_e ? 'Types de dossier (association)' : 'Associations connecteurs globaux';
}

if (!empty($permission_on_import_export)) {
    $admin_tabs['Entite/exportConfig'] = 'Export de la configuration';
    $admin_tabs['Entite/importConfig'] = 'Import de la configuration';
}
$collapse_admin = !array_key_exists($menu_gauche_select, $admin_tabs);

$daemon_tabs = [];
if (($daemon_exists || $id_e === 0) && $daemon_lecture) {
    $daemon_tabs = [
        'Entite/daemon' => 'Gestionnaire de tâches',
        'Entite/job' => 'Tous les travaux',
        'Entite/job?filtre=actif' => 'Travaux actifs',
        'Entite/job?filtre=lock' => 'Travaux suspendus',
        'Entite/job?filtre=wait' => 'Travaux en attente',
    ];
}
$collapse_daemon = !array_key_exists($menu_gauche_select, $daemon_tabs);

$donnees_tabs = [];
if ($droitLectureAnnuaire) {
    $donnees_tabs['MailSec/annuaire'] = 'Annuaire (mail sécurisé)';
}
$donnees_tabs['Entite/agents'] = 'Agents (Actes)';
$collapse_donnees = !array_key_exists($menu_gauche_select, $donnees_tabs);

?>

<div id="main_gauche" class="ls-on">

    <h3 class="<?= $collapse_admin ? 'collapsed' : '' ?>"
        data-bs-toggle="collapse"
        data-bs-target="#collapse-0"
        aria-expanded="<?= $collapse_admin ? 'false' : 'true' ?>"
        aria-controls="collapse-0">
        Administration
    </h3>
    <div class="menu collapse <?= $collapse_admin ? '' : 'show' ?>" id="collapse-0">
        <ul>
            <?php foreach ($admin_tabs as $url => $libelle) : ?>
                <li>
                    <a <?= ($menu_gauche_select === $url) ? 'class="actif"' : '' ?>
                            href='<?= get_hecho($url . (parse_url($url, PHP_URL_QUERY) ? '&' : '?')) . "id_e=$id_e" ?>'>
                        <?= $libelle ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>

    <?php if (!empty($daemon_tabs)) : ?>
        <h3 class="<?= $collapse_daemon ? 'collapsed' : '' ?>"
            data-bs-toggle="collapse"
            data-bs-target="#collapse-1"
            aria-expanded="<?= $collapse_daemon ? 'false' : 'true' ?>"
            aria-controls="collapse-1">
            Tâches automatiques
        </h3>
        <div class="menu collapse <?= $collapse_daemon ? '' : 'show' ?>" id="collapse-1">
            <ul>
                <?php foreach ($daemon_tabs as $url => $libelle) : ?>
                    <li>
                        <a <?= ($menu_gauche_select === $url) ? 'class="actif"' : '' ?>
                                href='<?= get_hecho($url . (parse_url($url, PHP_URL_QUERY) ? '&' : '?')) . "id_e=$id_e" ?>'>
                            <?= $libelle ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <h3 class="<?= $collapse_donnees ? 'collapsed' : '' ?>"
        data-bs-toggle="collapse"
        data-bs-target="#collapse-2"
        aria-expanded="<?= $collapse_donnees ? 'false' : 'true' ?>"
        aria-controls="collapse-2">
        Données pour les types de dossier
    </h3>
    <div class="menu collapse <?= $collapse_donnees ? '' : 'show' ?>" id="collapse-2">
        <ul>
            <?php foreach ($donnees_tabs as $url => $libelle) : ?>
                <li>
                    <a <?= ($menu_gauche_select === $url) ? 'class="actif"' : '' ?>
                            href='<?= get_hecho($url . "?id_e=$id_e") ?>'>
                        <?= $libelle ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>

</div>
