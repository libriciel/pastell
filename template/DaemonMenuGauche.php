<?php

/**
 * @var Gabarit $this
 * @var string $menu_gauche_select
 */

$onglet_tab  = [
    'Daemon/index' => 'Gestionnaire de tâches',
    'Daemon/verrou' => "Files d'attente",
    'Daemon/job' => 'Tous les travaux',
    'Daemon/job?filtre=actif' => 'Travaux actifs',
    'Daemon/job?filtre=lock' => 'Travaux suspendus',
    'Daemon/job?filtre=wait' => 'Travaux en attente',
];
?>

<div id="main_gauche" class="ls-on">

    <h3 data-bs-toggle="collapse"
        data-bs-target="#collapse-0" aria-expanded="false" aria-controls="collapse-0"
    >Tâches automatiques</h3>
    <div class="menu collapse <?php hecho(array_key_exists($menu_gauche_select, $onglet_tab) ? 'show' : ''); ?>"
         id="collapse-0">
        <ul>
            <?php foreach ($onglet_tab as $onglet_url => $onglet_name) : ?>
                <li >
                    <a <?php echo ($onglet_url == $menu_gauche_select) ? 'class="actif"' : '' ?>
                        href='<?php echo $onglet_url?>'>
                        <?php echo $onglet_name?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>

    <h3 data-bs-toggle="collapse"
        data-bs-target="#collapse-1"
        aria-expanded="false"
        aria-controls="collapse-1"
    >Configuration</h3>
    <div class="menu collapse <?php hecho(('Daemon/frequenceConfig' == $menu_gauche_select) || ('Daemon/config' == $menu_gauche_select) ? 'show' : ''); ?>"
         id="collapse-1"
    >
        <ul>
            <li>
                <a <?php echo ('Daemon/frequenceConfig' == $menu_gauche_select) ? 'class="actif"' : '' ?>
                    href="<?php $this->url('Daemon/frequenceConfig'); ?>"
                >Fréquence des connecteurs</a>
            </li>
            <li>
                <a <?php echo ('Daemon/config' == $menu_gauche_select) ? 'class="actif"' : '' ?>
                        href="<?php $this->url('Daemon/config'); ?>"
                >Configuration des gestionnaires de tâches</a>
            </li>
        </ul>
    </div>


</div><!-- main_gauche  -->
