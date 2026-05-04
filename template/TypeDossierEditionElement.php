<?php

/** @var Gabarit $this */
/** @var array $type_de_dossier_info */
/** @var TypeDossierFormulaireElementProperties $formulaireElement */

use Pastell\Validator\ElementIdValidator;

?>

<div class="box" style="min-height: 500px;">
    <?php if ($formulaireElement->element_id) : ?>
        <h2>Modification de l'élément du formulaire</h2>
    <?php else : ?>
        <h2>Ajout d'un élément au formulaire</h2>
    <?php endif; ?>

    <form action='<?php $this->url("TypeDossier/doEditionElement"); ?>'
          method='post' onSubmit="return checkDefaultValueOnSelect()">
        <?php $this->displayCSRFInput() ?>
        <input type='hidden' name='id_t' value='<?php hecho($type_de_dossier_info['id_t'])?>' />
        <input type='hidden' name='orig_element_id' value='<?php hecho($formulaireElement->element_id)?>' />
        <table class='table table-striped'>
            <tr>
                <th class="w400">
                    <label for="element_id" >Identifiant de l'élément<span class="obl">*</span></label>
                    <p class='form_commentaire'>Commence par une lettre minuscule ou _. Puis chiffres, lettres
                        minuscules ou _. 64 caractères maximum.</p>

                </th>
                <td>
                    <input
                            class="form-control col-md-8"
                            type='text'
                            maxlength="<?php echo ElementIdValidator::ELEMENT_ID_MAX_LENGTH; ?>"
                            pattern="<?php echo ElementIdValidator::ELEMENT_ID_REGEXP; ?>"
                            name='element_id'
                            id="element_id"
                            value='<?php hecho($formulaireElement->element_id)?>'
                            required
                    />
                </td>
            </tr>
            <tr>
                <th class="w400">
                    <label for="name" >Libellé</label>
                </th>
                <td>
                    <input class="form-control col-md-8" type='text' name='name' id="name"
                           value='<?php hecho($formulaireElement->name)?>' />
                </td>
            </tr>
            <tr>
                <th class="w400">
                    <label for="type" >Type d'élément</label>
                </th>
                <td>
                    <select id="type" name="type" class="form-select col-md-8">
                        <?php
                        foreach (TypeDossierFormulaireElementManager::getAllTypeElement() as $type => $type_libelle) :
                            ?>
                            <option value="<?php echo $type; ?>"
                                <?php echo $type == $formulaireElement->type ? 'selected="selected"' : ''; ?>
                            ><?php echo $type_libelle; ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr id="select_value_tr">
                <th class="w400">
                    <label for="select_value" >Valeur de la liste déroulante</label>
                    <p class='form_commentaire'>Une ligne par option.<br/>
                        Possibilité d'enregistrer un dictionnaire sous la forme "clé:valeur" (sans espace)<br/>
                    </p>
                </th>
                <td>
                    <textarea
                            style="  height: 150px;" class="form-control col-md-8" id="select_value" name="select_value"
                    ><?php echo get_hecho($formulaireElement->select_value)?></textarea>
                </td>
            </tr>

            <tr id="preg_match_tr">
                <th class="w400">
                    <label for="preg_match">Expression régulière</label>
                    <p class='form_commentaire'>
                        Exemple : /^[0-9A-Z_]{2,15}$/<br/>
                        Le texte doit faire entre 2 et 15 caractères et ne peut contenir que des chiffres, lettres en
                        majuscule et le caractère underscore (_)
                    </p>
                </th>
                <td>
                    <input class="form-control col-md-8" id="preg_match" name="preg_match"
                           value="<?php echo get_hecho($formulaireElement->preg_match); ?>"/>
                </td>
            </tr>
            <tr id="preg_match_error_tr">
                <th class="w400">
                    <label for="preg_match_error">Message d'erreur si l'expression régulière n'est pas respectée</label>
                </th>
                <td>
                    <input class="form-control col-md-8" id="preg_match_error" name="preg_match_error"
                           value="<?php echo get_hecho($formulaireElement->preg_match_error); ?>"/>
                </td>
            </tr>
            <tr id="default_value_tr">
                <th class="w400">
                    <label for="default_value_tr">Valeur par défaut</label>
                    <p class="form_commentaire" id="default_value_form_comment"></p>
                </th>
                <td id="default_value_td"></td>
            </tr>
            <tr id="content_type_tr">
                <th class="w400">
                    <label for="content_type">Type de contenu des fichiers</label>
                    <p class='form_commentaire'>
                        Liste de types de contenu (content-type), séparés par des virgules.<br/>
                        Le ou les fichiers doivent avoir un des types de la liste.
                    </p>
                </th>
                <td>
                    <input class="form-control col-md-8" id="content_type" name="content_type"
                           value="<?php echo get_hecho($formulaireElement->content_type); ?>"/>
                </td>
            </tr>

            <tr>
                <th class="w400">
                    <label for="commentaire" >Commentaire</label>
                    <p class='form_commentaire'>Apparaîtra en grisé sous le libellé du champ.</p>
                </th>
                <td>
                    <textarea
                            style="  height: 150px;" class="form-control col-md-8" name="commentaire" id="commentaire"
                    ><?php echo get_hecho($formulaireElement->commentaire)?></textarea>
                </td>
            </tr>
            <tr>
                <th class="w400">
                    <label for="requis" >Champs obligatoire</label>
                </th>
                <td>
                    <input name='requis' id='requis' class=""
                           type="checkbox" <?php echo $formulaireElement->requis ? "checked='checked'" : ""?>/>
                </td>
            </tr>
            <tr>
                <th class="w400">
                    <label for="champs_affiches">Affiché dans une colonne de la liste des dossiers</label>
                </th>
                <td>
                    <input name='champs_affiches' id='champs_affiches' class=""
                           type="checkbox" <?php echo $formulaireElement->champs_affiches ? "checked='checked'" : ""?>/>
                </td>
            </tr>
            <tr>
                <th class="w400">
                    <label for="champs_recherche_avancee">Affiché dans la recherche avancée</label>
                </th>
                <td>
                    <input name='champs_recherche_avancee' id='champs_recherche_avancee' class="" type="checkbox" <?php
                    echo $formulaireElement->champs_recherche_avancee ? "checked='checked'" : ""
                    ?>/>
                </td>
            </tr>
            <tr>
                <th class="w400">
                    <label for="titre">Définir comme titre du dossier</label>
                </th>
                <td>
                    <input name='titre' id='titre' class=""
                           type="checkbox" <?php echo $formulaireElement->titre ? "checked='checked'" : ""?>/>
                </td>
            </tr>
        </table>

        <a class='btn btn-outline-primary'
           href='<?php $this->url("TypeDossier/detail?id_t={$type_de_dossier_info['id_t']}")?>'>
            <i class="fa fa-times-circle"></i>&nbsp;Annuler
        </a>
        <button type="submit" class="btn btn-primary">
            <i class="fa fa-floppy-o"></i>&nbsp;Enregistrer
        </button>

    </form>
</div>

<script>
    $(document).ready(function () {
        $("#type").change(function () {
            const option = $(this).children("option:selected").val();
            if (option === "select") {
                $("#select_value_tr").show();
            } else {
                $("#select_value_tr").hide();
            }

            if (['text', 'textarea', 'checkbox', 'select', 'date'].includes(option)) {
                $("#default_value_tr").show();
            } else {
                $("#default_value_tr").hide();
            }

            let td = document.getElementById('default_value_td');
            if (['checkbox', 'date'].includes(option)) {
                td.innerHTML = '';
                let input = document.createElement('input');
                input.type = 'checkbox';
                input.id = 'default_value';
                input.name = 'default_value';
                input.checked = <?php echo $formulaireElement->default_value ? '1' : '0'; ?>;
                td.appendChild(input);
            } else if (option === 'text') {
                td.innerHTML = '';
                let input = document.createElement('input');
                input.type = 'text';
                input.id = 'default_value';
                input.name = 'default_value';
                input.className = 'form-control col-md-8';
                input.value = '<?php
                    echo preg_replace(
                        '/\R/',
                        '\n',
                        str_replace("'", "\\'", $formulaireElement->default_value ?? '')
                    );
                    ?>';
                td.appendChild(input);
            } else if (option === 'select') {
                td.innerHTML = '';
                let input = document.createElement('select');
                input.id = 'default_value';
                input.name = 'default_value';
                input.className = 'form-control col-md-8';
                td.appendChild(input);
            } else if (option === 'textarea') {
                td.innerHTML = '';
                let textarea = document.createElement('textarea');
                textarea.id = 'default_value';
                textarea.name = 'default_value';
                textarea.className= 'form-control col-md-8';
                textarea.style.height = '150px';
                textarea.value = '<?php
                    echo preg_replace(
                        '/\R/',
                        '\n',
                        str_replace("'", "\\'", $formulaireElement->default_value ?? '')
                    );
                    ?>';
                td.appendChild(textarea);
            } else {
                td.innerHTML = '';
            }

            if(option === 'date') {
                document.getElementById('default_value_form_comment').innerText = 'Initialiser à la date du jour';
            } else {
                document.getElementById('default_value_form_comment').innerText = '';
            }

            if ((option === 'text') || (option === 'textarea')) {
                $("#preg_match_tr").show();
                $("#preg_match_error_tr").show();
            } else {
                $("#preg_match_tr").hide();
                $("#preg_match_error_tr").hide();
            }

            if (option === "file" || option === "multi_file"){
                $("#content_type_tr").show();
            } else {
                $("#content_type_tr").hide();
            }

            $("tr:visible").each(function (index) {
                $(this).css("background-color", !!(index & 1) ? "var(--ls-grey-50)" : "var(--ls-white)");
            });
        }).trigger("change");

        $("#select_value").change(function () {
            document.getElementById('default_value').innerHTML = '';
            let option = document.createElement('option');
            option.innerText = '...';
            option.value = '';
            document.getElementById('default_value').appendChild(option);
            let objectList = getObjectList();
            for (const [key, value] of Object.entries(objectList)) {
                let option = document.createElement('option');
                let defaultValue = '<?php
                    echo preg_replace(
                        '/\R/',
                        '\n',
                        str_replace("'", "\\'", $formulaireElement->default_value ?? '')
                    );
                    ?>';
                if (defaultValue === key) {
                    option.selected = true;
                }
                option.value = key;
                option.innerText = value;

                document.getElementById('default_value').appendChild(option);
            }

        }).trigger("change");
    });

    function getObjectList() {
        let listValue = document.getElementById('select_value').value;
        listValue = listValue.split('\n');
        let objectList = {};
        let count = 1;
        for (let item in listValue) {
            if (listValue[item].includes(':')) {
                let keyValue = listValue[item].split(':');
                objectList[keyValue[0]] = keyValue[1];
            } else {
                objectList[count] = listValue[item];
            }
            count += 1;
        }
        return objectList;
    }

    function checkDefaultValueOnSelect() {
        let defaultValue = document.getElementById('default_value').value;
        if (document.getElementById('type').value === 'select' && defaultValue !== '') {
            let list = document.getElementById('select_value').value;
            let listArray = list.split('\n');
            let lineCount = listArray.length;
            if (list.endsWith('\n')) {
                lineCount = lineCount - 1;
            }
            if (
                !isNaN(defaultValue) && defaultValue >= 1
                && defaultValue <= lineCount && !listArray[defaultValue-1].includes(':')
                || list.startsWith(defaultValue+":") || list.includes("\n"+defaultValue+":")
            ) {
                return true;
            } else {
                alert('La valeur par défaut ne correspond pas à une valeur de la liste déroulante');
                return false;
            }
        }
    }

</script>

