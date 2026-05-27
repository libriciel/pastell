<?php

/**
 * @var Gabarit $this
 * @var int $id_ce
 * @var string $field
 * @var string $selectedEntity
 * @var string $entity_treeselect_data
 */

?>
<a class='btn btn-link' href='Connecteur/editionModif?id_ce=<?php hecho((string)$id_ce); ?>'>
    <i class="fas fa-arrow-left"></i>&nbsp;Retour au connecteur
</a>
<div class="box">

<form action='Connecteur/doExternalData' method='post'>
    <input type='hidden' name='id_ce' value='<?php hecho((string)$id_ce); ?>'/>
    <input type='hidden' name='field' value='<?php hecho($field); ?>'/>
    <?php $this->displayCSRFInput(); ?>

    <table class="table table-striped">
        <tr>
            <th class="w300">Entité</th>
            <td>
                <input id='get-entity-list_id' type='hidden' name='entity_id' value='<?php hecho($selectedEntity); ?>'/>
                <div class="treeselect-get-entity-list col-md-4"></div>
                <?php $this->renderTreeSelect($entity_treeselect_data, 'treeselect-get-entity-list', 'get-entity-list_id', 'Sélectionner une entité', 3); ?>
            </td>
        </tr>
    </table>
    <button type='submit' class='btn btn-primary'>
        <i class="fas fa-check"></i>&nbsp;Choisir
    </button>
</form>
</div>
