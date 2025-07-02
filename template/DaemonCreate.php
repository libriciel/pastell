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
                    <label for="type_connecteur">Entité</label>
                </th>
                <td>
                    <input id='role-entity_id' type='hidden' name='id_e' value=''/>
                    <div class="treeselect-role-entity"></div>
                </td>
            </tr>
            <tr>
                <th class='w300'>
                    <label for="type_connecteur">Nombre de processus à allouer</label>
                    <p class="form_commentaire">
                        <?=$nb_free_workers . ($nb_free_workers > 1 ? ' workers disponibles' : ' worker disponible')?>
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
                    <label for="daemon_admin_email">Mail d'administration</label>
                    <p class="form_commentaire">
                        Ce mail sert à notifier les erreurs des tâches automatiques de l'entité. <br>
                        Plusieurs mails peuvent être renseignés, séparés par des virgules.
                    </p>
                </th>
                <td>
                    <input class="form-control col-md-4" id='daemon_admin_email' type="text" name='daemon_admin_email' value=''/>
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
