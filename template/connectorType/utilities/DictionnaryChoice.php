<?php

/**
 * @var Gabarit $this
 * @var int $id_ce
 * @var string $field
 * @var array $dictionnary
 * @var string $selected_id
 * @var string $element_id;
 * @var string $element_name_libelle ;
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
        <table class='table table-striped'>
            <tr >
                <th class='w200'>
                    <label for='<?php hecho($element_id); ?>'><?php hecho($element_name_libelle) ?></label>
                </th>
                <td>
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
                </td>
            </tr>
        </table>
        <button type='submit' class='btn btn-primary'>
            <i class="fas fa-check"></i>&nbsp;Sélectionner
        </button>
    </form>
</div>
