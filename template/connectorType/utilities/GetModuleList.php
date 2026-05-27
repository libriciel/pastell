<?php

/**
 * @var Gabarit $this
 * @var int $id_ce
 * @var string $field
 * @var string $moduleType
 * @var string $module_treeselect_data
 */
?>
<a class='btn btn-link' href='Connecteur/editionModif?id_ce=<?php hecho((string)$id_ce); ?>'>
    <i class="fas fa-arrow-left"></i>&nbsp;Retour au connecteur
</a>
<div class="box">
    <h2 id="desc-module-type-table">Choisissez un type de dossier</h2>

    <form action='Connecteur/doExternalData' method='post' enctype="multipart/form-data">
        <?php $this->displayCSRFInput(); ?>
        <input type='hidden' name='id_ce' value='<?php hecho((string)$id_ce); ?>'/>
        <input type='hidden' name='field' value='<?php hecho($field); ?>'/>
        <input type='hidden' name='go' value='go'/>
        <table class='table table-striped' aria-labelledby="desc-module-type-table">
            <tr id="tr_type_document">
                <th class='w200' scope="row">
                    <label for="module_type">Type de dossier</label>
                </th>
                <td>
                    <input id='module_type' type='hidden' name='module_type' value='<?php hecho($moduleType); ?>'/>
                    <div class="treeselect-module-type"></div>
                    <?php
                    $this->renderTreeSelect(
                        $module_treeselect_data,
                        'treeselect-module-type',
                        'module_type',
                        'Sélectionner un type de dossier'
                    ); ?>
                </td>
            </tr>
        </table>

        <button type='submit' class='btn btn-primary' id="valider">
            <i class="fas fa-check"></i>&nbsp;Sélectionner
        </button>

    </form>

</div>
