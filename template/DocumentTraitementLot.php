<?php

/**
 * @var Gabarit $this
 * @var int $id_e
 * @var string $type
 * @var string $search
 * @var string $filtre
 * @var int $offset
 * @var array $all_action
 * @var array $listDocument
 * @var Action $theAction
 */

$return_url = "Document/list?id_e=$id_e&type=$type&filtre=$filtre&offset=$offset";
if ($search) {
    $return_url .= "&search=$search";
}

?>
<a class='btn btn-link'
   href='<?php $this->url(get_hecho($return_url)) ?>'
>
    <i class="fa fa-arrow-left"></i>&nbsp;Retour à la liste des dossiers
</a>
<div class="box">
    <form action='<?php $this->url('Document/confirmTraitementLot'); ?>' >
        <h3>Sélectionner un ou plusieurs objets, puis l'action à exécuter</h3>
        <div class="input-group">
            <select class="form-select col-md-5 me-2" id="action-select" name="action" title="Sélectionner une action">
                <option value="" disabled selected>Sélectionner une action</option>
            </select>
            <button type="submit"
                    class="btn btn-primary"
                    id="action-select-submit"
            ><i class="fa fa-cogs"></i>&nbsp;Exécuter
            </button>
        </div>
        <br/>

        <input type='hidden' name='id_e' value='<?php echo $id_e ?>' />
        <input type='hidden' name='type' value='<?php echo $type ?>' />
        <input type='hidden' name='search' value='<?php hecho($search) ?>' />
        <input type='hidden' name='filtre' value='<?php echo $filtre ?>' />
        <input type='hidden' name='offset' value='<?php echo $offset ?>' />
        <table class="table table-striped">
            <tr>
                <th>
                    <input title='Sélectionner ou déselectionner tous les document'
                           type="checkbox" name="select-all" id="select-all" class="w30"/>
                </th>
                <th class='w140'>Objet</th>
                <th>Dernier état</th>
                <th>Date du dernier état</th>
                <th>Actions possibles</th>
            </tr>
            <?php foreach ($listDocument as $i => $document) : ?>
                <tr>
                    <td class="w30">
                        <input title='Sélectioner le document' class='document_checkbox w30'
                               type='checkbox' name='id_d[]'
                               value='<?php echo $document['id_d']?>'/>
                    </td>
                    <td>
                        <?php $documentUrl = \sprintf(
                            'Document/detail?id_d=%s&id_e=%s',
                            $document['id_d'],
                            $document['id_e']
                        );
                        ?>
                        <a href='<?php $this->url($documentUrl); ?>'>
                            <?php hecho($document['titre'] ?: $document['id_d']); ?>
                        </a>
                    </td>
                    <td>
                        <?php echo $theAction->getActionName($document['last_action_display']) ?>
                    </td>
                    <td>
                        <?php echo time_iso_to_fr($document['last_action_date']) ?>
                    </td>
                    <td>
                        <ul>
                            <?php foreach ($document['action_possible'] as $action_name) : ?>
                                <li><?php hecho($theAction->getDoActionName($action_name)) ?></li>
                            <?php endforeach;?>
                        </ul>
                    </td>
                </tr>
            <?php endforeach;?>
        </table>

    </form>
</div>

<script>
    var all_tab = {
        <?php foreach ($listDocument as $i => $document) : ?>
        '<?php echo $document['id_d']?>': [
            <?php foreach ($document['action_possible'] as $action_name) : ?>
            '<?php echo $action_name ?>',
            <?php endforeach;?>

        ],
        <?php endforeach;?>
    };

    var all_tab_libelle = {
        <?php foreach ($all_action as $action_name) :?>
        '<?php echo $action_name; ?>': '<?php hecho($theAction->getDoActionName($action_name)) ?>',
        <?php endforeach;?>
    };


    function array_intersection(array1,array2){
        return array1.filter(function(n) {
            return array2.indexOf(n) !== -1
        });
    }

    function checkDocument(){

        var checkedValues = $('.document_checkbox:checked').map(function() {
            return this.value;
        }).get();
        var tab_result = [];
        var i;
        for(i=0; i<checkedValues.length; i++){
            var id_d = checkedValues[i];
            var tab_tmp = all_tab[id_d];
            if (i === 0){
                tab_result = tab_tmp;
            } else {
                tab_result = array_intersection(tab_result,tab_tmp);
            }
        }

        $("#action-select")
            .empty()
            .append('<option value="" disabled selected>Sélectionner une action</option>');

        tab_result.forEach(function(element){
            $("#action-select").append("<option value='"+element+"'>"+all_tab_libelle[element]+"</option>");
        });

        $("#action-select-submit").prop('disabled', 'disabled');
    }

    $(document).ready(function(){
        $(".action_submit").hide();
        $("#btn_message").html("Veuillez sélectionner un ou plusieurs documents");

        $(".document_checkbox").click(function(){
            checkDocument();
        });

        $("#select-all").click(function(){
            checkDocument();
        });

        $("#action-select").change(function(){
            if (['supression','suppression'].indexOf(this.value) !== -1){
                $("#action-select-submit")
                    .html("<i class=\"fa fa-trash\"></i>&nbsp;Supprimer")
                    .replaceClass('btn-primary','btn-danger');
            } else {
                $("#action-select-submit")
                    .html("<i class=\"fa fa-cogs\"></i>&nbsp;Exécuter")
                    .replaceClass('btn-danger','btn-primary');
            }
            $("#action-select-submit").prop('disabled', false);
        });

        checkDocument();
    });
</script>
