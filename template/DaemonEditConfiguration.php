<?php

declare(strict_types=1);

/** @var Gabarit $this */
/**
 * @var int $nb_workers
 */

?>

<div class="box">
    <form action='<?php $this->url('Daemon/doEditConfiguration') ?>' method='post' >
        <?php $this->displayCSRFInput() ?>
        <table class='table table-striped'>
            <tr>
                <th class='w200'>
                    <label for="type_connecteur">Nombre de processus disponibles sur pastell</label>
                </th>
                <td>
                    <input type="number" name="nb_workers" id="nb_workers" class="form-control col-md-4" value="<?= $nb_workers?>" min="1"/>
                </td>
            </tr>
        </table>
        <a class='btn btn-outline-primary' href='<?php $this->url('Daemon/configuration') ?>'>
            <i class="fas fa-circle-xmark"></i>&nbsp;
            Annuler</a>

        <button type="submit" class="btn btn-primary" id="daemonedit-frequence-enregistrer">
            <i class="fas fa-floppy-disk"></i>&nbsp;Enregistrer
        </button>
    </form>
</div>

