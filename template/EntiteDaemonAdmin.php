<?php

declare(strict_types=1);

/**
 * @var string $daemon_admin_email
 * @var int $daemon_late_jobs_threshold
 * @var int $id_e
 */

?>
<div class="box">
    <form action='<?php $this->url("Entite/doDaemonAdmin?id_e=$id_e"); ?>' method='post' >
        <table class='table table-striped'>
            <tr>
                <th class='w300'>
                    <label for="daemon_admin_email">Mail(s) d'administration <span class="obl">*</span></label>
                    <p class="form_commentaire">
                        Ce mail sert à notifier les erreurs des tâches automatiques de l'entité. <br>
                        Plusieurs mails peuvent être renseignés, séparés par des virgules.
                    </p>
                </th>
                <td>
                    <input class="form-control col-md-4" id='daemon_admin_email' type="text" name='daemon_admin_email'
                           value='<?= $daemon_admin_email ?>' required/>
                </td>
            </tr>
            <tr>
                <th class='w300'>
                    <label for="daemon_admin_email">Seuil d'alerte des tâches en retard <span
                                class="obl">*</span></label>
                    <p class="form_commentaire">
                        Nombre de tâches en retard minimum avant envoi d'une notification aux mails d'administration.
                    </p>
                </th>
                <td>
                    <input class="form-control col-md-4" id='daemon_late_jobs_threshold' type="number"
                           name='daemon_late_jobs_threshold' value='<?= $daemon_late_jobs_threshold ?>' min='1' required/>
                </td>
            </tr>
        </table>
        <?php $this->displayCSRFInput() ?>
        <button type="submit" class="btn btn-primary">
            <i class="fas fa-floppy-disk"></i>&nbsp;Enregistrer
        </button>
    </form>
</div>