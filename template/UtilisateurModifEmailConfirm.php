<a class='btn btn-link' href='Utilisateur/moi'><i class="fas fa-arrow-left"></i>&nbsp;Espace utilisateur</a>

<div class="box">

<?php

if ($result) : ?>
<div class="alert alert-success">

    Votre email a été validé.
    <br/>
    Votre administrateur doit maintenant valider votre changement d'email.
    <br/>
    Vous serez averti par email.
</div>  

<?php else : ?>
<div class="alert alert-warning">
Ce lien de confirmation est invalide ou a déjà été utilisé.
<br/>
Si votre changement d'email n'a pas été pris en compte, veuillez recommencer la procédure.
</div>
<?php endif;?>

</div>
