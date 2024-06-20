<?php

/**
 * @var string $flux
 * @var Field[] $fieldsList
 */

?>

<?php if ($flux) : ?>
    <div class="box" >
        <a class="collapse-link" data-bs-toggle="collapse" data-bs-target="#collapseModuleElements">
            <h2> <i class="fa fa-plus-square"></i>Liste des éléments du flux <b><?php hecho($flux) ?></b> possibles</h2>
        </a>

        <div class="collapse" id="collapseModuleElements">

            <table class="table table-striped">
                <tr>
                    <th class="w200">Identifiant</th>
                    <th class="w200">Libellé</th>
                    <th class="w200">Type</th>
                    <th>Commentaire</th>
                </tr>
                <?php foreach ($fieldsList as $theField) : ?>
                    <tr>
                        <td><?php hecho($theField->getName()) ?></td>
                        <td><?php hecho($theField->getLibelle()) ?></td>
                        <td><?php hecho($theField->getType()) ?></td>
                        <td><?php hecho($theField->getProperties('commentaire')) ?></td>
                    </tr>
                <?php endforeach ?>

            </table>
        </div>
    </div>
<?php else : ?>
    <div class="alert alert-warning">
        Associer ce connecteur à un seul flux de l'entité pour avoir la liste des éléments disponibles sur ce flux
    </div>
<?php endif; ?>
