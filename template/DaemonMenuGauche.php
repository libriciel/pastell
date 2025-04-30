<?php

/**
 * @var Gabarit $this
 * @var string $menu_gauche_select
 */

$taches_automatiques_tab  = [
    'Daemon/index' => 'Gestionnaire de tâches',
    'Daemon/verrou' => "Files d'attente",
    'Daemon/job' => 'Tous les travaux',
    'Daemon/job?filtre=actif' => 'Travaux actifs',
    'Daemon/job?filtre=lock' => 'Travaux suspendus',
    'Daemon/job?filtre=wait' => 'Travaux en attente',
];
$collapse_taches_automatiques = !array_key_exists($menu_gauche_select, $taches_automatiques_tab);

$configuration_tab = [
    'Daemon/frequenceConfiguration' => 'Fréquence des connecteurs',
    'Daemon/configuration' => 'Configuration des gestionnaires de tâches',
];
$collapse_configuration = !array_key_exists($menu_gauche_select, $configuration_tab);

?>

<div id="main_gauche" class="ls-on">

    <h3 class="<?= ($collapse_taches_automatiques) ? 'collapsed' : '' ?>"
        data-bs-toggle="collapse"
        data-bs-target="#collapse-0"
        aria-expanded="<?= $collapse_taches_automatiques ? 'false' : 'true' ?>"
        aria-controls="collapse-0"
    >Tâches automatiques</h3>
    <div class="menu collapse <?php hecho(array_key_exists($menu_gauche_select, $taches_automatiques_tab) ? 'show' : ''); ?>"
         id="collapse-0">
        <ul>
            <?php foreach ($taches_automatiques_tab as $onglet_url => $onglet_name) : ?>
                <li >
                    <a <?= ($onglet_url === $menu_gauche_select) ? 'class="actif"' : '' ?>
                            href='<?= $onglet_url ?>'>
                        <?= $onglet_name ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>

    <h3 class=" <?= ($collapse_configuration) ? 'collapsed' : '' ?>"
        data-bs-toggle="collapse"
        data-bs-target="#collapse-1"
        aria-expanded="<?= $collapse_configuration ? 'false' : 'true'?>"
        aria-controls="collapse-1"
    >Configuration</h3>
    <div class="menu collapse <?= ($collapse_configuration) ? '' : 'show' ?>"
         id="collapse-1">
        <ul>
            <?php foreach ($configuration_tab as $onglet_url => $onglet_name) : ?>
                <li>
                    <a <?= ($onglet_url === $menu_gauche_select) ? 'class="actif"' : '' ?>
                            href='<?= $onglet_url?>'>
                        <?= $onglet_name?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>


</div><!-- main_gauche  -->
