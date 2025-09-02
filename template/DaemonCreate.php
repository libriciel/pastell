<?php

declare(strict_types=1);

/**
 * @var Gabarit $this
 * @var int $nb_free_workers
 * @var array $tree
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
                    <input id='role-entity_id' type='hidden' name='id_e' value=''/>
                    <div class="treeselect-role-entity"></div>
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
                           name="nb_allocated_workers" value="0" min="0" max="<?= $nb_free_workers ?>"
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
            <i class="fa fa-times-circle"></i>&nbsp;Annuler
        </a>
        <button type="submit" class="btn btn-primary">
            <i class="fa fa-save"></i>&nbsp;Enregistrer
        </button>

    </form>
</div>

<script type="module">
    const domElement = document.querySelector('.treeselect-role-entity')
    const treeselect = new Treeselect({
        placeholder: 'Sélectionner une entité',
        parentHtmlContainer: domElement,
        options: <?php echo $tree; ?>,
        isSingleSelect: true,
        showTags: false,
        openLevel: 3
    })

    treeselect.srcElement.addEventListener('input', (e) => {
        document.getElementById('role-entity_id').value = e.detail;
    })
</script>
