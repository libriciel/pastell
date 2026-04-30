<?php

/**
 * @var Gabarit $this
 * @var array $role_info
 * @var string $role
 * @var bool $role_edition
 * @var array $all_droit_utilisateur
 */
?>
<?php $i = 0; ?>

<a class='btn btn-link' href='<?php $this->url("Role/index") ?>'><i class="fas fa-arrow-left"></i>&nbsp;Retour à la liste des rôles</a>


<div class="box">

<h2>Gestion du rôle : <?php  hecho($role_info['libelle'] ?? '') ?></h2>

<div class="bloc-flex">
<a class='btn btn-primary inline' href='<?php $this->url("Role/edition?role=" . get_hecho($role)) ?>'><i class='fas fa-pen'></i>&nbsp;Modifier le libellé</a>

<form action='<?php $this->url("Role/doDelete") ?>' method='post' class="form-suppression">
    <?php $this->displayCSRFInput() ?>
    <input type='hidden' name='role' value='<?php hecho($role) ?>' />
    <button type="submit" class="btn btn-danger">
        <i class="fas fa-trash"></i>&nbsp;Supprimer le rôle
    </button>
</form>
</div>

</div>


<div class="box">
<form action='<?php $this->url("Role/doDetail") ?>' method='post'>
    <?php $this->displayCSRFInput() ?>
    <?php if ($role_edition) : ?>
        <input type='hidden' name='role' value='<?php hecho($role); ?>'/>
        <button type="submit" class="btn btn-primary">
            <i class="fas fa-floppy-disk"></i>&nbsp;Enregistrer
        </button>
    <?php endif;?>

    <table class="table table-striped table-hover">
        <tr>
            <th>
                <h2>Gestion des droits</h2>
            </th>
        </tr>
        <?php foreach ($all_droit_utilisateur as $droit => $ok) : ?>
            <tr>
                <td>
                    <?php if ($role_edition) : ?>
                        <input style="width: 10px" type='checkbox' name='droit[]' value='<?php echo $droit ?>' <?php echo $ok ? "checked='checked'" : "" ?>/>&nbsp;
                    <?php endif;?>
                    <?php echo $droit ?>

                </td>
            </tr>
        <?php endforeach; ?>
    </table>
    <?php if ($role_edition) : ?>
        <button type="submit" class="btn btn-primary">
            <i class="fas fa-floppy-disk"></i>&nbsp;Enregistrer
        </button>
    <?php endif;?>
</form>



</div>