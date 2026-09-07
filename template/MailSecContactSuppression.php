<?php

declare(strict_types=1);

/**
 * @var Gabarit $this
 * @var array $contacts_to_delete
 * @var int $id_e
 */

?>
<div class='alert alert-danger' style='margin-top:10px;'>
    L'action de <strong>suppression</strong> d'un contact est irréversible.
</div>

<div class="box">
    <h2>Les contacts suivants seront supprimés :</h2>
    <table class='table table-striped'>
        <thead>
        <tr>
            <th>Description</th>
            <th>Email</th>
        </tr>
        </thead>
        <?php foreach ($contacts_to_delete as $contact) : ?>
            <tr>
                <td><?= htmlspecialchars($contact['info']['description']) ?></td>
                <td><?= htmlspecialchars($contact['info']['email']) ?></td>
            </tr>
        <?php endforeach ?>
    </table>

    <form action='MailSec/doContactSuppression' method='post'>
        <?php $this->displayCSRFInput() ?>
        <input type='hidden' name='id_e' value='<?= $id_e ?>'/>
        <?php foreach ($contacts_to_delete as $contact) : ?>
            <input type='hidden' name='id_a[]' value='<?= $contact['id_a'] ?>'/>
        <?php endforeach ?>
        <div style='margin-top:20px;'>
            <a class='btn btn-outline-primary'
               href='<?php $this->url("MailSec/annuaire?id_e=$id_e"); ?>'
            ><i class="fas fa-circle-xmark"></i>&nbsp;Annuler</a>
            <button type='submit' class='btn btn-danger'>
                <i class="fas fa-trash"></i>&nbsp;Supprimer
            </button>
        </div>
    </form>
</div>
