<?php

/**
 * @var Gabarit $this
 * @var ConnecteurFrequence $connecteurFrequence
 */
?>


<div class="box">
    <form action='<?php $this->url("Daemon/doEditFrequence") ?>' method='post' >
        <?php $this->displayCSRFInput() ?>
        <input type="hidden" name="id_cf" value="<?php echo $connecteurFrequence->id_cf?>"/>
        <table class='table table-striped'>
            <tr>
                <th class='w200'>
                    <label for="type_connecteur">Type de connecteur</label>
                </th>
                <td >
                    <select name="type_connecteur" id="type_connecteur" class="form-select col-md-4">
                        <option value="">Tous les types</option>
                        <option value="<?php echo ConnecteurFrequence::TYPE_GLOBAL ?>">Connecteurs globaux</option>
                        <option value="<?php echo ConnecteurFrequence::TYPE_ENTITE ?>">Connecteurs d'entité</option>
                    </select>
                </td>
            </tr>
            <tr id="tr_famille_connecteur" class="hide">
                <th class='w200'>
                    <label for="famille_connecteur">Famille de connecteur</label>
                </th>
                <td>
                    <select name="famille_connecteur" id="famille_connecteur" class="form-select col-md-4">
                    </select>
                </td>
            </tr>
            <tr id="tr_id_connecteur" class="hide">
                <th class='w200'>
                    <label for="id_connecteur">Connecteur</label>
                </th>
                <td>
                    <select name="id_connecteur" id="id_connecteur" class="form-select col-md-4">
                    </select>
                </td>
            </tr>
            <tr id="tr_id_ce" class="hide">
                <th class='w200'>
                    <label for="id_ce">Instance de connecteur</label>
                </th>
                <td>
                    <select name="id_ce" id="id_ce" class="form-select col-md-4">
                    </select>
                </td>
            </tr>

            <tr id="tr_action_type" class="hide">
                <th class='w200'>
                    <label for="action_type">Type d'action</label>
                </th>
                <td>
                    <select name="action_type" id="action_type" class="form-select col-md-4">
                        <option value="">Tous les types</option>
                        <option value="<?php echo ConnecteurFrequence::TYPE_ACTION_CONNECTEUR ?>">Actions de connecteur</option>
                        <option value="<?php echo ConnecteurFrequence::TYPE_ACTION_DOCUMENT ?>">Actions de document</option>
                    </select>
                </td>
            </tr>

            <tr id="tr_type_document" class="hide">
                <th class='w200'>
                    <label for="type_document">Type de dossier</label>
                </th>
                <td>
                    <select name="type_document" id="type_document" class="form-select col-md-4">
                    </select>
                </td>
            </tr>
            <tr id="tr_action" class="hide">
                <th class='w200'>
                    <label for="action">Action</label>
                </th>
                <td>
                    <select name="action" id="action" class="form-select col-md-4">
                    </select>
                </td>
            </tr>
            <tr id="tr_expression" class="">
                <th class='w200'>
                    <label for="expression">Expression</label>
                </th>
                <td>
                    <textarea name="expression" id="expression" class="form-control col-md-4" rows="10"><?php hecho($connecteurFrequence->expression)?></textarea>
                </td>
            </tr>
            <tr id="tr_id_verrou" class="">
                <th class='w200'>
                    <label for="id_verrou">Verrou</label>
                </th>
                <td>
                    <input name="id_verrou" id="id_verrou" class="form-control col-md-4" value="<?php hecho($connecteurFrequence->id_verrou)?>"/>
                </td>
            </tr>

        </table>

        <a class='btn btn-outline-primary' href='<?php $this->url("Daemon/config") ?>'>
            <i class="fa fa-times-circle"></i>&nbsp;
            Annuler</a>

        <button type="submit" class="btn btn-primary" id="daemonedit-frequence-enregistrer">
            <i class="fa fa-floppy-o"></i>&nbsp;Enregistrer
        </button>

    </form>

</div>


<div class="alert alert-info">
    <p><strong>Format de l'expression</strong></p>

    <p>L'expression peut contenir plusieurs lignes.</p>
    <p>Chaque ligne est de la forme <i>fréquence</i> X <i>nombre d'exécutions</i></p>
    <p>La fréquence est soit un nombre de minutes, soit une expression de type <a href="https://fr.wikipedia.org/wiki/Cron#Syntaxe_de_la_table">cron</a></p>
    <p>Il est également possible de définir la fréquence en secondes en suffixant par le caractère "s"</p>

    <p id="desc-expressions-table"><strong>Exemples</strong></p>
    <table border="1" aria-labelledby="desc-expressions-table">
        <tr>
            <th>Expression</th>
            <th>Résultat</th>
        </tr>
        <tr>
            <td>10</td>
            <td>La tâche sera exécutée toutes les 10 minutes</td>
        </tr>
        <tr>
            <td>10s</td>
            <td>La tâche sera exécutée toutes les 10 secondes</td>
        </tr>
        <tr>
            <td>10 X 2<br>60</td>
            <td>La tâche sera exécutée toutes les 10 minutes, 2 fois, puis toutes les heures</td>
        </tr>
        <tr>
            <td>(40 2 * * *) X 1<br>60</td>
            <td>La tâche sera réalisée à 2h40, puis toutes les heures</td>
        </tr>
        <tr>
            <td>1 X 10</td>
            <td>La tâche sera exécutée chaque minute 10 fois, puis la tâche sera suspendue</td>
        </tr>
    </table>


</div>


<script type="text/javascript">
$(document).ready(function() {

    var type_connecteur = $("#type_connecteur");

    var action_type = $("#action_type");

    type_connecteur.change(function(){

       $("#tr_id_connecteur").hide();
        $("#tr_action_type").hide();
        $("#tr_action").hide();
        $("#tr_id_ce").hide();
       var type_connecteur = $("#type_connecteur").val();

       if (type_connecteur === ''){
           $("#tr_famille_connecteur").hide();
           return;
       }

       var url = "Daemon/listFamilleAjax?global=" + getGlobalType();

       addArrayToSelect(
            url,
           "#famille_connecteur",
           "Toutes les familles de connecteur",
           "#tr_famille_connecteur",
           function(){
               $("#famille_connecteur").val("<?php echo $connecteurFrequence->famille_connecteur ?>").change();
           }
       );
   });

    $("#famille_connecteur").change(function(){
        var famille_connecteur = $("#famille_connecteur");

        if (famille_connecteur.val() === null){
            return famille_connecteur.val('').change();
        }
        if (famille_connecteur.val() === ''){
            $("#tr_id_connecteur").hide();
            $("#tr_id_ce").hide();
            return;
        }

        var url = "Daemon/listConnecteurAjax?famille_connecteur="+famille_connecteur.val()+"&global=" + getGlobalType();
        addArrayToSelect(
            url,
            "#id_connecteur",
            "Tous les connecteurs",
            "#tr_id_connecteur",
            function(){
                $("#tr_action_type").show();
                $("#id_connecteur").val("<?php echo $connecteurFrequence->id_connecteur ?>").change();
            }
        );
    });

    $("#id_connecteur").change(function(){
        $("#tr_id_ce").hide();
        var id_connecteur = $("#id_connecteur");
        if (id_connecteur.val() === null){
            return id_connecteur.val('').change();
        }
        if (id_connecteur.val() === ''){
            $("#tr_action").hide();
        }
        action_type.val("<?php echo $connecteurFrequence->action_type ?>").change();
        if (id_connecteur.val() === ''){
            return;
        }
        var url = "Daemon/listInstanceConnecteurAjax?id_connecteur="+id_connecteur.val();

        $.get(url,function(data){
            var id_ce = $("#id_ce");
            id_ce.html("").append($("<option>",{
                value: "",
                text: "Toutes les instances de connecteurs"
            }));

            $.each($.parseJSON(data),function(index,value){
                if (value.id_e !== "0" && getGlobalType() === "1"){
                    return;
                }
                if (value.id_e === "0" && getGlobalType() !== '1'){
                    return;
                }
                if (value.denomination === null){
                    value.denomination = 'Entité racine';
                }
                $("#id_ce").append($("<option>",{
                    value: value.id_ce,
                    text: value.libelle + " [" + value.denomination + "]"
                }));
            });
            $("#tr_id_ce").show();
            id_ce.val("<?php echo $connecteurFrequence->id_ce ?>").change();
        });
    });

    $("#id_ce").change(function() {
        var id_ce = $("#id_ce");
        if (id_ce.val() === null) {
            return id_ce.val('').change();
        }
    });

    action_type.change(function(){
        var famille_connecteur = $("#famille_connecteur");
        var id_connecteur = $("#id_connecteur");
        var action_type = $("#action_type");
        if (action_type.val() === null ){
            return action_type.val('').change();
        }
        $("#tr_type_document").hide();
        $("#tr_action").hide();
        if (action_type.val() === ''){

            //$("#tr_type_document").hide();
        }
        var url;
        if (action_type.val() === 'connecteur'){

            url = "Daemon/listActionAjax?famille_connecteur="+famille_connecteur.val()+"&id_connecteur="+id_connecteur.val()+"&global=" + getGlobalType();
            addArrayToSelect(
                url,
                "#action",
                "Toutes les actions",
                "#tr_action",
                function(){
                    $("#action").val("<?php echo $connecteurFrequence->action ?>").change();
                }
            );
        }
        if (action_type.val() === 'document'){
            url = "Daemon/listFluxAjax";
            addArrayToSelect(
                url,
                "#type_document",
                "Tous les types de dossiers",
                "#tr_type_document",
                function(){
                    $("#type_document").val("<?php echo $connecteurFrequence->type_document ?>").change();
                }
            );
        }
    });

    var type_document = $("#type_document");

    type_document.change(function(){

        var type_document = $("#type_document");

        if (type_document.val() === null){
            return type_document.val('').change();
        }
        if (type_document.val() === ''){
            return $("#tr_action").hide();
        }
        var famille_connecteur = $("#famille_connecteur");

        var url = "Daemon/listFluxActionAjax?type_document=" + type_document.val() + "&famille_connecteur=" + famille_connecteur.val();
        addArrayToSelect(
            url,
            "#action",
            "Toutes les actions",
            "#tr_action",
            function(){
                $("#action").val("<?php echo $connecteurFrequence->action ?>").change();
            }
        );
    });

    var getGlobalType = function(){
        return ($("#type_connecteur").val() === 'global')?"1":"0";
    };

    var addArrayToSelect = function(
        url,
        select_jquery_selector,
        default_option,
        next_to_show,
        after_function
    ){
        $.get(url,function(data){
            $(select_jquery_selector).html("").append($("<option>",{
                value: "",
                text: default_option
            }));

            $.each($.parseJSON(data),function(index,value){
                $(select_jquery_selector).append($("<option>",{
                    value: value,
                    text: value
                }));
            });

            $(next_to_show).show();
            if (after_function !== undefined) {
                after_function();
            }
        });
    };

    type_connecteur.val("<?php echo $connecteurFrequence->type_connecteur ?>").change();

});
</script>
