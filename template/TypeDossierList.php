<?php

/**
 * @var string $system_edition
 */
?>
<div class="box" style="min-height: 500px;">
    <?php if ($system_edition) : ?>
        <a href="TypeDossier/edition" class='btn btn-primary grow'><i class="fa fa-plus"></i>&nbsp;Créer</a>
        <a href="TypeDossier/import" class='btn btn-outline-primary grow'><i class="fa fa-upload"></i>&nbsp;Importer</a>
    <?php endif;?>

    <?php if (empty($type_dossier_list)) : ?>
        <br/><br/>
        <div class="alert-info alert">
            Il n'y a pas de type de dossier personnalisé sur cette plateforme Pastell.
        </div>
    <?php else : ?>
            <table class="table table-striped">
                <tr>
                    <th class='w200'>Identifiant</th>
                    <th class='w200'>Libellé</th>
                    <th>Actions</th>
                </tr>
                <?php foreach ($type_dossier_list as $type_dossier_info) : ?>
                    <tr>
                        <td>
                                <?php hecho($type_dossier_info['id_type_dossier']) ?>
                        </td>
                        <td>
                                <?php hecho(json_decode($type_dossier_info['definition'])->nom) ?>
                        </td>
                        <td>
                            <a href="<?php $this->url("TypeDossier/detail?id_t={$type_dossier_info['id_t']}") ?>" class="btn btn-primary">
                                <i class="fa fa-cog"></i>&nbsp;Gérer
                            </a>

                            <a href="<?php $this->url("TypeDossier/export?id_t={$type_dossier_info['id_t']}") ?>" class="btn btn-outline-primary">
                                <i class="fa fa-download"></i>&nbsp;Exporter
                            </a>
                            &nbsp;
                            <a href="<?php $this->url("TypeDossier/edition?id_t={$type_dossier_info['id_t']}") ?>" class="btn btn-warning">
                                <i class="fa fa-warning"></i>&nbsp;Modifier l'identifiant
                            </a>
                            &nbsp;
                            <a href="<?php $this->url("TypeDossier/delete?id_t={$type_dossier_info['id_t']}") ?>" class="btn btn-danger">
                                <i class="fa fa-trash"></i>&nbsp;Supprimer
                            </a>
                            &nbsp;
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>

    <?php endif;?>

</div>
