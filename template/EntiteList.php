<?php

/**
 * @var Gabarit $this
 * @var string $search
 * @var int $id_e
 * @var int $offset
 * @var int $nbCollectivite
 * @var bool $cdg_feature
 * @var array $liste_collectivite
 * @var bool $entite_edition
 */

$search = get_hecho($search);
?>
<div class="box">
    <div style='width:100%;'>
        <h2>Liste des entités</h2>
    </div>
    <?php
    if ($entite_edition) : ?>
        <a class='btn btn-primary grow'
           href="<?php $this->url('Entite/edition'); ?>"
        ><i class="fas fa-plus-circle"></i> Ajouter</a>
        <a class='btn btn-outline-primary'
           href='<?php $this->url(\sprintf('Entite/export?id_e=%s&search=%s', $id_e, $search)); ?>'
        ><i class='fas fa-download'></i>&nbsp;Exporter
            (fichier CSV)</a>
        <a class='btn btn-outline-primary'
           href="<?php $this->url('Entite/import'); ?>"
        ><i class="fas fa-upload"></i>&nbsp;Importer
            (fichier CSV)</a>
    <?php endif; ?>

    <div class="row">
        <form action='<?php $this->url('Entite/detail') ?>' method='get' class="pt-3 input-group col-md-4">
            <input type='text' name='search' id='search'
                   value='<?php echo $search; ?>' class="form-control"
                   placeholder="Rechercher par dénomination"/>
            <button type='submit' class='btn btn-primary btn-search' id="search-entite"><i class='fas fa-search'></i>
            </button>
            <div class="col-md-8"></div>
        </form>

    </div>

    <?php $this->SuivantPrecedent($offset, 20, $nbCollectivite, "Entite/detail?search=$search"); ?>
    <h3 id="title-result" class="ls-off title-result">Résultat(s) de la recherche</h3>
    <table class="table table-striped">
        <tr>
            <th class='w200'>Dénomination</th>
            <th>Siren</th>
            <?php if ($cdg_feature) : ?>
                <th>Type</th>
            <?php endif; ?>
            <th>État</th>
        </tr>
        <?php foreach ($liste_collectivite as $i => $info) : ?>
            <tr>
                <td><a href='<?php $this->url("Entite/detail?id_e={$info['id_e']}") ?>'
                    ><?php hecho($info['denomination']) ?></a></td>
                <td><?php echo $info['siren'] ?></td>
                <?php if ($cdg_feature) : ?>
                    <td>
                        <?php echo EntiteSQL::getNom($info['type']) ?>
                    </td>
                <?php endif; ?>
                <td>
                    <?php if ($info['is_active']) : ?>
                        <p class="badge bg-info">
                            Activée
                        </p>
                    <?php else : ?>
                        <p class="badge bg-danger">
                            Désactivée
                        </p>
                    <?php endif ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>

    <a class='btn btn-outline-primary'
       href='<?php $this->url(\sprintf('Entite/export?id_e=%s&search=%s', $id_e, $search)); ?>'
    ><i class='fas fa-download'></i>&nbsp;Exporter (fichier CSV)</a>
</div>
