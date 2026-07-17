<?php

/**
 * @var Gabarit $this
 * @var int $id_e
 * @var int $id_g
 * @var array $info_group
 */

?>

<div class="box">
    <form action='MailSec/doGroupeEdition' method='post'>
        <?php $this->displayCSRFInput(); ?>
        <input type='hidden' name='id_e' value='<?= $id_e ?>'/>
        <table class='table table-striped'>
            <tr>
                <th>Nom</th>
                <td>
                    <input class="form-control col-md-4" type='text' name='nom'
                           value='<?php hecho($info_group['nom']) ?>'/>
                </td>
            </tr>
        </table>
        <a class='btn btn-outline-primary'
           href='<?= $id_g ? "MailSec/groupeDetail?id_e=$id_e&id_g=$id_g" : "MailSec/groupeList?id_e=$id_e" ?>'>
            <i class="fas fa-circle-xmark"></i>&nbsp;Annuler</a>
        <button type="submit" class="btn btn-primary">
            <i class="fas fa-save"></i>&nbsp;Enregistrer
        </button>
    </form>
</div>