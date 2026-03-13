<?php

declare(strict_types=1);

/**
 * @var Gabarit $this
 * @var int $nb_free_workers
 */

?>
<div class="box">
    <form action='<?php $this->url('Daemon/doCreate'); ?>' method='post' >
        <table class='table table-striped'>
            <tr>
                <th class='w300'>
                    <label for="type_connecteur">Entité <span class="obl">*</span></label>
                </th>
                <td>
                    <input id='daemon-entity_id' type='hidden' name='id_e' value=''/>
                    <div class="treeselect-daemon-entity"></div>
                    <?php
                    $this->setViewParameter('treeselect_container_class', 'treeselect-daemon-entity');
                    $this->setViewParameter('treeselect_input_id', 'daemon-entity_id');
                    $this->render('EntityTreeSelect');
                    ?>
                </td>
            </tr>
            <tr>
                <th class='w300'>
                    <label for="type_connecteur">Nombre de processus à allouer <span class="obl">*</span></label>
                    <p class="form_commentaire">
                        <?= $nb_free_workers . ' processus ' . ($nb_free_workers > 1 ? 'disponibles' : 'disponible') ?>
                    </p>
                </th>
                <td>
                    <input class="form-control col-md-4" type="number" id="nb_allocated_workers"
                           name="nb_allocated_workers" value="1" min="1" max="<?= $nb_free_workers ?>"
                    />
                </td>
            </tr>
            <tr>
                <th class='w300'>
                    <label for="daemon_admin_email">Mail(s) d'administration <span class="obl">*</span></label>
                    <p class="form_commentaire">
                        Ce mail sert à notifier les erreurs des tâches automatiques de l'entité. <br>
                        Plusieurs mails peuvent être renseignés, séparés par des virgules.
                    </p>
                </th>
                <td>
                    <input class="form-control col-md-4" id='daemon_admin_email' type="text" name='daemon_admin_email' value='' required/>
                </td>
            </tr>
            <tr>
                <th class='w300'>
                    <label for="daemon_admin_email">Seuil d'alerte des tâches en retard <span class="obl">*</span></label>
                    <p class="form_commentaire">
                        Nombre de tâches en retard minimum avant envoi d'une notification aux mails d'administration.
                    </p>
                </th>
                <td>
                    <input class="form-control col-md-4" id='daemon_late_jobs_threshold' type="number" name='daemon_late_jobs_threshold' value='1' required/>
                </td>
            </tr>
        </table>
        <?php $this->displayCSRFInput() ?>
        <a class='btn btn-outline-primary' href='<?php $this->url('Daemon/configuration')?>'>
            <i class="fas fa-circle-xmark"></i>&nbsp;Annuler
        </a>
        <button type="submit" class="btn btn-primary">
            <i class="fas fa-floppy-disk"></i>&nbsp;Enregistrer
        </button>

    </form>
</div>
