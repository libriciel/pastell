<?php

declare(strict_types=1);

/**
 * @var Gabarit $this
 * @var string $admin_email
 */

?>
<div class="box">
    <form action='<?php $this->url('System/doEditAdminEmail'); ?>' method='post' >
        <table class='table table-striped'>
            <tr>
                <th class='w300'>
                    <label for="type_connecteur">ADMIN_EMAIL<span class="obl">*</span></label>
                </th>
                <td>
                    <input class="form-control col-md-4" id='admin_email' type="text" name='admin_email'
                           value='<?= $admin_email ?>' required/>
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

