<?php

/**
 * @var Gabarit $this
 * @var bool $droitCreation
 * @var bool $droitSuppression
 * @var bool $utilisateur_creation
 * @var string $descendance
 * @var array $all_role
 * @var string $role_selected
 * @var string $search
 * @var int $nb_utilisateur
 * @var array $liste_utilisateur
 * @var int $id_e
 * @var int $offset
 * @var int $id_u_courant
 */

$exportUserUrl = sprintf(
    'Entite/exportUtilisateur?id_e=%s&descendance=%s&role_selected=%s&search=%s',
    $id_e,
    $descendance,
    $role_selected,
    $search
);
?>
<div class="box">
    <?php if ($utilisateur_creation) : ?>
        <a href="Utilisateur/edition?id_e=<?php echo $id_e?>"
           class='btn btn-primary grow'
        ><i class="fas fa-plus-circle"></i>&nbsp;Ajouter</a>
    <?php endif;?>

    <h2>Rechercher un utilisateur</h2>

    <form action="Entite/utilisateur" method='get' class="table-end">
        <input type='hidden' name='id_e' value='<?php echo $id_e?>'/>
        <input type='hidden' name='page' value='1'/>
    <table class='table table-striped'>
        <tr>
        <td class='w300'>Afficher les utilisateurs des entités filles</td>
        <td><input type='checkbox' name='descendance' <?php echo $descendance ? "checked='checked'" : ""?>/><br/></td>
        </tr>
        <tr>
        <td>Rôle</td>
        <td><select name='role' class="form-select col-md-5">
        <option value=''>N'importe quel rôle</option>
            <?php foreach ($all_role as $role) : ?>
                <option value='<?php hecho($role['role']); ?>'
                    <?php echo $role_selected == $role['role'] ? "selected='selected'" : ""?>
                > <?php hecho($role['libelle']); ?> </option>
            <?php endforeach ; ?>
            </select>
        </td></tr>
        <tr>
            <td>Recherche</td>
            <td>
                <input class="form-control col-md-5" type='text' name='search'
                       value='<?php hecho($search) ?>' placeholder="Rechercher par nom, prénom ou login"
                />
            </td>
        </tr>
        </table>
        <a href="Entite/utilisateur?id_e=<?php hecho($id_e) ?>"
           class="btn btn-outline-primary"
        ><i class="fas fa-undo"></i>&nbsp;Réinitialiser</a>
        <button type="submit" class="btn btn-primary">
            <i class="fas fa-search"></i>&nbsp;Rechercher
        </button>
    </form>

<h2>Liste des utilisateurs - résultats de la recherche</h2>

<a class='btn btn-outline-primary'
   href='<?php hecho($exportUserUrl); ?>'
><i class='fas fa-download'></i>&nbsp;Exporter</a>

<?php if ($droitSuppression) : ?>
    <button type='submit' form='form-suppression-lot' class='btn btn-danger' id='btn-suppression-lot-top' disabled>
        <i class='fa fa-trash'></i>&nbsp;Supprimer la sélection
    </button>
<?php endif; ?>

    <?php
    $this->suivantPrecedent(
        $offset,
        UtilisateurListe::NB_UTILISATEUR_DISPLAY,
        $nb_utilisateur,
        "Entite/utilisateur?id_e=$id_e&page=1&search=$search&descendance=$descendance&role_selected=$role_selected"
    ); ?>

<?php if ($droitSuppression) : ?>
<form action='Utilisateur/suppression' method='post' id='form-suppression-lot'>
    <?php $this->displayCSRFInput() ?>
    <input type='hidden' name='id_e' value='<?= $id_e ?>'/>
    <input type='hidden' name='source' value='list'/>
    <input type='hidden' name='search' value='<?php hecho($search) ?>'/>
    <input type='hidden' name='descendance' value='<?php hecho($descendance) ?>'/>
    <input type='hidden' name='role_selected' value='<?php hecho($role_selected) ?>'/>
<?php endif; ?>

<table class='table table-striped'>
<thead>
<tr>
    <?php if ($droitSuppression) : ?>
        <th><input type='checkbox' id='select-all-users' title='Tout sélectionner'/></th>
    <?php endif; ?>
    <th class='w200'>Prénom Nom</th>
    <th>login</th>
    <th>email</th>
    <th>Rôle</th>
    <?php if ($descendance) : ?>
        <th>Collectivité de base</th>
    <?php endif;?>
    <th>Statut</th>
</tr>
</thead>

<?php foreach ($liste_utilisateur as $user) : ?>
    <tr>
        <?php if ($droitSuppression) : ?>
            <td>
                <?php if ($user['id_u'] !== $id_u_courant) : ?>
                    <input type='checkbox' name='id_u_list[]' value='<?= $user['id_u'] ?>' class='user-checkbox'/>
                <?php endif; ?>
            </td>
        <?php endif; ?>
        <td>
            <a href='Utilisateur/detail?id_u=<?php echo $user['id_u'] ?>'>
                <?php hecho($user['prenom']); ?> <?php hecho($user['nom']); ?>
            </a>
        </td>
        <td><a href='Utilisateur/detail?id_u=<?php echo $user['id_u'] ?>'><?php hecho($user['login']); ?></a></td>
        <td><?php hecho($user['email']); ?></td>
        <td>
            <?php foreach ($user['all_role'] as $role) : ?>
                <?php hecho($role['libelle'] ?: "Aucun droit"); ?> -
                <?php if ($role['denomination'] !== null) : ?>
                    <?php if (count($role['ancetre']) > 2) : ?>
                        <?php
                        $tooltip = '';
                        foreach ($role['ancetre'] as $ancetre) {
                            $tooltip .= $ancetre . ' / ';
                        }
                        $tooltip .= $role['denomination'];
                        ?>
                        <em class="text-muted"
                            data-toggle="tooltip"
                            data-offset="70 0"
                            title="<?php hecho($tooltip); ?>"
                        >
                            Entité racine / ... /
                        </em>
                    <?php else : ?>
                        <?php foreach ($role['ancetre'] as $ancetre) : ?>
                            <em class="text-muted">
                                <?php hecho($ancetre . ' / '); ?>
                            </em>
                        <?php endforeach; ?>
                    <?php endif; ?>
                <?php endif; ?>
                <a href='Entite/detail?id_e=<?php echo $role['id_e']?>'>
                    <?php hecho($role['denomination'] ?: 'Entité racine'); ?>
                </a>
                <br/>
            <?php endforeach;?>

        </td>
        <?php if ($descendance) : ?>
            <td>
                <a href='Entite/detail?id_e=<?php echo $user['id_e']?>'
                ><?php hecho($user['denomination'] ?: "Entité racine"); ?></a>
            </td>
        <?php endif;?>
        <td>
            <?php if ($user['is_enabled'] === 0) : ?>
                <p class='badge bg-danger'>désactivé</p>
            <?php else : ?>
                <p class='badge bg-success'>activé</p>
            <?php endif; ?>
        </td>
    </tr>
<?php endforeach; ?>

</table>

    <?php
    $this->suivantPrecedent(
        $offset,
        UtilisateurListe::NB_UTILISATEUR_DISPLAY,
        $nb_utilisateur,
        "Entite/utilisateur?id_e=$id_e&page=1&search=$search&descendance=$descendance&role_selected=$role_selected"
    ); ?>

    <a class='btn btn-outline-primary'
       href='<?php hecho($exportUserUrl); ?>'
    ><i class='fas fa-download'></i>&nbsp;Exporter</a>

<?php if ($droitSuppression) : ?>
    <button type='submit' form='form-suppression-lot' class='btn btn-danger' id='btn-suppression-lot' disabled>
        <i class='fa fa-trash'></i>&nbsp;Supprimer la sélection
    </button>
</form>
<script>
    document.getElementById('select-all-users').addEventListener('change', function () {
        document.querySelectorAll('.user-checkbox').forEach(cb => cb.checked = this.checked);
        updateDeleteButton();
    });
    document.querySelectorAll('.user-checkbox').forEach(cb => cb.addEventListener('change', updateDeleteButton));
    function updateDeleteButton() {
        const anyChecked = document.querySelectorAll('.user-checkbox:checked').length > 0;
        document.getElementById('btn-suppression-lot').disabled = !anyChecked;
        document.getElementById('btn-suppression-lot-top').disabled = !anyChecked;
    }
</script>
<?php endif; ?>
</div>
