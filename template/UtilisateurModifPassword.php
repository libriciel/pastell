<?php

/**
 * @var Gabarit $this
 * @var int $password_min_entropy
 */
?>
<a href='Utilisateur/moi' class="btn btn-link"><i class="fas fa-arrow-left"></i>&nbsp;Espace utilisateur</a>


<div class="box">

<h2>Modifier votre mot de passe</h2>
<form action='Utilisateur/doModifPassword' method='post' >
    <?php $this->displayCSRFInput(); ?>

    <div class="form-group row">
        <label for="old_password" class="col-sm-3 col-form-label ">Mot de passe actuel </label>
        <div class="col-md-5">
            <input name="old_password" id="old_password" class="form-control" type="password">
        </div>
    </div>
    <div class="form-group row">
        <label for="password" class="col-sm-3 col-form-label ">Nouveau mot de passe </label>
        <div class="col-md-5">
            <input name="password" id="password" class="form-control" type="password">
        </div>
    </div>
    <div class="form-group row">
        <label for="password2" class="col-sm-3 col-form-label ">Confirmer le nouveau mot de passe : </label>
        <div class="col-md-5">
            <input name="password2" id="password2" class="form-control" type="password">
        </div>
    </div>

        <a class='btn btn-outline-primary' href='Utilisateur/moi'>
                <i class="fas fa-circle-xmark"></i>&nbsp;Annuler
        </a>

    <button type="submit" class="btn btn-primary">
        <i class="fas fa-floppy-disk"></i>&nbsp;Enregistrer
    </button></form>

</div>

<div class="box">
    <div class="alert alert-info">
    Le calcul de la force du mot de passe est basé sur
                <a href="https://www.ssi.gouv.fr/administration/precautions-elementaires/calculer-la-force-dun-mot-de-passe/
" target="_blank">la documentation de l'ANSSI</a>
    </div>
</div>



<script type="text/javascript">
    $(document).ready(function(){
        $('input[type=password]')
            .lsPasswordStrengthMeter( {
                "className": "ls-password-strength-meter",
                "inputGroupClass": "input-group",
                "inputGroupTag": "div",
                "thresholds": [
                    { "value": 0, "className": "bg-danger" },
                    { "value": <?php hecho(floor($password_min_entropy / 2)); ?>, "className": "bg-warning" },
                    { "value": <?php hecho($password_min_entropy); ?>, "className": "bg-success" }
                ]
            })
            .lsPasswordToggler($.fn.lsPasswordToggler.configure('4.3'));

    });
</script>
