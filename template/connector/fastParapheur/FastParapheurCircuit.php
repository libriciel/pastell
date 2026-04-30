<?php

/**
 * @var Gabarit $this
 * @var string $id_d
 * @var int $id_e
 * @var int $page
 * @var string $field
 * @var array $circuits
 */
?>

<div class="box">
    <form action='Document/doExternalData' method='post'>
        <?php $this->displayCSRFInput() ?>
        <input type='hidden' name='id_d' value='<?php echo $id_d ?>'/>
        <input type='hidden' name='id_e' value='<?php echo $id_e ?>'/>
        <input type='hidden' name='page' value='<?php echo $page ?>'/>
        <input type='hidden' name='field' value='<?php echo $field ?>'/>
        <table class='table table-striped'>

            <tr>
                <th class='w200'>
                    <label for="fast_parapheur_circuit">Circuit Parapheur FAST</label>
                </th>
                <td>
                    <select name='fast_parapheur_circuit' id="fast_parapheur_circuit" class="form-select col-md-2">
                        <?php foreach ($circuits as $num => $type_message) : ?>
                            <option value='<?php hecho($type_message) ?>'><?php hecho($type_message) ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>

        </table>

        <a class='btn btn-outline-primary'
           href='<?php $this->url("Document/edition?id_d=$id_d&id_e=$id_e&page=$page") ?>'>
            <i class="fas fa-circle-xmark"></i>&nbsp;Annuler
        </a>

        <button type='submit' class='btn btn-primary' id="valider">
            <i class="fas fa-check"></i>&nbsp;Valider
        </button>
    </form>
</div>
