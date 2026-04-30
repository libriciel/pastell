<?php

/** @var Gabarit $this */
/** @var array $flux_info */
?>

<div class="box" style="min-height: 500px;">

    <form action='<?php $this->url("TypeDossier/doImport"); ?>' method='post' enctype="multipart/form-data">
        <?php $this->displayCSRFInput() ?>
        <table class='table table-striped'>
            <tr>
                <th class="w400"><label for='path'>
                        <label for="id_type_dossier" >Fichier JSON contenant l'export de la definition du type de dossier</label>
                </th>
                <td> <input  type='file' name='json_type_dossier' id="json_type_dossier" class="btn btn-outline-primary col-md-4"/></td>
            </tr>
        </table>

        <a class='btn btn-outline-primary' href='<?php $this->url("TypeDossier/list")?>'>
            <i class="fas fa-circle-xmark"></i>&nbsp;Annuler
        </a>
        <button type="submit" class="btn btn-primary">
            <i class="fas fa-floppy-disk"></i>&nbsp;Importer
        </button>

    </form>
</div>
