<?php

/**
 * @var Gabarit $this
 * @var int $id_ce
 * @var string $field
 * @var array $dictionnary
 * @var string $selected_id
 * @var string $element_id;
 */

?>

<form action='Connecteur/doExternalData' method='post'>
    <input type='hidden' name='id_ce' value='<?php hecho((string)$id_ce); ?>'/>
    <input type='hidden' name='field' value='<?php hecho($field); ?>'/>
    <?php $this->displayCSRFInput(); ?>
    <select name='<?php hecho($element_id); ?>'
            class='select2_entite form-select col-md-1'
            aria-label="choisir une valeur">
        <?php foreach ($dictionnary as $keyId => $value) : ?>
            <option
                value='<?php echo $keyId ?>'
                <?php echo $selected_id === $keyId ? 'selected' : '' ?>
            >
                <?php hecho($value); ?>
            </option>
        <?php endforeach; ?>
    </select>
    <button type='submit' class='btn btn-primary'>
        <i class="fa fa-check"></i>&nbsp;Choisir
    </button>
</form>
