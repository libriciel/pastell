<?php

declare(strict_types=1);

/**
 * @var string $daemon_admin_email
 * @var int $id_e
 */

?>
<div class="box">
    <form action='<?php $this->url("Entite/doDaemonAdmin?id_e=$id_e"); ?>' method='post' >
        <table class='table table-striped'>
            <tr>
                <th class='w300'>
                    <label for="daemon_admin_email">Mail d'administration <span class="obl">*</span></label>
                    <p class="form_commentaire">
                        Ce mail sert à notifier les erreurs des tâches automatiques de l'entité. <br>
                        Plusieurs mails peuvent être renseignés, séparés par des virgules.
                    </p>
                </th>
                <td>
                    <input class="form-control col-md-4" id='daemon_admin_email' type="text" name='daemon_admin_email' value='<?= $daemon_admin_email ?>' required/>
                </td>
            </tr>
        </table>
        <?php $this->displayCSRFInput() ?>
        <button type="submit" class="btn btn-primary">
            <i class="fa fa-save"></i>&nbsp;Enregistrer
        </button>
    </form>
</div>