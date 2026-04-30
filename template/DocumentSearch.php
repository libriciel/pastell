<?php

/**
 * @var Gabarit $this
 * @var int $id_e
 * @var string $type
 * @var string $search
 * @var string $lastEtat
 * @var string $last_state_begin_iso
 * @var string $last_state_end_iso
 * @var string $etatTransit
 * @var string $state_begin_iso
 * @var string $state_end_iso
 * @var string $notEtatTransit
 * @var string $tri
 * @var string $sens_tri
 * @var array $indexedFieldValue
 * @var DocumentActionEntite $documentActionEntite
 * @var int $offset
 * @var int $limit
 * @var array $allDroitEntite
 */
?>

    <a class='btn btn-link'
       href='Document/list?id_e=<?php echo $id_e?>&type=<?php echo $type?>'
    ><i class="fas fa-arrow-left"></i>&nbsp;Retour à la liste des dossiers </a>


    <div class="accordion" id="accordionExample">
        <div class="card">
            <a id="headingOne"
               class="card-header ls-accordion"
               data-bs-toggle="collapse" data-bs-target="#collapseOne"
               aria-expanded="true" aria-controls="collapseOne"
            >
                <i class="fas fa-search"></i> Recherche avancée
                <i class="fas fa-plus-square plier"></i><i class="fas fa-minus-square deplier"></i>
            </a>

            <div id="collapseOne" class="collapse show" aria-labelledby="headingOne" data-parent="#accordionExample">
                <div class="card-body">
                    <div>
                        <form action='Document/search' method='get'>
                            <input type='hidden' name='go' value='go'/>
                            <input type='hidden' name='date_in_fr' value='true'/>

                            <?php $this->getRechercheAvanceFormulaireHTML()->display(); ?>

                            <a class='btn btn-outline-primary'
                               href='<?php $this->url("Document/list?id_e=$id_e&type=$type"); ?>'
                            >
                                <i class="fas fa-circle-xmark"></i>
                                Annuler
                            </a>

                            <a class='btn btn-outline-primary'
                               href='Document/search?id_e=<?php echo $id_e ?>&type=<?php echo $type ?>'
                            >
                                <i class="fas fa-undo"></i>&nbsp;
                                Réinitialiser
                            </a>

                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i>&nbsp;Rechercher
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script type="text/javascript">
        var type = $('select[name="type"]');
        $(type.get(0)).on('change', function () {
            var selectedType = $(this).val();
            var fields = ['lastetat', 'etatTransit', 'notEtatTransit'];
            for(var i = 0; i < fields.length; ++i) {
                var field = $('[name=' + fields[i] + ']');
                var optionGroups = field.find('optgroup');
                var optionGroupOfSelectedType = field.find('[label="' + selectedType + '"]');
                optionGroups.hide();
                optionGroupOfSelectedType.show();
            }
        }).trigger('change');
    </script>

<?php

$url = sprintf(
    'id_e=%s&search=%s&type=%s&lastetat=%s&last_state_begin=%s&last_state_end=%s&etatTransit=%s&state_begin=%s' .
    '&state_end=%s&notEtatTransit=%s&tri=%s&sens_tri=%s&date_in_fr=true',
    $id_e,
    $search,
    $type,
    $lastEtat,
    $last_state_begin_iso,
    $last_state_end_iso,
    $etatTransit,
    $state_begin_iso,
    $state_end_iso,
    $notEtatTransit,
    $tri,
    $sens_tri
);

if ($type) {
    foreach ($indexedFieldValue as $indexName => $indexValue) {
        $url .= "&" . urlencode($indexName) . "=" . urlencode($indexValue);
    }
}


if ($go = 'go') {
    $listDocument = $documentActionEntite->getListBySearch(
        $id_e,
        $type,
        $offset,
        $limit,
        $search,
        $lastEtat,
        $last_state_begin_iso,
        $last_state_end_iso,
        $tri,
        $allDroitEntite,
        $etatTransit,
        $state_begin_iso,
        $state_end_iso,
        $notEtatTransit
    );
    $count = $documentActionEntite->getNbDocumentBySearch(
        $id_e,
        $type,
        $search,
        $lastEtat,
        $last_state_begin_iso,
        $last_state_end_iso,
        $allDroitEntite,
        $etatTransit,
        $state_begin_iso,
        $state_end_iso,
        $notEtatTransit,
        $indexedFieldValue
    );
    if ($count) {
        $this->suivantPrecedent($offset, $limit, $count, "Document/search?$url");
        $this->setViewParameter('url', $url);
        $this->render("DocumentListBox");


        ?>


        <a href="Document/traitementLot?<?php hecho($url); ?>" class="btn btn-primary">
            <i class='fas fa-cogs'></i>&nbsp;Traitement par lot
        </a>

            <a class='btn btn-primary'
               href='Document/export?<?php hecho($url); ?>'
            ><i class='fas fa-download'></i>&nbsp;Exporter</a>
        <?php
    } else {
        ?>
        <div class="alert alert-info">
            Les critères de recherches ne correspondent à aucun document
        </div>
        <?php
    }
}
