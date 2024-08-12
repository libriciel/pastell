<?php

/**
 * @var Gabarit $this
 * @var array $entite_info
 * @var bool $cdg_feature
 */
?>
<div class="box">
    <form action="Entite/doImport" method='post' enctype='multipart/form-data'>
        <?php $this->displayCSRFInput() ?>
    <input type='hidden' name='id_e' value='<?php hecho($entite_info['id_e'] ?? 0)?>' />
    <table class='table'>
        <?php if (! empty($entite_info['id_e'])) : ?>
        <tr>
            <th class='w140'>Collectivité parente</th>
            <td><?php hecho($entite_info['denomination']); ?></td>
        </tr>
        <?php endif;?>

        <tr>
            <th class='w140'>Fichier CSV</th>
            <td><input type='file' name='csv_col' class="btn btn-outline-primary col-md-4"/></td>
        </tr>
        <?php if ($cdg_feature) : ?>
        <tr>
            <th>Centre de gestion</th>
            <td><?php $this->render("CDGSelect"); ?></td>
        </tr>
        <?php endif; ?>
    </table>
        <button type="submit" class="btn btn-primary">
            <i class="fa fa-upload"></i>&nbsp;Importer
        </button>
    </form>
    </div>

    <div class="alert alert-info">
    <p><strong>Format du fichier</strong></p>
    <p>Le fichier CSV doit contenir une collectivité par ligne.</p>
    <p>Les lignes sont formatées de la manière suivante : "libellé collectivité","siren"</p>
</div>
