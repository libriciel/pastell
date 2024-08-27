<?php

/**
 * @var Gabarit $this
 * @var string $id_d
 * @var int $id_e
 * @var int $page
 * @var array $info
 * @var string $field
 * @var array $pieces
 * @var array $pieces_type_pj_list
 * @var array $type_pj_selection
 */

?>
<a class='btn btn-link'
   href='Document/edition?id_d=<?php echo $id_d ?>&id_e=<?php echo $id_e ?>&page=<?php echo $page ?>'
>
    <i class="fa fa-arrow-left"></i>&nbsp;<?php echo $info['titre'] ?: $info['id_d'] ?>
</a>


<div id='box_type_piece' class='box'>
    <h2>Choix du type des pièces</h2>

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
                        Pièce numéro <?php echo $num_piece + 1 ?>
                    </td>
                    <td class="w300"><label for="type_<?php echo $num_piece ?>"><?php hecho($libelle_piece) ?></label>
                    </td>
                    <td>
                        <select id="type_<?php echo $num_piece ?>" name="type_pj[]" class="form-select col-md-7">
                            <?php foreach ($pieces_type_pj_list as $id_pj => $libelle_pj) : ?>
                                <option value="<?php hecho($id_pj) ?>" <?php hecho($id_pj == $type_pj_selection[$num_piece] ? 'selected="selected"' : '') ?> ><?php hecho($libelle_pj) ?></option>

                            <?php endforeach; ?>
                        </select>

                    </td>
                </tr>
            <?php endforeach; ?>

        </table>

        <button type='submit' class='btn btn-primary'><i class='fa fa-plus-circle'></i>&nbsp;Enregistrer</button>

    </form>

</div>
