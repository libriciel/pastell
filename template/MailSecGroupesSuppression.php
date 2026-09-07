<?php

declare(strict_types=1);

/**
 * @var Gabarit $this
 * @var array $groupes_to_delete
 * @var int $id_e
 */

?>
<div class='alert alert-danger' style='margin-top:10px;'>
    L'action de <strong>suppression</strong> d'un groupe est irréversible.
</div>

<div class="box">
    <h2>Les groupes suivants seront supprimés :</h2>
    <table class='table table-striped'>
        <thead>
        <tr>
            <th>Nom</th>
            <th>Nombre de contacts</th>
        </tr>
        </thead>
        <?php foreach ($groupes_to_delete as $groupe) : ?>
            <tr>
                <td><?= htmlspecialchars($groupe['info']['nom']) ?></td>
                <td><?= (int)$groupe['nb_contacts'] ?></td>
            </tr>
        <?php endforeach ?>
    </table>

    <form action='MailSec/doGroupeSuppression' method='post'>
        <?php $this->displayCSRFInput() ?>
        <input type='hidden' name='id_e' value='<?= $id_e ?>'/>
        <?php foreach ($groupes_to_delete as $groupe) : ?>
            <input type='hidden' name='id_g[]' value='<?= $groupe['id_g'] ?>'/>
        <?php endforeach ?>
        <div style='margin-top:20px;'>
            <a class='btn btn-outline-primary'
               href='<?php $this->url("MailSec/groupeList?id_e=$id_e"); ?>'
            ><i class="fas fa-circle-xmark"></i>&nbsp;Annuler</a>
            <button type='submit' class='btn btn-danger'>
                <i class="fas fa-trash"></i>&nbsp;Supprimer
            </button>
        </div>
    </form>
</div>
