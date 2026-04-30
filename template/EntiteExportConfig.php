<?php

/**
 * @var Gabarit $this
 * @var int $id_e
 */

use Pastell\Service\ImportExportConfig\ExportConfigService;

?>

<div class="box">

    <form action='Entite/exportConfigVerif' method='post'>
        <?php $this->displayCSRFInput() ?>
        <input type='hidden' name='id_e' value='<?php hecho($id_e)?>'>
        <table class='table table-striped' aria-label="Export de la configuration">
            <?php foreach (ExportConfigService::getOptions() as $id => $label) : ?>
            <tr>
                <th id="<?php hecho($id)?>-th">
                    <label for="<?php hecho($id)?>"><?php hecho($label)?></label>
                </th>
                <td>
                    <div class="input-group">
                        <input type="checkbox" class="" name="<?php hecho($id)?>" id="<?php hecho($id)?>"/>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>

        <button type='submit' class='btn btn-primary'><i class="fas fa-download"></i>&nbsp;Vérifier l'export</button>

    </form>
</div>
