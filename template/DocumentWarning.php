<?php

/**
 * @var Gabarit $this
 * @var string $actionName
 * @var string $id_d
 * @var int $id_e
 * @var int $page
 * @var string $action
 */
?>

<div class='alert alert-danger' style='margin-top:10px;'>
    L'action <strong><?php hecho($actionName); ?></strong> est irréversible.
</div>

<div class="box">
    <h2>Êtes-vous sûr de vouloir effectuer cette action ? </h2>

    <form action='Document/action' method='post'>
        <?php $this->displayCSRFInput() ?>
        <input type='hidden' name='id_d' value='<?php hecho($id_d); ?>'/>
        <input type='hidden' name='id_e' value='<?php hecho($id_e); ?>'/>
        <input type='hidden' name='page' value='<?php hecho($page); ?>'/>
        <input type='hidden' name='action' value='<?php hecho($action); ?>'/>
        <input type='hidden' name='go' value='1'/>

        <a
                class='btn btn-outline-primary'
                href='<?php $this->url(get_hecho("Document/detail?id_d={$id_d}&id_e={$id_e}&page={$page}")); ?>'
        >
            <i class="fas fa-circle-xmark"></i>&nbsp;Annuler
        </a>

        <button type='submit' class='btn btn-danger'>
            <i class="fas fa-trash"></i> <?php hecho($actionName); ?>
        </button>
    </form>
</div>
