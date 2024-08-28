<?php

/**
 * @var Gabarit $this
 * @var int $id_ce
 * @var string $field
 * @var array $type_iparapheur
 */
?>

<a class='btn btn-link' href='Connecteur/editionModif?id_ce=<?php echo $id_ce?>'>
    <i class="fa fa-arrow-left"></i>&nbsp;Retour au connecteur
</a>
<div class="box">
    <form action='Connecteur/doExternalData' method='post'>
        <?php $this->displayCSRFInput();?>
        <input type='hidden' name='id_ce' value='<?php echo $id_ce?>' />
        <input type='hidden' name='field' value='<?php echo $field?>' />
        <table class='table table-striped'>

            <tr >
                <th class='w200'>    <label for="iparapheur_sous_type">Type iparapheur</label>
                </th>
                <td>
                    <select name='iparapheur_type' class="form-select col-md-2">
                        <?php foreach ($type_iparapheur as $num => $type_message) : ?>
                            <option value='<?php hecho($type_message) ?>'><?php hecho($type_message)?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
        </table>

        <button type='submit' class='btn btn-primary' id="valider">
            <i class="fa fa-check"></i>&nbsp;Sélectionner
        </button>
    </form>
</div>