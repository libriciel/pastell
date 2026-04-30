<?php
    /**
     * @var int $id_u
     */
?>
<div class='alert alert-danger' style='margin-top:10px;'>
    L'action de <strong>suppression</strong> d'un utilisateur est irréversible.
</div>

<div class="box">
    <h2>Êtes-vous sûr de vouloir effectuer cette action ? </h2>

    <form action='Utilisateur/doSuppression' method='post'>
        <?php $this->displayCSRFInput() ?>
        <input type='hidden' name='id_u' value='<?php echo $id_u ?>'/>
        <a
                class='btn btn-outline-primary'
                href='<?php $this->url("Utilisateur/detail?id_u=$id_u"); ?>'
        >
            <i class="fas fa-circle-xmark"></i>&nbsp;Annuler
        </a>

        <button type='submit' class='btn btn-danger'>
            <i class="fas fa-trash"></i> Supprimer
        </button>
    </form>
</div>
