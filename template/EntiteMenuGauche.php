<?php

/**
 * @var Gabarit $this
 * @var $menu_gauche_select
 * @var $droit_lecture_on_connecteur
 * @var $droitLectureOnUtilisateur
 * @var int $id_e
 * @var bool $permission_on_import_export;
 * @var bool $droitLectureAnnuaire;
 * @var int $global
 */

$admninistration_menu['Entite/detail'] = 'Informations (entités)';

if ($droitLectureOnUtilisateur) {
    $admninistration_menu['Entite/utilisateur'] = 'Utilisateurs';
}

if ($droit_lecture_on_connecteur) {
    $admninistration_menu['Entite/connecteur?global=0'] = 'Connecteurs' . ($id_e ? '' : " d'entités");
    if ($id_e === 0) {
        $admninistration_menu['Entite/connecteur?global=1'] = 'Connecteurs globaux';
    }
    $admninistration_menu['Flux/index'] = $id_e ? 'Types de dossier (association)' : 'Associations connecteurs globaux';
}

if (! empty($permission_on_import_export)) {
    $admninistration_menu['Entite/exportConfig'] = 'Export de la configuration';
    $admninistration_menu['Entite/importConfig'] = 'Import de la configuration';
}

if ($droitLectureAnnuaire) {
    $donnees_menu['MailSec/annuaire'] = 'Annuaire (mail sécurisé)';
}
$donnees_menu['Entite/agents'] = 'Agents (Actes)';

?>

<div id="main_gauche" class="ls-on">

    <h3 data-bs-toggle="collapse"
        data-bs-target="#collapse-0"
        aria-expanded="false"
        aria-controls="collapse-0">Administration</h3>
    <div id="collapse-0"
         class="menu collapse <?php hecho(
             array_key_exists($menu_gauche_select, $admninistration_menu) ? "show" : ""
         ); ?>">
        <ul>
            <?php foreach ($admninistration_menu as $url => $libelle) : ?>
                <li>
                    <a class="<?php echo $menu_gauche_select == $url ? "actif" : "" ?>"
                       href='<?php $this->url(get_hecho($url . (parse_url($url, PHP_URL_QUERY) ? '&' : '?')) . "id_e=$id_e"); ?>'>
                        <?php echo $libelle ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>

    <h3 data-bs-toggle="collapse"
        data-bs-target="#collapse-1"
        aria-expanded="false"
        aria-controls="collapse-1">Données pour les types de dossier</h3>
    <div id="collapse-1"
         class="menu collapse <?php hecho(
             array_key_exists($menu_gauche_select, $donnees_menu) ? "show" : ""
         ); ?>">
        <ul>
            <?php foreach ($donnees_menu as $url => $libelle) : ?>
                <li>
                    <a class="<?php echo $menu_gauche_select == $url ? "actif" : "" ?>"
                       href='<?php $this->url(get_hecho($url . "?id_e=$id_e")); ?>'><?php echo $libelle ?></a>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>
