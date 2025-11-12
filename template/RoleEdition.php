<?php

/**
 * @var Gabarit $this
 * @var array $role_info
 * @var bool $nouveau
 * @var string $cancelRedirectUrl
 */

$edition_role = $role_info['role'] !== '';
?>

<div class="box">

    <form class="form-horizontal" action='<?php $this->url('Role/doEdition'); ?>' method='post'>
        <?php $this->getCSRFToken()->displayFormInput() ?>
        <input type='hidden' name='nouveau' value='<?php hecho($nouveau); ?>'/>
        <div class="control-group">
            <label class="control-label" for="role">Rôle
                <?php if (!$edition_role) : ?>
                    <span class="obl">*</span>
                <?php endif; ?>
            </label>
            <div class="controls">
                <input class="form-control col-md-4"
                       type="text"
                       name="role"
                       id="role"
                    <?php
                    if ($edition_role) {
                        echo 'disabled="disabled"';
                    } ?>
                       value="<?php hecho($role_info['role']); ?>"
                />
            </div>
        </div>
        <?php if ($edition_role) : ?>
            <input type="hidden" name="role" value="<?php hecho($role_info['role']); ?>" />
        <?php endif; ?>

        <div class="control-group">
            <label class="control-label" for="libelle">Libellé<span class="obl">*</span></label>
            <div class="controls">
                <input class="form-control col-md-4"
                       type='text'
                       name='libelle'
                       id='libelle'
                       value='<?php hecho($role_info['libelle']); ?>'
                />
            </div>
        </div>

        <br/>
        <div class="control-group">
            <a class='btn btn-outline-primary'
               href='<?php $this->url($cancelRedirectUrl); ?>'>
                <i class="fa fa-times-circle"></i>&nbsp;Annuler
            </a>

            <button type="submit" class="btn btn-primary">
                <i class="fa fa-floppy-o"></i>&nbsp;Enregistrer
            </button>
        </div>
    </form>
</div>
