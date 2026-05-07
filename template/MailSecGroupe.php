<?php

/**
 * @var Gabarit $this
 * @var array $infoGroupe
 * @var int $nbUtilisateur
 * @var int $id_g
 * @var array $listUtilisateur
 * @var bool $can_edit
 * @var array $infoEntite
 */
?>
<a class='btn btn-link' href='MailSec/groupeList?id_e=<?php echo $id_e ?>'><i class="fas fa-arrow-left"></i>&nbsp; Voir tous les groupes</a>

<br/><br/>
<div class="box">
<h2>Liste des contacts de «<?php hecho($infoGroupe['nom']); ?>» </h2>

<?php $this->SuivantPrecedent($offset, AnnuaireGroupe::NB_MAX, $nbUtilisateur, "MailSec/groupe?id_e=$id_e&id_g=$id_g"); ?>



<form action='MailSec/delContactFromGroupe' method='post' >
    <?php $this->displayCSRFInput() ?>
    <input type='hidden' name='id_e' value='<?php echo $id_e ?>' />
    <input type='hidden' name='id_g' value='<?php echo $id_g ?>' />

<table  class="table table-striped">
    <tr>

        <th>Description</th>
        <th>Email</th>

    </tr>
<?php foreach ($listUtilisateur as $utilisateur) : ?>
    <tr>
        <td>
            <input type='checkbox' name='id_a[]' value='<?php echo $utilisateur['id_a'] ?>'/>
            <a href='MailSec/detail?id_a=<?php echo $utilisateur['id_a']?>&id_e=<?php echo $id_e?>'><?php hecho($utilisateur['description']); ?></a>
        </td>
        <td>
            <?php echo $utilisateur['email']?>
        </td>
    </tr>
<?php endforeach;?>

</table>
<?php if ($can_edit) : ?>
    <button type='submit' class='btn btn-danger'>Enlever du groupe</button>
<?php endif; ?>

</form>
</div>

<?php if ($roleUtilisateur->hasDroit($authentification->getId(), "annuaire:edition", $id_e)) : ?>
<div class="box">
<h2>Ajouter un contact à «<?php hecho($infoGroupe['nom']); ?>» </h2>
<form action='MailSec/addContactToGroupe' method='post' >
    <?php $this->displayCSRFInput() ?>
    <input type='hidden' name='id_e' value='<?php echo $id_e ?>' />
    <input type='hidden' name='id_g' value='<?php echo $id_g ?>' />

    <table class="table table-striped">
        <tbody>
            <tr>
                <th>Contact : </th>
                <td><input type='text' id='nom_contact' name='name' value='' /></td>
            </tr>   
        </tbody>
    </table>
    <script>

         $(document).ready(function(){
                $("#nom_contact").pastellAutocomplete("MailSec/getContactAjax",<?php echo $id_e?>,true);

         });
    </script>
    <button type='submit' class='btn btn-primary'>Ajouter</button>
</form>
</div>
<?php endif;?>


<div class="box">
<h2>Partage</h2>

<?php if ($infoGroupe['partage']) : ?>
<div class='alert alert-info'>
Ce groupe est actuellement partagé avec les entités-filles (services, collectivités) de <?php hecho($infoEntite['denomination']); ?> qui peuvent l'utiliser
pour leur propre mail.
</div>
<form action='MailSec/partageGroupe' method='post' >
    <?php $this->displayCSRFInput() ?>
    <input type='hidden' name='id_e' value='<?php echo $id_e ?>' />
    <input type='hidden' name='id_g' value='<?php echo $id_g ?>' />
    <button type='submit' class='btn btn-danger'>Supprimer le partage</button>
</form>
<?php else :?>
<div class='alert alert-info'>
Cliquer pour partager ce groupe avec les entités filles de <?php hecho($infoEntite['denomination']); ?>.
</div>
    <form action='MailSec/partageGroupe' method='post' >
        <?php $this->displayCSRFInput() ?>
    <input type='hidden' name='id_e' value='<?php echo $id_e ?>' />
    <input type='hidden' name='id_g' value='<?php echo $id_g ?>' />
        <button type='submit' class='btn btn-primary'><i class="fas fa-globe"></i>&nbsp;Partager</button>
</form>
<?php endif;?>

</div>
