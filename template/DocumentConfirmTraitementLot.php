<?php

/** @var Gabarit $this */
/** @var Action $theAction */
/** @var string $action_selected */
/** @var string $search */
/** @var string $filtre */
/** @var array $listDocument */
?>
<div class="box">
    <h2>Confirmez-vous l'action «<?php echo $theAction->getDoActionName($action_selected) ?>» sur ces dossiers ? </h2>
    <form action='<?php $this->url("Document/doTraitementLot"); ?>' method='post'>
        <?php $this->displayCSRFInput() ?>
        <input type='hidden' name='id_e' value='<?php echo $id_e ?>' />
        <input type='hidden' name='type' value='<?php echo $type ?>' />
        <input type='hidden' name='search' value='<?php echo $search ?>' />
        <input type='hidden' name='filtre' value='<?php echo $filtre ?>' />
        <input type='hidden' name='offset' value='<?php echo $offset ?>' />
        <input type='hidden' name='action' value='<?php echo $action_selected ?>' />
        <table class="table table-striped">
            <tr>
                <th class='w140'>Objet</th>
                <th>Dernier état</th>
                <th>Date</th>
            </tr>
            <?php foreach ($listDocument as $i => $document) : ?>
            <tr>
                <td>
                <input type='hidden' name='id_d[]' value='<?php echo $document['id_d']?>'/>
                <a href='<?php $this->url("Document/detail?id_d={$document['id_d']}&id_e={$document['id_e']}"); ?>'>
                        <?php hecho($document['titre'] ? $document['titre'] : $document['id_d'])?>
                    </a>
                </td>
                <td>
                    <?php echo $theAction->getActionName($document['last_action_display']) ?>
                </td>
                <td>
                    <?php echo time_iso_to_fr($document['last_action_date']) ?>
                </td>

            </tr>
            <?php endforeach;?>
        </table>
        <a class='btn btn-outline-primary'
                href='<?php $this->url("Document/traitementLot?id_e=$id_e&type=$type&search=$search&filtre=$filtre&offset=$offset") ?>'> <i class="fas fa-circle-xmark"></i>
            Annuler
        </a>

        <?php if (in_array($action_selected, ['supression','supression'])) :?>
            <button type="submit" class="btn btn-danger">
                <i class="fas fa-trash"></i>&nbsp;
                Supprimer
            </button>
        <?php else : ?>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-cogs"></i>&nbsp;
                Exécuter
            </button>
        <?php endif; ?>
    </form>
</div>
