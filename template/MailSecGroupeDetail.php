<?php

/**
 * @var Gabarit $this
 * @var array $infoGroupe
 * @var int $nbUtilisateur
 * @var int $id_g
 * @var int $id_e
 * @var int $offset
 * @var array $listUtilisateur
 * @var bool $annuaire_edition
 * @var array $infoEntite
 * @var int $nb_max
 */
?>
<a class='btn btn-link' href='MailSec/groupeList?id_e=<?php echo $id_e ?>'><i class="fas fa-arrow-left"></i>&nbsp; Voir tous les groupes</a>

<br/><br/>
<div class="box">
<h2>Liste des contacts de «<?php hecho($infoGroupe['nom']); ?>» </h2>

<?php $this->suivantPrecedent($offset, $nb_max, $nbUtilisateur, "MailSec/groupeDetail?id_e=$id_e&id_g=$id_g"); ?>

<form action='MailSec/groupeRetrait' method='post' id='form-suppression-groupe'>
    <?php $this->displayCSRFInput() ?>
    <input type='hidden' name='id_e' value='<?php echo $id_e ?>' />
    <input type='hidden' name='id_g' value='<?php echo $id_g ?>' />

    <?php if ($annuaire_edition) : ?>
        <button type='submit' class='btn btn-danger' id='btn-suppression-groupe-top' disabled>
            <i class='fa fa-trash'></i>&nbsp;Retirer du groupe
        </button>
    <?php endif; ?>

    <table class="table table-striped">
        <thead>
        <tr>
            <?php if ($annuaire_edition) : ?>
                <th><input type='checkbox' id='select-all-groupe' title='Tout sélectionner'/></th>
            <?php endif; ?>
            <th>Description</th>
            <th>Email</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($listUtilisateur as $utilisateur) : ?>
            <tr>
                <?php if ($annuaire_edition) : ?>
                    <td><input type='checkbox' name='id_a[]' value='<?php echo $utilisateur['id_a'] ?>' class='groupe-checkbox'/></td>
                <?php endif; ?>
                <td>
                    <a href='MailSec/contactDetail?id_a=<?php echo $utilisateur['id_a'] ?>&id_e=<?php echo $id_e ?>'><?php hecho($utilisateur['description']); ?></a>
                </td>
                <td><?php echo $utilisateur['email'] ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <?php $this->suivantPrecedent($offset, $nb_max, $nbUtilisateur, "MailSec/groupeDetail?id_e=$id_e&id_g=$id_g"); ?>

    <?php if ($annuaire_edition) : ?>
        <button type='submit' class='btn btn-danger' id='btn-suppression-groupe-bottom' disabled>
            <i class='fa fa-trash'></i>&nbsp;Retirer du groupe
        </button>
    <?php endif; ?>
</form>
</div>

<?php if ($annuaire_edition) : ?>
<script>
    document.getElementById('select-all-groupe').addEventListener('change', function () {
        document.querySelectorAll('.groupe-checkbox').forEach(cb => cb.checked = this.checked);
        updateDeleteButton();
    });
    document.querySelectorAll('.groupe-checkbox').forEach(cb => cb.addEventListener('change', updateDeleteButton));
    function updateDeleteButton() {
        const anyChecked = document.querySelectorAll('.groupe-checkbox:checked').length > 0;
        document.getElementById('btn-suppression-groupe-top').disabled = !anyChecked;
        document.getElementById('btn-suppression-groupe-bottom').disabled = !anyChecked;
    }
</script>
<?php endif; ?>

<?php if ($annuaire_edition) : ?>
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
