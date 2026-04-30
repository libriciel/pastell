<?php

/** @var Gabarit $this */
?>
<div class="box">

<table class="table table-striped">
        <tr>
                <th class="w200">Type de connecteur</th>
                <th class="w200">Connecteur</th>
                <th>&nbsp;</th>
        </tr>
<?php
$i = 0;

foreach ($all_connecteur_type as $connecteur_type => $global_connecteur) :
    ?>
    <tr>
        <td><?php echo $connecteur_type;?></td>
        <td>
            <?php if ($global_connecteur) : ?>
            <a href='<?php $this->url("Connecteur/edition?id_ce={$all_flux_global[$connecteur_type][0]['id_ce']}") ?>'><?php hecho($all_flux_global[$connecteur_type][0]['libelle']) ?></a>
                &nbsp;(<?php hecho($all_flux_global[$connecteur_type][0]['id_connecteur']) ?>)
            <?php else :?>
            AUCUN
            <?php endif;?>  
        </td>
        <td>
            <a class='btn btn-primary' href='<?php $this->url("Flux/edition?id_e={$id_e}&type={$connecteur_type}"); ?>'><i class="fas fa-link"></i>&nbsp; Associer</a>
        </td>
    </tr>
<?php endforeach;?>

</table>
</div>