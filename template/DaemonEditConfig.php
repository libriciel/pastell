<?php

declare(strict_types=1);

/**
 * @var int $nb_workers
 */

?>

<div class="box">
    <form action='<?php $this->url('Daemon/doEditConfig') ?>' method='post' >
        <?php $this->displayCSRFInput() ?>
        <table class='table table-striped'>
            <tr>
                <th class='w200'>
                    <label for="type_connecteur">Nombre de processus disponibles sur pastell</label>
                </th>
                <td>
                    <input type="number" name="nb_workers" id="nb_workers" class="form-control col-md-4" value="<?= $nb_workers?>"/>
                </td>
            </tr>
        </table>
        <a class='btn btn-outline-primary' href='<?php $this->url('Daemon/config') ?>'>
            <i class="fa fa-times-circle"></i>&nbsp;
            Annuler</a>

        <button type="submit" class="btn btn-primary" id="daemonedit-frequence-enregistrer">
            <i class="fa fa-floppy-o"></i>&nbsp;Enregistrer
        </button>
    </form>
</div>

