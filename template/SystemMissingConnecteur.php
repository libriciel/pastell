<?php

/**
* @var array $connecteur_manquant_list
 */
?>
<div class="box">

    <a href="<?php $this->url("/System/exportAllMissingConnecteur"); ?>" class="btn btn-primary"><i class='fas fa-upload'></i>&nbsp;Exporter tous les connecteurs</a>
    (Attention, les fichiers associés sont exportés séparément)
    <br/><br/>
<?php foreach ($connecteur_manquant_list as $connecteur_id => $connecteur_list) : ?>
    <h2><?php hecho($connecteur_id);?></h2>
    <table class='table table-striped' >
        <tr>
            <th class="w300">Entité</th>
            <th>Connecteur</th>
        </tr>
        <?php foreach ($connecteur_list as $connecteur_info) :?>
            <tr>
                <td>
                    <a href="<?php $this->url("/Entite/detail?id_e={$connecteur_info['id_e']}"); ?>">
                        <?php hecho($connecteur_info['denomination'] ?: 'Entité racine'); ?>
                    </a>
                </td>
                <td>
                    <a href="<?php $this->url("/Connecteur/edition?id_ce={$connecteur_info['id_ce']}"); ?>">
                        <?php hecho($connecteur_info['libelle'] ?: 'pas de libelle'); ?>
                    </a>
                </td>

            </tr>
        <?php endforeach; ?>
    </table>
    <br/>
<?php endforeach; ?>
</div>