<?php

/**
 * @var Gabarit $this
 * @var TypeDossierProperties $typeDossierProperties
 * @var array $type_de_dossier_info
 * @var string $type_dossier_hash
 * @var int $id_t
 * @var CSRFToken $csrfToken
 * @var array $all_etape_type
 * @var array $fieldsFromEtape
*/

?>

<a class='btn btn-link' href='<?php $this->url("TypeDossier/list")?>'>
    <i class="fas fa-arrow-left"></i>&nbsp;Liste des types de dossier
</a>

<div class='alert alert-warning'>
    Ne pas oublier d'ajouter les droits sur ce type de dossier dans les rôles concernés.
</div>

<div class="box">
    <h2>Informations sur le type de dossier</h2>

    <table class="table table-striped">
        <tr>
            <th class='w200'>Identifiant</th>
            <td><?php hecho($type_de_dossier_info['id_type_dossier']) ?></td>
        </tr>
        <tr>
            <th class='w200'>Empreinte sha256</th>
            <td><?php hecho($type_dossier_hash) ?></td>
        </tr>
        <tr>
            <th class='w200'>Libellé</th>
            <td><?php hecho($typeDossierProperties->nom) ?></td>
        </tr>
        <tr>
            <th class='w200'>Libellé du classement</th>
            <td><?php hecho($typeDossierProperties->type)?></td>
        </tr>
        <tr>
            <th class='w200'>Description</th>
            <td><?php echo nl2br(get_hecho($typeDossierProperties->description))?></td>
        </tr>
        <tr>
            <th class='w200'>Libellé de l'onglet principal</th>
            <td><?php hecho($typeDossierProperties->nom_onglet)?></td>
        </tr>
        <tr>
            <th class='w200'>Affichage sur un seul onglet</th>
            <td><?php hecho($typeDossierProperties->affiche_one  ? 'oui' : 'non')?></td>
        </tr>
    </table>

    <a class='btn btn-primary inline' href='<?php $this->url("/TypeDossier/editionLibelle?id_t={$id_t}") ?>'>
        <i class='fas fa-pen'></i>&nbsp;Modifier
    </a>

</div>

<div class="box">
    <h2>Gestion des éléments du formulaire</h2>
    <a class='btn btn-primary inline' href='<?php $this->url("/TypeDossier/editionElement?id_t={$id_t}") ?>'>
        <i class='fas fa-plus-circle'></i>&nbsp;Ajouter
    </a>
    <?php if (empty($typeDossierProperties->formulaireElement)) : ?>
        <div class="alert alert-warning">
            Ce formulaire ne contient pas d'élement
        </div>
    <?php else : ?>
        <table class="table table-striped">
            <tr>
                <th>Identifiant de l'élément</th>
                <th>Libellé</th>
                <th>Type</th>
                <th>Propriétés</th>
                <th>Actions</th>
            </tr>
            <tbody id="sortElement" class="type-dossier-sortable">
            <?php foreach ($typeDossierProperties->formulaireElement as $formulaireElement) : ?>
                <tr id="tr-<?php  hecho($formulaireElement->element_id) ?>">
                    <td><i class="fas fa-arrows handle"></i>&nbsp;<?php hecho($formulaireElement->element_id) ?>
                        <?php if (in_array($formulaireElement->element_id, $fieldsFromEtape, true)) :?>
                            <p><strong style='color:red'>Identifiant en doublon </strong>
                                <em class="text-muted"
                                    data-toggle="tooltip"
                                    title="Cet élément du formulaire est déjà automatiquement créé via une étape du cheminement.
                                    Il ne doit pas être créé manuellement.">
                                    <i class="fas fa-info-circle"></i>
                                </em>
                            </p>
                        <?php endif;?>
                    </td>
                    <td><?php hecho($formulaireElement->name) ?></td>
                    <td><?php hecho(TypeDossierFormulaireElementManager::getTypeElementLibelle($formulaireElement->type)) ?></td>
                    <td>
                        <?php if ($formulaireElement->titre) :?>
                            <p class="badge bg-primary">Titre du dossier</p>
                        <?php endif;?>
                        <?php if ($formulaireElement->requis) :?>
                            <p class="badge bg-danger">Obligatoire</p>
                        <?php endif;?>
                        <?php if ($formulaireElement->champs_affiches) :?>
                            <p class="badge bg-info">Affiché sur la liste</p>
                        <?php endif;?>
                        <?php if ($formulaireElement->champs_recherche_avancee) :?>
                            <p class="badge bg-info">Recherche avancée</p>
                        <?php endif;?>
                    </td>
                    <td>
                        <?php $queryParams = 'id_t=' . $id_t . '&element_id=' . $formulaireElement->element_id; ?>
                        <a class='btn btn-primary'
                           href="<?php $this->url('/TypeDossier/editionElement?' . $queryParams); ?>"
                        ><i class='fas fa-pen'></i>&nbsp;Modifier</a>
                        &nbsp;
                        <a class='btn btn-danger'
                           href="<?php $this->url('/TypeDossier/deleteElement?' . $queryParams) ?>"
                        ><i class='fas fa-trash'></i>&nbsp;Supprimer</a>
                    </td>
                </tr>
            <?php endforeach;?>
            </tbody>
        </table>
    <?php endif; ?>

</div>

<div class="box">
    <h2>Gestion des étapes du cheminement</h2>
    <a class='btn btn-primary inline' href='<?php $this->url("/TypeDossier/newEtape?id_t={$id_t}") ?>'>
        <i class='fas fa-plus-circle'></i>&nbsp;Ajouter
    </a>
    <?php if (empty($typeDossierProperties->etape)) : ?>
        <div class="alert alert-warning">
            Le cheminement de ce type de dossier est vide.
        </div>
    <?php else : ?>
        <table class="table table-striped">
            <tr>
                <th>Type de l'étape</th>
                <th>Libellé de l'étape</th>
                <th>Propriétés</th>
                <th>Actions</th>
            </tr>
            <tbody id="sortEtape" class="type-dossier-sortable">
            <?php foreach ($typeDossierProperties->etape as $num_etape => $etape) : ?>
                <tr id="tr-<?php  hecho($num_etape) ?>">
                    <td><i class="fas fa-arrows handle"></i>&nbsp;<?php hecho($all_etape_type[$etape->type]) ?></td>
                    <td><?php hecho($etape->label ?: $all_etape_type[$etape->type]); ?></td>
                    <td>
                        <?php if ($etape->defaultChecked && ! $etape->requis) :?>
                            <p class="badge bg-info">Par défaut</p>
                        <?php endif;?>
                        <?php if ($etape->requis) :?>
                            <p class="badge bg-danger">Obligatoire</p>
                        <?php else : ?>
                            <p class="badge bg-warning">Facultative</p>
                        <?php endif;?>
                        <?php if ($etape->automatique) :?>
                            <p class="badge bg-info">Etape suivante automatique</p>
                        <?php else : ?>
                            <p class="badge bg-warning">Etape suivante manuelle</p>
                        <?php endif;?>
                    </td>
                    <td>
                        <a class='btn btn-primary btn-modifier' href="#"><i class='fas fa-pen'></i>&nbsp;Modifier</a>&nbsp;
                        <a class='btn btn-danger btn-supprimer' href="#"><i class='fas fa-trash'></i>&nbsp;Supprimer</a>
                    </td>
                </tr>
            <?php endforeach;?>
            </tbody>
        </table>
    <?php endif; ?>

</div>

<div class="row">
    <div class="col float-right">
        <a class='btn btn-link' href='TypeDossier/etat?id_t=<?php echo $id_t ?>'><i class='fas fa-rectangle-list'></i>&nbsp;Voir les états du type de dossier</a>
    </div>
</div>

<script>
    $(document).ready(function(){

        $(".btn-modifier").click(function(){
            tr_number  = $(this).parents("tr:first").index();
            window.location.href = '<?php $this->url("/TypeDossier/editionEtape?id_t={$id_t}&num_etape=")?>' + tr_number;
            return false;
        });

        $(".btn-supprimer").click(function(){
            tr_number  = $(this).parents("tr:first").index();
            window.location.href = '<?php $this->url("/TypeDossier/deleteEtape?id_t={$id_t}&num_etape=")?>' + tr_number;
            return false;
        });

        $('.type-dossier-sortable').sortable({
                cursor: "grabbing",
                handle: ".handle",
                update: function () {
                    var tbody_id = $(this)[0].id;
                    var data =
                        $(this).sortable('serialize',{expression : /([^-=_]+)[-=_](.+)/})
                        + "&id_t=<?php echo $id_t ?>"
                        + "&<?php echo CSRFToken::TOKEN_NAME ?>=" +
                        encodeURIComponent("<?php echo($csrfToken->getCSRFToken()) ?>")
                    ;
                    console.log("Data send :" + data);
                    $.ajax({
                        data: data,
                        type: 'POST',
                        url: '/TypeDossier/' + tbody_id,
                        success: function(result){
                            $("#sortEtape").children("tr").each(function(){
                                $(this).attr("id","tr-" +  $(this).index());
                            })
                        },
                        error: function(result){
                            console.log("Error");
                            console.log(result);
                        }
                    });
                }
            }
        );
    });
</script>
