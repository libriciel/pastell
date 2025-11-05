<?php

declare(strict_types=1);

/**
 * @var Gabarit $this
 * @var string $libelle_plateforme_mail
 */

?>
<div class="box">
    <form action='<?php $this->url('System/doEditLibellePlateformeMail'); ?>' method='post' >
        <table class='table table-striped'>
            <tr>
                <th class='w300'>
                    <label for="type_connecteur">LIBELLE_PLATEFORME_MAIL<span class="obl">*</span></label>
                    <p class="form_commentaire">
                        Nom affiché avant l’adresse d’expéditeur dans le champ “From” des mails envoyés par Pastell.
                    </p>
                </th>
                <td>
                    <input class="form-control col-md-4" id='libelle_plateforme_mail' type="text" name='libelle_plateforme_mail'
                           value='<?= $libelle_plateforme_mail ?>' required/>
                </td>
            </tr>
        </table>
        <?php $this->displayCSRFInput() ?>
        <a class='btn btn-outline-primary' href='<?php $this->url('System/index')?>'>
            <i class="fa fa-times-circle"></i>&nbsp;Annuler
        </a>
        <button type="submit" class="btn btn-primary">
            <i class="fa fa-save"></i>&nbsp;Enregistrer
        </button>
    </form>
</div>

