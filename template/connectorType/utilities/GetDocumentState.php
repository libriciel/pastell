<?php

/**
 * @var Gabarit $this
 * @var int $id_ce
 * @var string $field
 * @var string $document_state
 * @var array $list_etat
 */
?>

<a class='btn btn-link' href='Connecteur/editionModif?id_ce=<?php hecho((string)$id_ce); ?>'>
    <i class="fa fa-arrow-left"></i>&nbsp;Retour au connecteur
</a>
<div class="box">
    <h2 id="desc-module-type-table">Choisissez un état du dossier</h2>

    <form action='Connecteur/doExternalData' method='post' enctype="multipart/form-data">
        <?php $this->displayCSRFInput(); ?>
        <input type='hidden' name='id_ce' value='<?php hecho((string)$id_ce); ?>'/>
        <input type='hidden' name='field' value='<?php hecho($field); ?>'/>
        <input type='hidden' name='go' value='go'/>
        <table class='table table-striped' aria-labelledby="desc-module-type-table">
            <tr id="tr_type_document">
                <th class='w200' scope="row">
                    <label for="module_type">État du dossier</label>
                </th>
                <td>
                    <select name="document_state" id="document_state" class="form-select col-md-2">
                        <option></option>
                        <?php foreach ($list_etat as $etat_id => $etat_info) : ?>
                            <option
                                    value="<?php hecho($etat_id) ?>"
                                <?php echo ($etat_id === $document_state) ? "selected='selected'" : '' ?>
                            >
                                <?php hecho($etat_info['name'] ?? $etat_id) ?>
                            </option>
                        <?php endforeach ?>
                    </select>
                </td>
            </tr>
        </table>

        <button type='submit' class='btn btn-primary' id="valider">
            <i class="fa fa-check"></i>&nbsp;Sélectionner
        </button>

    </form>

</div>
