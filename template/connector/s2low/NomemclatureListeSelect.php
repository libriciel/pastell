<?php

/**
 * @var Gabarit $this
 * @var int $id_ce
 * @var array $classifCDG
 * @var string $field
 */
?>
<a class='btn btn-link' href='<?php $this->url("Connecteur/editionModif?id_ce=$id_ce") ?>'><i class="fas fa-arrow-left"></i>&nbsp;Retour à la définition du connecteur</a>

<div class="box">
<h2>Choix de la nomenclature CDG</h2>

<table class='table table-striped'>
    <?php
    foreach ($classifCDG as $i => $info) : ?>
        <tr>
            <td class="w30">        
            <a href='Connecteur/doExternalData?id_ce=<?php echo $id_ce?>&field=<?php echo $field ?>&nomemclature_file=<?php hecho($info) ?>'><?php echo $info?></a>
            </td>       
        </tr>
    <?php endforeach;?>
    <tr>
        <td class="w30">        
            <a href='Connecteur/doExternalData?id_ce=<?php echo $id_ce?>&field=<?php echo $field ?>&nomemclature_file='>Supprimer le fichier </a>
        </td>
    </tr>
</table>

</div>