<?php

/**
 * @var Gabarit $this
 * @var bool $droit_edition
 * @var bool $droit_lecture_on_connecteur
 * @var array $all_connecteur
 * @var int $id_e
 * @var int $global
 */
?>
<div class="box">
    <?php if ($droit_edition) : ?>
<a href="<?php $this->url("Connecteur/new?global=$global&id_e=$id_e") ?>" class='btn btn-primary grow'>
    <i class="fas fa-plus-circle"></i>&nbsp; Ajouter
</a>
    <?php endif;?>



<table class="table table-striped">
    <tr>
        <th>Famille de connecteur</th>
        <th>Connecteur</th>
        <th>Instance</th>
        <th>&nbsp;</th>
    </tr>
<?php foreach ($all_connecteur as $i => $connecteur) : ?>
    <tr>
        <td><?php echo $connecteur['type'];?></td>
        <td><?php echo $all_connecteur_definition[$connecteur['id_connecteur']]['nom'] ?? ""?> (<?php  echo $connecteur['id_connecteur'];  ?>)</td>
        <td><?php hecho($connecteur['libelle']);?></td>
        <td>
            <?php if ($droit_edition) : ?>
                <a class='btn btn-primary'
                   href='<?php $this->url("Connecteur/edition?id_ce={$connecteur['id_ce']}") ?>'
                >
                    <i class="fas fa-pen"></i> Modifier
                </a>
            <?php elseif ($droit_lecture_on_connecteur) : ?>
                <a class='btn btn-primary'
                   href='<?php $this->url("Connecteur/edition?id_ce={$connecteur['id_ce']}") ?>'
                >
                    <i class="fas fa-eye"></i> Voir
                </a>
            <?php endif;?>
        </td>
    </tr>
<?php endforeach;?>
</table>

</div>
