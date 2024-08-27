<?php

/** @var Gabarit $this */
/** @var array $type_de_dossier_info */
/** @var TypeDossierEtapeProperties $etapeInfo */
/** @var array $all_etape_type */

use Pastell\Validator\ElementIdValidator;

?>

<div class="box" style="min-height: 500px;">
    <h2>Ajout d'une étape dans le cheminement</h2>

    <form action='<?php $this->url("TypeDossier/doNewEtape"); ?>' method='post' >
        <?php $this->displayCSRFInput() ?>
        <input type='hidden' name='id_t' value='<?php hecho($type_de_dossier_info['id_t'])?>' />
        <table class='table table-striped'>
            <tr>
                <th class="w400">
                    <label for="type" >Nature de l'étape<span class="obl">*</span></label>
                </th>
                <td>

                    <select class="form-select col-md-4" name='type' id="type">
                        <?php foreach ($all_etape_type as $type => $libelle_type) : ?>
                            <option value="<?php echo $type ?>" <?php echo $type == $etapeInfo->type ? 'selected="selected"' : ''; ?>><?php hecho($libelle_type) ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th class="w400">
                    <label for="label" >Libellé</label>
                    <p class='form_commentaire'>64 caractères maximum.</p>
                </th>
                <td>
                    <input
                            class="form-control col-md-4"
                            type='text'
                            maxlength="<?php echo ElementIdValidator::ELEMENT_ID_MAX_LENGTH; ?>"
                            name='label'
                            id="label"
                            value='<?php hecho($etapeInfo->label); ?>'
                    />
                </td>
            </tr>
            <tr>
                <th class="w400">
                    <label for="default_checked" >Choix par défaut</label>
                    <p class='form_commentaire'>Sélectionné par défaut (si non obligatoire).</p>
                </th>
                <td>
                    <input name='default_checked' id='default_checked' class="" type="checkbox" <?php echo $etapeInfo->defaultChecked ? "checked='checked'" : ""?>/>
                </td>
            </tr>
            <tr>
                <th class="w400">
                    <label for="requis" >Étape obligatoire</label>
                </th>
                <td>
                    <input name='requis' id='requis' class="" type="checkbox" <?php echo $etapeInfo->requis ? "checked='checked'" : ""?>/>
                </td>
            </tr>
            <tr>
                <th class="w400">
                    <label for="automatique" >Enchaîner automatiquement sur l'étape suivante</label>
                </th>
                <td>
                    <input name='automatique' id='automatique'  type="checkbox" <?php echo $etapeInfo->automatique ? "checked='checked'" : ""?>/>
                </td>
            </tr>

        </table>


        <a class='btn btn-outline-primary' href='<?php $this->url("TypeDossier/detail?id_t={$type_de_dossier_info['id_t']}")?>'>
            <i class="fa fa-times-circle"></i>&nbsp;Annuler
        </a>
        <button type="submit" class="btn btn-primary">
            <i class="fa fa-floppy-o"></i>&nbsp;Enregistrer
        </button>

    </form>
</div>
