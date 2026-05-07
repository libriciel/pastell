<?php

/** @var Gabarit $this */
/** @var array $flux_info */
/** @var TypeDossierProperties $typeDossierProperties */
/** @var array $type_de_dossier_info */
/** @var string $type_dossier_hash */
?>

<div class="box" style="min-height: 500px;">


    <h2>Modification des informations de « <?php hecho($typeDossierProperties->nom ?: $typeDossierProperties->id_type_dossier) ?> »</h2>
    <form action='<?php $this->url("TypeDossier/doEditionLibelle"); ?>' method='post' >
        <?php $this->displayCSRFInput() ?>
        <input type='hidden' name='id_t' value='<?php hecho($type_de_dossier_info['id_t'])?>' />
        <table class='table table-striped'>
            <tr>
                <th class="w400">
                    <label for="id_type_dossier" >Identifiant</label>
                </th>
                <td>
                    <b><?php hecho($type_de_dossier_info['id_type_dossier'])?></b>
                </td>
            </tr>
            <tr>
                <th class="w400">
                    <label for="type_dossier_hash" >Empreinte sha256</label>
                </th>
                <td><?php hecho($type_dossier_hash) ?></td>
            </tr>
            <tr>
                <th class="w400">
                        <label for="nom" >Libellé</label>
                </th>
                <td>
                    <input class="form-control col-md-8" type='text' name='nom' id="nom" value='<?php hecho($typeDossierProperties->nom)?>' />
                </td>
            </tr>
            <tr>
                <th class="w400">
                    <label for="type" >Libellé du classement</label>
                </th>
                <td>
                    <input class="form-control col-md-8"  type='text' name='type' id="type" value='<?php hecho($typeDossierProperties->type)?>' />
                </td>
            </tr>
            <tr>
                <th class="w400">
                    <label for="description" >Description</label>
                </th>
                <td>
                    <textarea style="  height: 150px;" class="form-control col-md-8" name="description" id="description"><?php echo get_hecho($typeDossierProperties->description)?></textarea>

                </td>
            </tr>
            <tr>
                <th class="w400">
                    <label for="nom_onglet" >Nom de l'onglet principal</label>
                </th>
                <td>
                    <input class="form-control col-md-8"  type='text' name='nom_onglet' id="nom_onglet" value='<?php hecho($typeDossierProperties->nom_onglet)?>' />
                </td>
            </tr>
            <tr>
                <th class="w400">
                    <label for="affiche_one" >Affichage sur un seul onglet</label>
                    <p class='form_commentaire'>Consultation du contenu d'un dossier sur un seul onglet</p>
                </th>
                <td>
                    <input type='checkbox' name='affiche_one' id="affiche_one" <?=
                    $typeDossierProperties->affiche_one ? 'checked' : '' ?> />
                </td>
            </tr>
        </table>

        <a class='btn btn-outline-primary' href='<?php $this->url("TypeDossier/detail?id_t={$type_de_dossier_info['id_t']}")?>'>
            <i class="fas fa-circle-xmark"></i>&nbsp;Annuler
        </a>
        <button type="submit" class="btn btn-primary">
            <i class="fas fa-floppy-disk"></i>&nbsp;Enregistrer
        </button>

    </form>
</div>
