<?php

declare(strict_types=1);

/** @var Gabarit $this */
/** @var Daemon $daemon */
/** @var array $entite */

?>

<div class="box">

    <div class="alert-danger alert">
        Attention ! Vous êtes sur le point de supprimer définitivement le gestionnaire de tâches sur l'entité
        <b><?php hecho($entite['denomination']) ?></b>
    </div>

    <form action='<?php $this->url("Daemon/doDeleteDaemon?id_daemon=$daemon->id_daemon"); ?>' method='post' >
        <?php $this->displayCSRFInput() ?>
        <input type='hidden' name='id_t' value='<?php hecho((string)$daemon->id_e)?>' />

        <a class='btn btn-outline-primary' href='<?php $this->url('Daemon/configuration')?>'>
            <i class="fa fa-times-circle"></i>&nbsp;Annuler
        </a>
        <button type="submit" class="btn btn-danger">
            <i class="fa fa-trash"></i>&nbsp;Supprimer
        </button>

    </form>
</div>
