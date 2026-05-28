<?php

declare(strict_types=1);

/**
 * @var Gabarit $this
 * @var array $users_to_delete
 * @var int $id_e
 * @var string $source
 */

?>
<div class='alert alert-danger' style='margin-top:10px;'>
    L'action de <strong>suppression</strong> d'un utilisateur est irréversible.
    Les utilisateurs supprimés n'apparaîtront plus dans le journal d'événement.
</div>

<div class="box">
    <h2>Les utilisateurs suivants seront supprimés :</h2>
    <table class='table table-striped'>
        <thead>
        <tr>
            <th>Prénom Nom</th>
            <th>Login</th>
            <th>Email</th>
        </tr>
        </thead>
        <?php foreach ($users_to_delete as $user) : ?>
            <tr>
                <td><?= $user['info']['prenom'] . ' ' . $user['info']['nom'] ?></td>
                <td><?= $user['info']['login'] ?></td>
                <td><?= $user['info']['email'] ?></td>
            </tr>
        <?php endforeach ?>
    </table>

    <form action='Utilisateur/doSuppression' method='post'>
        <?php $this->displayCSRFInput() ?>
        <input type='hidden' name='id_e' value='<?= $id_e ?>'/>
        <input type='hidden' name='source' value='<?= $source ?>'/>
        <?php foreach ($users_to_delete as $user) : ?>
            <input type='hidden' name='id_u_list[]' value='<?= $user['id_u'] ?>'/>
        <?php endforeach ?>
        <div style='margin-top:20px;'>
            <a
                class='btn btn-outline-primary'
                href='<?php $this->url(
                    $source === 'list' ?
                        "Entite/utilisateur?id_e=$id_e" :
                        "Utilisateur/detail?id_u={$users_to_delete[0]['id_u']}"
                ); ?>'
            >
                <i class="fas fa-circle-xmark"></i>&nbsp;Annuler
            </a>

            <button type='submit' class='btn btn-danger'>
                <i class="fas fa-trash"></i> Supprimer
            </button>
        </div>
    </form>
</div>
