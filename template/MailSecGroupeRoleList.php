<?php

/**
 * @var Gabarit $this
 * @var array $infoEntite
 * @var array $listGroupe
 * @var AnnuaireRoleSQL $annuaireRole
 * @var bool $can_edit
 * @var array $groupe_herited
 * @var string $entity_treeselect_data
 */
?>
<a class='btn btn-link' href='MailSec/annuaire?id_e=<?php echo $id_e ?>'><i class="fas fa-arrow-left"></i>&nbsp;Voir la liste des contacts</a>


<div class="box">
<h2>Liste des groupes basé sur des rôles  de <?php hecho($infoEntite['denomination']); ?> </h2>

<form action='MailSec/operationGroupeRole' method='post' >
    <?php $this->displayCSRFInput() ?>
    <input type='hidden' name='id_e' value='<?php echo $id_e ?>' />

<table class="table table-striped">
    <tr>

        <th>Nom</th>
        <th>Contact</th>
        <th>Partagé ?</th>
    </tr>
<?php foreach ($listGroupe as $groupe) :
    $utilisateur = $annuaireRole->getUtilisateur($groupe['id_r']);
    $nbUtilisateur = count($utilisateur);
    $r = [];
    foreach ($utilisateur as $u) {
        $r[] = htmlentities("\"{$u['nom']} {$u['prenom']}\" <{$u['email']}>", ENT_QUOTES);
    }
    $utilisateur = implode(",<br/>", $r);
    ?>
    <tr>
        <td><input type='checkbox' name='id_r[]' value='<?php echo $groupe['id_r'] ?>'/>
            <?php hecho($groupe['nom']); ?></td>
        <td><?php if ($nbUtilisateur) : ?>
                <?php echo $utilisateur;?>
            <?php else : ?>
                Ce groupe est vide
            <?php endif;?>  
        </td>
        <td>
            <?php echo $groupe['partage'] ? "OUI" : "NON";?>    
        </td>
    </tr>
<?php endforeach;?>

</table>
<?php if ($can_edit) : ?>
    <button type="submit" class="btn btn-danger" name="submit" value="Supprimer">
        <i class="fas fa-trash"></i>&nbsp;Supprimer
    </button>

    <button type="submit" class="btn btn-primary" name="submit" value="Partager">
        <i class="fas fa-share-nodes"></i>&nbsp;Partager
    </button>

    <button type="submit" class="btn btn-warning" name="submit" value="Enlever le partage">
        <i class="fas fa-share-nodes"></i>&nbsp;Ne plus partager
    </button>

<?php endif; ?>

</form>
</div>

<?php if ($roleUtilisateur->hasDroit($authentification->getId(), "annuaire:edition", $id_e)) : ?>
<div class="box">
<h2>Créer un groupe</h2>
<form action='MailSec/addGroupeRole' method='post' >
    <?php $this->displayCSRFInput() ?>
    <input type='hidden' name='id_e_owner' value='<?php echo $id_e ?>' />
    <table class="table table-striped">
            <tr>
                <th class="w200">Rôle</th>
                <td>
                    <?php
                        $roleSQL = new RoleSQL($sqlQuery);
                        $allRole = $roleSQL->getAllRole();
                    ?>
                    <select name='role' class="form-select col-md-4">
                        <option value=''>...</option>
                        <?php foreach ($allRole as $role) : ?>
                            <option value='<?php echo $role['role']?>'> <?php hecho($role['role']); ?> </option>
                        <?php endforeach ; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th>Collectivité ou service</th>
                <td>
                    <input id='mailsec-groupe-role-entity_id' type='hidden' name='id_e' value=''/>
                    <div class="treeselect-mailsec-groupe-role-entity"></div>
                    <?php
                    $this->renderTreeSelect(
                        $entity_treeselect_data,
                        'treeselect-mailsec-groupe-role-entity',
                        'mailsec-groupe-role-entity_id',
                        'Sélectionner une entité',
                        3
                    ); ?>
                </td>
            </tr>
    </table>
    <button type="submit" class="btn btn-primary">
        <i class="fas fa-plus"></i>&nbsp;Créer
    </button></form>
</div>
<?php endif;?>

<?php if ($groupe_herited) : ?>
<div class="box">
<h2>Liste des groupes hérités</h2>

<table  class="table table-striped">
    <tr>
        <th>Entité</th>
        <th>Nom</th>
        <th>Contact</th>
    </tr>
    <?php foreach ($groupe_herited as $groupe) :
        $utilisateur = $annuaireRole->getUtilisateur($groupe['id_r']);
        $nbUtilisateur = count($utilisateur);
        $r = [];
        foreach ($utilisateur as $u) {
            $r[] = htmlentities("\"{$u['nom']} {$u['prenom']}\" <{$u['email']}>", ENT_QUOTES, "utf-8");
        }
        $utilisateur = implode(",<br/>", $r);
        ?>
    <tr>
        <td><?php hecho($groupe['denomination']); ?></td>
        <td>
            <?php hecho($groupe['nom']); ?></td>
        <td><?php if ($nbUtilisateur) : ?>
                <?php echo $utilisateur;?>
            <?php else : ?>
                Ce groupe est vide
            <?php endif;?>  

    </tr>
    <?php endforeach;?>

</table>
</div>

<?php endif;?>

