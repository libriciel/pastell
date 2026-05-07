<?php

/**
 * @var Gabarit $this
 * @var string $id_d
 * @var int $id_e
 * @var int $page
 * @var string $field
 * @var array $pieces
 * @var array $actes_type_pj_list
 * @var array $type_pj_selection
 */
?>

<div id='box_signature' class='box'>

    <form action='Document/doExternalData' method='post' id='form_sign'>
        <?php $this->displayCSRFInput(); ?>
        <input type='hidden' name='id_d' value='<?php echo $id_d ?>'/>
        <input type='hidden' name='id_e' value='<?php echo $id_e ?>'/>
        <input type='hidden' name='page' value='<?php echo $page ?>'/>
        <input type='hidden' name='field' value='<?php echo $field ?>'/>
        <table class="table table-striped">
            <tr>
                <th>Pièce</th>
                <th>Nom original du fichier</th>
                <th>Sélection du type de pièce</th>
            </tr>
            <?php foreach ($pieces as $num_piece => $libelle_piece) : ?>
                <tr>
                    <td>
                        <?php if ($num_piece) : ?>
                            Annexe numéro <?php echo $num_piece ?>
                        <?php else : ?>
                            Pièce principale
                        <?php endif; ?>
                    </td>
                    <td class="w500"><label for="type_<?php echo $num_piece ?>"><?php hecho($libelle_piece) ?></label>
                    </td>
                    <td>
                        <select id="type_<?php echo $num_piece ?>" name="type_pj[]" class="w500">
                            <?php foreach ($actes_type_pj_list as $id_pj => $libelle_pj) : ?>
                                <option value="<?php hecho($id_pj) ?>" <?php echo ($id_pj == $type_pj_selection[$num_piece]) ? 'selected="selected"' : '' ?> ><?php hecho($libelle_pj) ?></option>
                            <?php endforeach; ?>
                        </select>

                    </td>
                </tr>
            <?php endforeach; ?>

        </table>

        <a class='btn btn-outline-primary'
           href='Document/edition?id_d=<?php echo $id_d ?>&id_e=<?php echo $id_e ?>&page=<?php echo $page ?>'>
            <i class="fas fa-circle-xmark"></i>&nbsp;Annuler
        </a>

        <button type="submit" class="btn btn-primary">
            <i class="fas fa-check"></i>&nbsp;Enregistrer
        </button>
    </form>

</div>
