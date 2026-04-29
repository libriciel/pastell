<?php

declare(strict_types=1);

/**
 * @var Gabarit $this
 * @var int $workspace_alert_threshold
 */

?>
<div class="box">
    <form action='<?php $this->url('System/doEditWorkspaceAlertThreshold'); ?>' method='post' >
        <table class='table table-striped'>
            <tr>
                <th class='w300'>
                    <label for="workspace_alert_threshold">Seuil d'alerte taux d'occupation du workspace<span class="obl">*</span></label>
                    <p class="form_commentaire">
                        Taux d'occupation (en %) à partir duquel un email d'alerte est envoyé. Valeur entre 0 et 100.
                    </p>
                </th>
                <td>
                    <input class="form-control col-md-4" id='workspace_alert_threshold' type="number" name='workspace_alert_threshold'
                           min="0" max="100"
                           value='<?= $workspace_alert_threshold ?>' required/>
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
