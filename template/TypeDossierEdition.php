<?php

use Pastell\Service\TypeDossier\TypeDossierEditionService;

/**
 * @var Gabarit $this
 * @var array $flux_info
 */
?>

<div class="box" style="min-height: 500px;">

    <div class="alert-warning alert">
        <i class="fas fa-exclamation-triangle"></i> Une fois les premiers dossiers créés, l'identifiant ne sera plus modifiable.
    </div>

    <form action='<?php $this->url('TypeDossier/doEdition'); ?>' method='post' >
        <?php $this->displayCSRFInput() ?>
        <input type='hidden' name='id_t' value='<?php hecho($flux_info['id_t'] ?? ''); ?>' />
        <table class='table table-striped'>
            <tr>
                <th class="w400">
                        <label for="id_type_dossier" >Identifiant du type de dossier</label>
                        <span class='obl'>*</span>
                    <p class='form_commentaire'>Chiffres, lettres minuscules ou -. 32 caractères maximum.</p>
                </th>
                <td>
                    <input
                            style='width:500px'
                            type='text'
                            name='id_type_dossier'
                            id="id_type_dossier"
                            value='<?php hecho($flux_info['id_type_dossier'] ?? ''); ?>'
                            maxlength="<?php echo TypeDossierEditionService::TYPE_DOSSIER_ID_MAX_LENGTH; ?>"
                            pattern="<?php echo TypeDossierEditionService::TYPE_DOSSIER_ID_REGEXP; ?>"
                    />
                </td>
            </tr>
        </table>

        <a class='btn btn-outline-primary' href='<?php
        $this->url(
            !empty($flux_info['id_t']) ?
                    "TypeDossier/detail?id_t={$flux_info['id_t']}" :
                    'TypeDossier/list'
        ) ?>'>
            <i class="fas fa-circle-xmark"></i>&nbsp;Annuler
        </a>
        <button type="submit" class="btn btn-primary">
            <i class="fas fa-floppy-disk"></i>&nbsp;Enregistrer
        </button>
    </form>
</div>
