<?php

declare(strict_types=1);

/**
 * @var Gabarit $this
 * @var string $message
 * @var string $id_d
 * @var int $id_e
 * @var int $page
 */

?>

<div class='alert alert-danger' style='margin-top:10px;'>
    Passer le document en erreur fatale est irréversible. Toutes les tâches automatiques associées au document seront supprimées.
</div>

<div class="box">
    <h2>Êtes-vous sûr de vouloir effectuer cette action ? </h2>

    <form action='Document/doFatalError' method='post'>
        <?php $this->displayCSRFInput() ?>
        <input type='hidden' name='id_d' value='<?php hecho($id_d); ?>'/>
        <input type='hidden' name='id_e' value='<?php hecho((string)$id_e); ?>'/>
        <input type='hidden' name='page' value='<?php hecho((string)$page); ?>'/>
        <input type='hidden' name='go' value='1'/>

        <a
            class='btn btn-outline-primary'
            href='<?php $this->url(get_hecho("Document/detail?id_d={$id_d}&id_e={$id_e}&page={$page}")); ?>'
        >
            <i class="fas fa-circle-xmark"></i>&nbsp;Annuler
        </a>

        <button type='submit' class='btn btn-danger'>
            <i class="fas fa-trash"></i> Passer en erreur fatale
        </button>
    </form>
</div>
