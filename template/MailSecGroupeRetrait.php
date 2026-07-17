<?php

declare(strict_types=1);

/**
 * @var Gabarit $this
 * @var array $contacts_to_delete
 * @var int $id_e
 * @var int $id_g
 * @var array $infoGroupe
 */

?>
<div class='alert alert-danger' style='margin-top:10px;'>
    L'action de <strong>retrait</strong> d'un contact du groupe est irréversible.
</div>

<div class="box">
    <h2>Les contacts suivants seront retirés du groupe «<?= htmlspecialchars($infoGroupe['nom']) ?>» :</h2>
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

    <form action='MailSec/doGroupeRetrait' method='post'>
        <?php $this->displayCSRFInput() ?>
        <input type='hidden' name='id_e' value='<?= $id_e ?>'/>
        <input type='hidden' name='id_g' value='<?= $id_g ?>'/>
        <?php foreach ($contacts_to_delete as $contact) : ?>
            <input type='hidden' name='id_a[]' value='<?= $contact['id_a'] ?>'/>
        <?php endforeach ?>
        <div style='margin-top:20px;'>
            <a class='btn btn-outline-primary'
               href='<?php $this->url("MailSec/groupeDetail?id_e=$id_e&id_g=$id_g"); ?>'
            ><i class="fas fa-circle-xmark"></i>&nbsp;Annuler</a>
            <button type='submit' class='btn btn-danger'>
                <i class="fas fa-trash"></i>&nbsp;Retirer
            </button>
        </div>
    </form>
</div>
