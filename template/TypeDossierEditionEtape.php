<?php

/** @var Gabarit $this */
/** @var array $type_de_dossier_info */
/** @var TypeDossierEtapeProperties $etapeInfo */
/** @var TypeDossierFormulaireElementProperties[] $multi_file_field_list */
/** @var TypeDossierFormulaireElementProperties[] $file_field_list */
/** @var TypeDossierFormulaireElementProperties[] $text_field_list  */
/** @var TypeDossierFormulaireElementProperties[] $textarea_field_list  */
/** @var array $formulaire_etape */
/** @var array $all_etape_type */

use Pastell\Validator\ElementIdValidator;

?>

<div class="box" style="min-height: 500px;">
    <h2>Modification de l'étape du cheminement</h2>

    <form action='<?php $this->url('TypeDossier/doEditionEtape'); ?>' method='post' >
        <?php $this->displayCSRFInput() ?>
        <input type='hidden' name='id_t' value='<?php hecho($type_de_dossier_info['id_t'])?>' />
        <input type='hidden' name='num_etape' value='<?php hecho($etapeInfo->num_etape)?>' />
        <table class='table table-striped'>
            <tr>
                <th class="w400">
                    <label for="type" >Nature de l'étape<span class="obl">*</span></label>
                </th>
                <td>
                    <b><?php hecho($all_etape_type[$etapeInfo->type]) ?></b>

                </td>
            </tr>
            <tr>
                <th class="w400">
                    <label for="label" >Libellé</label>
                    <p class='form_commentaire'>64 caractères maximum.</p>
                </th>
                <td>
                    <input
                            class="form-control col-md-8"
                            type='text'
                            maxlength="<?php echo ElementIdValidator::ELEMENT_ID_MAX_LENGTH; ?>"
                            name='label'
                            id='label'
                            value='<?php hecho($etapeInfo->label); ?>'
                    />
                </td>
            </tr>
            <tr>
                <th class="w400">
                    <label for="default_checked" >Choix par défaut </label>
                    <p class='form_commentaire'>Sélectionné par défaut (si non obligatoire).</p>
                </th>
                <td>
                    <input name='default_checked' id='default_checked' class=""
                           type="checkbox" <?php echo $etapeInfo->defaultChecked ? "checked='checked'" : '' ?>
                    />
                </td>
            </tr>
            <tr>
                <th class="w400">
                    <label for="requis" >Étape obligatoire</label>
                </th>
                <td>
                    <input name='requis' id='requis' class=""
                           type="checkbox" <?php echo $etapeInfo->requis ? "checked='checked'" : '' ?>
                    />
                </td>
            </tr>
            <tr>
                <th class="w400">
                    <label for="automatique" >Enchaîner automatiquement sur l'étape suivante</label>
                </th>
                <td>
                    <input name='automatique' id='automatique'
                           type="checkbox" <?php echo $etapeInfo->automatique ? "checked='checked'" : '' ?>
                    />
                </td>
            </tr>
        </table>

        <table class='table table-striped'>
            <?php foreach ($formulaire_etape as $element_id => $element_info) : ?>
                <tr>
                    <th class="w400">
                        <label for="<?php hecho($element_id) ?>" ><?php hecho($element_info['name']); ?></label>
                        <p class='form_commentaire'><?php hecho($element_info['commentaire'] ?? ''); ?></p>
                    </th>

                    <td>
                        <?php if ($element_info['type'] == 'file') : ?>
                            <select class="form-select col-md-8"
                                    name='<?php hecho($element_id) ?>'
                                    id="<?php hecho($element_id) ?>"
                            >
                                <option value=""></option>
                                <?php foreach ($file_field_list as $file_field_id => $file_field_info) : ?>
                                    <option value="<?php echo $file_field_id ?>"
                                        <?php echo $file_field_id ==
                                        $etapeInfo->specific_type_info[$element_id] ? 'selected="selected"' : ''; ?>
                                    ><?php hecho($file_field_info->name) ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php elseif ($element_info['type'] == 'multi_file') : ?>
                            <select class="form-select col-md-8"
                                    name='<?php hecho($element_id) ?>'
                                    id="<?php hecho($element_id) ?>"
                            >
                                <option value=""></option>
                                <?php foreach ($multi_file_field_list as $file_field_id => $file_field_info) : ?>
                                    <option value="<?php echo $file_field_id ?>"
                                        <?php echo $file_field_id ==
                                        $etapeInfo->specific_type_info[$element_id] ? 'selected="selected"' : ''; ?>
                                    ><?php hecho($file_field_info->name) ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php elseif ($element_info['type'] == 'select') : ?>
                            <select class="form-select col-md-8"
                                    name='<?php hecho($element_id) ?>'
                                    id="<?php hecho($element_id) ?>"
                            >
                                <?php foreach ($element_info['value'] as $file_field_id => $file_field_value) : ?>
                                    <option value="<?php echo $file_field_id ?>"
                                        <?php echo $file_field_id ==
                                        $etapeInfo->specific_type_info[$element_id] ? 'selected="selected"' : ''; ?>
                                    ><?php hecho($file_field_value) ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php elseif ($element_info['type'] == 'checkbox') : ?>
                            <input name='<?php hecho($element_id) ?>'
                                   id='<?php hecho($element_id) ?>'
                                   type="checkbox"
                                <?php echo $etapeInfo->specific_type_info[$element_id] ? "checked='checked'" : ""?>
                            />
                        <?php elseif ($element_info['type'] == 'text_select') : ?>
                            <select class="form-select col-md-8"
                                    name='<?php hecho($element_id) ?>'
                                    id="<?php hecho($element_id) ?>"
                            >
                                <option value=""></option>
                                <?php foreach ($text_field_list as $file_field_id => $file_field_info) : ?>
                                    <option value="<?php echo $file_field_id ?>"
                                        <?php echo $file_field_id ==
                                        $etapeInfo->specific_type_info[$element_id] ? 'selected="selected"' : ''; ?>
                                    ><?php hecho($file_field_info->name) ?></option>
                                <?php endforeach; ?>
                                <?php foreach ($textarea_field_list as $file_field_id => $file_field_info) : ?>
                                    <option value="<?php echo $file_field_id ?>"
                                        <?php echo $file_field_id ==
                                        $etapeInfo->specific_type_info[$element_id] ? 'selected="selected"' : ''; ?>
                                    ><?php hecho($file_field_info->name) ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php elseif ($element_info['type'] === 'text') : ?>
                            <input class="form-control col-md-8"
                                   name='<?php hecho($element_id) ?>'
                                   id='<?php hecho($element_id) ?>'
                                   value="<?php hecho($etapeInfo->specific_type_info[$element_id])?>"
                            />
                        <?php else : ?>
                            <input class="form-control col-md-8"
                                   name='<?php hecho($element_id) ?>'
                                   id='<?php hecho($element_id) ?>'
                                   value="<?php hecho($etapeInfo->specific_type_info[$element_id])?>"
                            />
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>

        <a class='btn btn-outline-primary'
           href='<?php $this->url("TypeDossier/detail?id_t={$type_de_dossier_info['id_t']}")?>'
        >
            <i class="fa fa-times-circle"></i>&nbsp;Annuler
        </a>
        <button type="submit" class="btn btn-primary">
            <i class="fa fa-floppy-o"></i>&nbsp;Enregistrer
        </button>

    </form>
</div>
