<?php

use Pastell\Service\Document\DocumentPastellMetadataService;

?>
<div class="box" >
    <a class="collapse-link" data-bs-toggle="collapse" data-bs-target="#collapseDataPastell">
        <h2> <i class="fa fa-plus-square"></i>Liste des métadonnées communes à tous les types de dossier</h2>
    </a>

    <div class="collapse" id="collapseDataPastell">

        <table class="table table-striped">
            <tr>
                <th class="w200">Identifiant</th>
                <th class="">Explication</th>
            </tr>
            <?php foreach (DocumentPastellMetadataService::getPastellMetadataDescription() as $id => $commentaire) : ?>
                <tr>
                    <td><?php hecho($id) ?></td>
                    <td><?php hecho($commentaire) ?></td>
                </tr>
            <?php endforeach ?>

        </table>
    </div>
</div>
