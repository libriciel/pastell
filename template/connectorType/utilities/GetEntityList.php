<?php

/**
 * @var Gabarit $this
 * @var int $id_ce
 * @var string $field
 * @var array $entityList
 * @var string $selectedEntity
 */

?>

<form action='Connecteur/doExternalData' method='post'>
    <input type='hidden' name='id_ce' value='<?php hecho((string)$id_ce); ?>'/>
    <input type='hidden' name='field' value='<?php hecho($field); ?>'/>
    <?php $this->displayCSRFInput(); ?>

    <select name='entity_id' class='select2_entite form-select col-md-1'>
        <?php foreach ($entityList as $entiteInfo) : ?>
            <option
                    value='<?php echo $entiteInfo['id_e'] ?>'
                <?php echo $selectedEntity == $entiteInfo['id_e'] ? 'selected' : '' ?>
            >
                <?php echo str_repeat("-", $entiteInfo['profondeur']); ?>
                <?php hecho($entiteInfo['denomination'] . ' ( id_e=' . $entiteInfo['id_e'] . ')'); ?>
            </option>
        <?php endforeach; ?>
    </select>
    <button type='submit' class='btn btn-primary'>
        <i class="fa fa-check"></i>&nbsp;Choisir
    </button>
</form>
