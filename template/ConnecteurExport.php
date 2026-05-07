<?php

/**
 * @var Gabarit $this
 * @var int $id_ce
 * @var string $password
 */
?>
<div class="alert alert-info">
    Votre mot de passe pour ce fichier est <strong><?php hecho($password);?></strong><br>
    Assurez-vous de le sauvegarder, il ne sera plus affiché.<br>
    Le mot de passe généré permet de protéger le contenu du connecteur.
    Il sera nécessaire pour importer à nouveau le connecteur sur un Pastell en version 3.1 ou ultérieur.
</div>
<div class="box">

    <form action='Connecteur/doExport' method='post'>
        <?php $this->displayCSRFInput() ?>
        <input type='hidden' name='id_ce' value='<?php hecho($id_ce)?>'>

        <a class='btn btn-outline-primary' href='Connecteur/edition?id_ce=<?php hecho($id_ce); ?>'>
            <i class="fas fa-circle-xmark"></i>&nbsp;Annuler
        </a>

        <button type='submit' class='btn btn-primary'><i class="fas fa-download"></i>&nbsp;Récupérer le connecteur</button>

    </form>
</div>
