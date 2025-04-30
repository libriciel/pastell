<?php

declare(strict_types=1);

/**
 * @var Gabarit $this
 * @var int $id_e
 * @var int $nb_free_workers
 */

?>

<div class="box">
    <form action='<?php $this->url("Daemon/doCreate?id_e=$id_e"); ?>' method='post' >
        <table class='table table-striped'>
            <tr>
                <th class='w300'>
                    <label for="type_connecteur">Nombre de processus à allouer</label>
                    <p class="form_commentaire">
                        <?=$nb_free_workers . ($nb_free_workers > 1 ? ' workers disponibles' : ' worker disponible')?>
                    </p>
                </th>
                <td>
                    <input type="number" id="nb_allocated_workers" name="nb_allocated_workers" value="0" min="0" max="<?= $nb_free_workers ?>" />
                </td>
            </tr>
        </table>
        <?php $this->displayCSRFInput() ?>
        <input type='hidden' name='id_e' value='<?= $id_e?>' />
        <a class='btn btn-outline-primary' href='<?php $this->url('Daemon/configuration')?>'>
            <i class="fa fa-times-circle"></i>&nbsp;Annuler
        </a>
        <button type="submit" class="btn btn-primary">
            <i class="fa fa-save"></i>&nbsp;Enregistrer
        </button>

    </form>
</div>
