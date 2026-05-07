<?php

/**
 * @var Gabarit $this
 * @var string $id_d
 * @var int $id_e
 * @var int $page
 * @var array $info
 * @var array $ligne_de_poste
 * @var string $field
 */
?>
<a class='btn btn-link'
   href='Document/edition?id_d=<?php echo $id_d?>&id_e=<?php echo $id_e?>&page=<?php echo $page?>'
><i class="fas fa-arrow-left"></i>&nbsp;<?php echo $info['titre'] ?: $info['id_d']; ?></a>

<div class="box">
    <h2>Listes des lignes de poste</h2>
    <?php if (! $ligne_de_poste) :  ?>
        <div class="alert-warning alert">
            Aucune ligne de poste trouvée.
        </div>
    <?php else : ?>
    <table class="table table-striped">

        <tr>
            <th>Numéro</th>
            <th>Référence(facultatif)</th>
            <th>Dénomination</th>
            <th>Quantité</th>
            <th>Unité</th>
            <th>Montant unitaire HT</th>
            <th>Montant de la remise HT(fac)</th>
            <th>Taux de TVA</th>
        </tr>
        <?php foreach ($ligne_de_poste as $i => $ligne) : ?>
        <tr>
            <td><?php echo $i + 1 ?></td>
            <td><?php hecho($ligne['lignePosteReference']) ?></td>
            <td><?php hecho($ligne['lignePosteDenomination']) ?></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>

        </tr>
        <?php endforeach; ?>
    </table>

    <?php endif; ?>
</div>

<div class="box">
    <h2>Ajout d'une ligne de poste</h2>
    <form action='Document/doExternalData' method='post' id="form-sign">
        <?php $this->displayCSRFInput() ?>
        <input type='hidden' name='id_d' value='<?php echo $id_d?>' />
        <input type='hidden' name='id_e' value='<?php echo $id_e?>' />
        <input type='hidden' name='page' value='<?php echo $page?>' />
        <input type='hidden' name='field' value='<?php echo $field?>' />

    <table class="table table-striped">

        <tr>
            <th><label for="lignePosteReference">Référence(facultatif)</label></th>
            <td><input class="form-control col-md-4" type='text' name="lignePosteReference" id="lignePosteReference"/></td>
        </tr>
        <tr>
            <th><label for="lignePosteDenomination">Dénomination</label></th>
            <td><input class="form-control col-md-4" type='text' name="lignePosteDenomination" id="lignePosteDenomination"/></td>
        </tr>
        <tr>
            <th><label for="quantite">Quantité</label></th>
            <td><input class="form-control col-md-4" type='text' name="quantite" id="quantite"/></td>
        </tr>
        <tr>
            <th><label for="unite">Unité</label></th>
            <td><input class="form-control col-md-4" type='text' name="unite" id="unite"/></td>
        </tr>
        <tr>
            <th><label for="montant_unitaire">Montant unitaire HT</label></th>
            <td><input class="form-control col-md-4" type='text' name="montant_unitaire" id="montant_unitaire"/></td>
        </tr>
        <tr>
            <th><label for="montant_remise">Montant de la remise HT(fac)</label></th>
            <td><input class="form-control col-md-4" type='text' name="montant_remise" id="montant_remise"></td>
        </tr>
            <tr><th><label for="taux_tva">Taux de TVA</label></th>
            <td><input class="form-control col-md-4" type='text' name="taux_tva" id="taux_tva"/></td>
        </tr>

    </table>
        <button type='submit' class='btn btn-primary'><i class='fas fa-plus-circle'></i>&nbsp;Ajouter</button>
    </form>

</div>