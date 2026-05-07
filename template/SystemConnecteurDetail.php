<?php

/**
 * @var Gabarit $this
 * @var bool $isConnectorValid
 * @var string $connectorError
 * @var string $description
 * @var array $list_restriction_pack
 * @var array $formulaire_fields
 * @var array $all_action
 * @var bool $allowOnEntiteRacine
 */
?>
<a class='btn btn-link' href='<?php $this->url('System/connecteur')?>'>
    <i class="fas fa-arrow-left"></i>&nbsp;Retour à la liste des connecteurs
</a>


<div class="box">
    <h2>Validation du connecteur</h2>
    <?php if ($isConnectorValid) : ?>
        <div class='alert alert-success'>Le fichier définissant ce connecteur est valide</div>
    <?php else :?>
        <div class='alert alert-danger'>
            Le fichier définissant ce connecteur contient des erreurs : <?php echo $connectorError ?>
        </div>
    <?php endif;?>
</div>

<div class="box">
    <h2>Description</h2>
    <?php if ($description) :?>
        <?php echo nl2br($description)?>
    <?php else : ?>
        <div class='alert'>Il n'y a pas de description pour ce flux.</div>
    <?php endif;?>
</div>

<div class="box">
    <h2>Restriction pour ce connecteur :</h2>
    <?php if ($list_restriction_pack) :?>
        <ul>
            <?php foreach ($list_restriction_pack as $restriction_pack) :?>
                <li><?php hecho($restriction_pack) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php else : ?>
        <div>Il n'y a pas de restriction pour ce connecteur</div>
    <?php endif;?>
</div>

<div class="box">
    <h2>Création sur l'entité racine</h2>
    <?php if ($allowOnEntiteRacine) : ?>
        <div class='alert alert-success'>
            Ce connecteur peut être créé sur l'entité racine
        </div>
    <?php else : ?>
        <div class='alert alert-danger'>
            Ce connecteur ne peut pas être créé sur l'entité racine
        </div>
    <?php endif ?>
</div>

<div class="box">
    <h2>Éléments du formulaire</h2>
    <table class='table table-striped'>
        <tr>
            <th>Id</th>
            <th>Libellé</th>
            <th>Commentaire</th>
        </tr>
        <?php foreach ($formulaire_fields as $field_id => $fields_properties) : ?>
            <tr>
                <td><?php hecho($field_id)?></td>
                <td><?php hecho($fields_properties['name'])?></td>
                <td><?php hecho($fields_properties['commentaire'] ?? "")?></td>
            </tr>
        <?php endforeach;?>
    </table>
</div>


<div class="box">
    <h2>Action du connecteur </h2>
    <table class='table table-striped'>
        <tr>
            <th>Id</th>
            <th>Nom de l'action</th>
            <th>Nom de l'état</th>
            <th>Classe</th>
            <th>Action automatique</th>
            <th>Emplacement</th>
        </tr>
        <?php
        foreach ($all_action as $i => $action) : ?>
            <tr>
                <td><?php hecho($action['id'])?></td>
                <td>
                    <?php if ($action['do_name'] != $action['name']) :?>
                        <?php hecho($action['do_name'])?>
                    <?php else : ?>
                        &nbsp;
                    <?php endif;?>

                </td>
                <td><?php hecho($action['name'])?></td>
                <td><?php hecho($action['class'])?></td>
                <td><?php hecho($action['action_auto'])?></td>
                <td><?php hecho($action['path'])?></td>
            </tr>
        <?php endforeach;?>
    </table>
</div>