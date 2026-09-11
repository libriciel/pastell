<?php

declare(strict_types=1);

/**
 * @var Gabarit $this
 * @var string $action
 * @var string $description
 * @var string $submit_label
 */

?>

<a href='Utilisateur/moi' class="btn btn-link"><i class="fas fa-arrow-left"></i>&nbsp;Espace utilisateur</a>

<div class="box">
    <p><?php hecho($description); ?></p>
    <p>Pour confirmer, saisissez votre mot de passe.</p>

    <form action='Mfa/doAuthRequired' method='post'>
        <?php $this->displayCSRFInput(); ?>
        <input type="hidden" name="action" value="<?php hecho($action); ?>"/>

        <div class="form-group row">
            <label for="password" class="col-sm-2 col-form-label">Mot de passe<span class='obl'>*</span></label>
            <div class="col-md-4">
                <input name="password" id="password" class="form-control" type="password"
                       autocomplete="current-password" required autofocus>
            </div>
        </div>

        <a class='btn btn-outline-primary' href='Utilisateur/moi'>
            <i class="fas fa-circle-xmark"></i>&nbsp;Annuler
        </a>

        <button type="submit" class="btn btn-danger">
            <i class="fas fa-shield-alt"></i>&nbsp;<?php hecho($submit_label); ?>
        </button>
    </form>
</div>
