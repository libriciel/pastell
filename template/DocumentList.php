<?php

/**
 * @var Gabarit $this
 * @var int $id_e
 * @var string $type
 * @var string $search
 * @var array $all_action
 * @var string $filtre
 * @var int $offset
 * @var string $last_id
 * @var DocumentActionEntite $documentActionEntite
 * @var int $limit
 * @var string $tri
 * @var string $sens_tri
 */

?>

<?php if ($id_e != 0) : ?>
    <div class="box">

        <form class="input-group" action='Document/list' method='get'>
            <input type='hidden' name='id_e' value='<?php echo $id_e; ?>'/>
            <input type='hidden' name='type' value='<?php echo $type; ?>'/>
            <input type='text' placeholder="Rechercher par titre" name='search' class="form-control col-2 me-2"
                   value='<?php hecho($search); ?>'/>
            <select name='filtre' class="form-select me-2">
                <option value=''>Sélectionner un état</option>
                <?php foreach ($all_action as $etat => $libelle_etat) : ?>
                    <option value='<?php echo $etat; ?>'
                        <?php echo $filtre == $etat ? 'selected' : ''; ?>
                    ><?php echo $libelle_etat; ?></option>
                <?php endforeach; ?>
            </select>
            <button type='submit' class='btn btn-primary me-2'><i class="fa fa-search"></i>Rechercher</button>

            <div class="float_right">
                <a class='btn btn-outline-primary' href='<?php $this->url("Document/search?id_e=$id_e&type=$type"); ?>'>
                    <i class="fa fa-search-plus"></i>
                    Recherche avancée
                </a>
                <?php if ($type && $id_e) : ?>
                    <?php
                    $bulkProcessUrl = sprintf(
                        'Document/traitementLot?id_e=%s&type=%s&search=%s&offset=%s&lastetat=%s',
                        $id_e,
                        $type,
                        $search,
                        $offset,
                        $filtre,
                    ); ?>
                    <a href="<?php hecho($bulkProcessUrl); ?>"
                       class="btn btn-outline-primary me-2"
                    ><i class="fa fa-cogs"></i>
                        Traitement par lot
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>


    <?php
    if ($last_id) {
        $offset = $documentActionEntite->getOffset($last_id, $id_e, $type, $limit);
    }

    $count = $documentActionEntite->getNbDocument($id_e, $type, $search, $filtre);

    $this->suivantPrecedent(
        $offset,
        $limit,
        $count,
        "Document/list?id_e=$id_e&type=$type&search=$search&filtre=$filtre&tri=$tri&sens_tri=$sens_tri"
    );

    $this->render('DocumentListBox');

    $this->suivantPrecedent(
        $offset,
        $limit,
        $count,
        "Document/list?id_e=$id_e&type=$type&search=$search&filtre=$filtre&tri=$tri&sens_tri=$sens_tri"
    );
    ?>

<?php endif; ?>

<?php if ($id_e) : ?>
    <?php $journalUrl = \sprintf('Journal/index?id_e=%s&type=%s', $id_e, $type); ?>
    <a class="btn btn-link" href="<?php echo $journalUrl; ?>">
        <i class='fa fa-list-alt'></i>&nbsp;Voir le journal des événements
    </a>
<?php endif; ?>
