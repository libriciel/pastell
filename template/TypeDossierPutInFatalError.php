<?php

/** @var Gabarit $this */
/** @var array $type_de_dossier_info */
/** @var String $content */

?>

    <div class="box" style="min-height: 500px;">

    <div class="alert-danger alert">
        Vous êtes sur le point de mettre tous les dossiers associés au flux <b><?php hecho($type_de_dossier_info['id_type_dossier']) ?></b> en erreur fatale.<br>
        Attention ! Cette action est <b>définitive</b>, vous ne pourrez pas revenir en arrière.</div>

    <div><?php echo $content; ?></div>

    <form action='<?php $this->url('/TypeDossier/doPutInFatalError'); ?>' method='post' >
        <?php $this->displayCSRFInput() ?>
        <input type='hidden' name='id_t' value='<?php hecho($type_de_dossier_info['id_t'])?>' />
        <input type='hidden' name='id_type_dossier' value='<?php hecho($type_de_dossier_info['id_type_dossier'])?>' />
        <?php $id_t = $type_de_dossier_info['id_t'] ?>
        <a class='btn btn-outline-primary' href='<?php $this->url("TypeDossier/detail?id_t=$id_t")?>'>
            <i class="fas fa-circle-xmark"></i>&nbsp;Annuler
        </a>
        <button type="submit" class="btn btn-danger">
            <i class="fas fa-folder"></i>&nbsp;Mettre tous les dossiers en erreur fatale
        </button>

    </form>

    </div>

