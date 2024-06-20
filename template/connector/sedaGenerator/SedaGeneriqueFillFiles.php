<?php

/**
 * @var Gabarit $this
 * @var string $node_id
 * @var GenerateurSedaFillFiles $generateurSedaFillFiles
 * @var int $id_ce
 * @var string $field
 * @var string $flux
 * @var Field[] $fieldsList
 */

$specific_info = [];
if ($node_id) {
    $specific_info = $generateurSedaFillFiles->getArchiveUnitSpecificInfo($node_id);
}
?>
<div id='box_signature' class='box'>

    <h2>
    <?php if ($node_id) : ?>
        Détail de l'unité d'archive "<?php
            hecho($generateurSedaFillFiles->getDescription($node_id) ?: $node_id);
        ?>"
    <?php else : ?>
        Racine du bordereau
    <?php endif; ?>
    </h2><br/>


    <form action='Connecteur/doExternalData' method='post' id='form_sign'>
        <?php $this->displayCSRFInput(); ?>
        <input type='hidden' name='id_ce' value='<?php echo $id_ce ?>'/>
        <input type='hidden' name='field' value='<?php echo $field ?>'/>
        <input type='hidden' name='node_id' value='<?php hecho($node_id) ?>'/>

        <?php if ($node_id) : ?>
            <button type="submit" class="btn btn-link" name="node_id_to" value="root">
                Racine du bordereau
            </button>
            <?php foreach ($generateurSedaFillFiles->getParent($node_id) as $element) : ?>
                /
            <button type="submit" class="btn btn-link" name="node_id_to" value="<?php hecho($element['id']);?>">
                    <?php hecho(strval($element['description']) ?: $element['id']) ?>
            </button>
            <?php endforeach ?>
            <br/><br/>
        <?php endif; ?>
        <div class="box">
            <h2>Liste des fichiers</h2>
            <button type="submit" class="btn btn-primary inline" name="add-file" value="root">
                <i class='fa fa-plus-circle'></i>&nbsp;Ajouter
            </button>

                <table class="table table-striped">
                    <tr>
                        <th>Description</th>
                        <th>Expression</th>
                        <th>Actions</th>
                    </tr>

                        <?php foreach ($generateurSedaFillFiles->getFiles($node_id) as $file) : ?>
                            <tr>
                                <td>
                                    <textarea
                                            name='description_<?php hecho($file['id']) ?>'
                                            id='description_<?php hecho($file['id']) ?>'
                                            cols="40"
                                            rows="<?php echo max(1, substr_count($file['description'], "\n") + 1); ?>"
                                            class="form-control "><?php hecho($file['description']); ?></textarea>
                                </td>
                                <td>
                                    <textarea
                                            name='expression_<?php hecho($file['id']) ?>'
                                            id='expression_<?php hecho($file['id']); ?>'
                                            cols="40"
                                            rows="
                                            <?php echo max(
                                                1,
                                                substr_count($file['field_expression'], "\n") + 1
                                            ); ?>"
                                            class="form-control"><?php hecho($file['field_expression']); ?></textarea>
                                </td>
                                <td>
                                    <button type="submit"
                                            class="btn btn-primary inline"
                                            name="up"
                                            value="<?php hecho($file['id']) ?>">
                                        <i class="fa fa-caret-square-o-up"></i>
                                    </button>
                                    <button type="submit"
                                            class="btn btn-primary inline"
                                            name="down"
                                            value="<?php hecho($file['id']) ?>">
                                        <i class="fa fa-caret-square-o-down"></i>
                                    </button>
                                    <button type="submit"
                                            class="btn btn-danger inline"
                                            name="delete-file"
                                            value="<?php hecho($file['id']) ?>">
                                        <i class="fa fa-trash"></i>&nbsp;Supprimer
                                    </button>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <input style="width: 10px"
                                           type='checkbox'
                                           name="do_not_put_mime_type_<?php hecho($file['id']) ?>"
                                        <?php echo (!empty($file['do_not_put_mime_type'])) ? "checked='checked'" : "" ?>
                                    />
                                    <label for="do_not_put_mime_type_<?php hecho($file['id']) ?>"
                                    >Ne pas inclure le MimeType lors de la création du bordereau</label>
                                </td>
                            </tr>
                        <?php endforeach ?>
                    </tbody>
                </table>
        </div>

            <div class="box">
                <h2>Liste des objets d'archives</h2>
                <button type="submit" class="btn btn-primary inline" name="add-unit" value="root">
                    <i class='fa fa-plus-circle'></i>&nbsp;Ajouter
                </button>

                <table class="table table-striped">
                    <tr>
                        <th>Nom</th>
                        <th>Expression</th>
                        <th>Actions</th>
                    </tr>

                    <?php foreach ($generateurSedaFillFiles->getArchiveUnit($node_id) as $file) : ?>
                        <tr>
                            <td>
                                <textarea
                                        name='description_<?php hecho($file['id']) ?>'
                                        id='description_<?php hecho($file['id']) ?>'
                                        cols="40"
                                        rows="<?php echo max(1, substr_count($file['description'], "\n") + 1); ?>"
                                        class="form-control "><?php hecho($file['description']); ?></textarea>
                            </td>
                            <td>
                                <textarea
                                        name='expression_<?php hecho($file['id']) ?>'
                                        id='expression_<?php hecho($file['id']); ?>'
                                        cols="40"
                                        rows="<?php echo max(1, substr_count($file['field_expression'], "\n") + 1); ?>"
                                        class="form-control"><?php hecho($file['field_expression']); ?></textarea>
                            </td>
                            <td>
                                <button type="submit" class="btn btn-primary inline" name="up"
                                        value="<?php hecho($file['id']) ?>">
                                    <em class="fa  fa-caret-square-o-up"></em>
                                </button>
                                <button type="submit" class="btn btn-primary inline" name="down"
                                        value="<?php hecho($file['id']) ?>">
                                    <em class="fa  fa-caret-square-o-down"></em>
                                </button>
                                <button type="submit" class="btn btn-primary inline" name="unit-content"
                                        value="<?php hecho($file['id']) ?>">
                                    <em class="fa fa-folder-open"></em>&nbsp;Contenu
                                    (<?php echo $generateurSedaFillFiles->countChildNode($file['id']) ?>)
                                </button>
                                <button type="submit" class="btn btn-danger inline" name="delete-unit"
                                        value="<?php hecho($file['id']) ?>">
                                    <em class="fa fa-trash"></em>&nbsp;Supprimer
                                </button>
                            </td>
                        </tr>
                    <?php endforeach ?>

                </table>
            </div>

            <?php if ($node_id) : ?>
                <div class="box">
                    <a class="collapse-link" data-bs-toggle="collapse" data-bs-target="#collapseProperties">
                        <h2><i class="fa fa-plus-square"></i>Propriétés spécifiques des objets d'archives</h2>
                    </a>

                    <div class="collapse" id="collapseProperties">
                        <table class="table table-striped">
                            <?php
                            foreach (
                                $generateurSedaFillFiles
                                    ->getArchiveUnitSpecificInfoDefinition() as $specificInfoId => $specifInfo
                            ) : ?>
                                <tr>
                                    <th class="w500">
                                        <?php hecho($specifInfo['libelle']) ?>
                                        <p class="form_commentaire">
                                            <?php if (!empty($specifInfo['commentaire'])) {
                                                echo $this->getHTMLPurifier()->purify($specifInfo['commentaire']);
                                            } ?>
                                        </p>
                                    </th>
                                    <td>
                                        <textarea
                                                class="form-control col-md-12"
                                                name="<?php hecho($specificInfoId) ?>"
                                                cols="40"
                                                rows="
                                                <?php echo max(
                                                    1,
                                                    substr_count($specific_info[$specificInfoId], "\n") + 1
                                                ); ?>"
                                        ><?php hecho($specific_info[$specificInfoId]); ?></textarea>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </table>
                    </div>

                </div>
            <?php endif; ?>
        <a class='btn btn-outline-primary'
           href='Connecteur/editionModif?id_ce=<?php echo $id_ce ?>'>
            <i class="fa fa-times-circle"></i>&nbsp;Annuler
        </a>

        <button type="submit" class="btn btn-primary" name="enregistrer" value="enregistrer">
            <i class="fa fa-check"></i>&nbsp;Enregistrer
        </button>
    </form>
</div>

<?php include __DIR__ . '/SedaGeneriqueFillTwigExplanation.php' ?>
<?php $this->render('/twigDocumentation/TwigElementFlux'); ?>
<?php $this->render('/twigDocumentation/TwigPastellMetadataDescription'); ?>
