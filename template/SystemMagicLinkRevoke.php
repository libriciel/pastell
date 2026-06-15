<?php

declare(strict_types=1);

/**
 * @var Gabarit $this
 * @var array<string, mixed> $magic_link
 */

?>
<div class="box">

    <div class="alert-danger alert">
        Attention ! Vous êtes sur le point de révoquer définitivement l'accès support
        « <b><?php hecho($magic_link['motif']) ?></b> » (expiration prévue le
        <?= get_hecho($magic_link['expires_at']) ?>).<br />
        L'intervenant perdra immédiatement son accès à la plateforme.
    </div>

    <form action='<?php $this->url('System/doMagicLinkRevoke'); ?>' method='post'>
        <?php $this->displayCSRFInput() ?>
        <input type='hidden' name='id' value='<?= (int)$magic_link['id'] ?>'/>

        <a class='btn btn-outline-primary' href='<?php $this->url('System/magicLink') ?>'>
            <i class="fas fa-circle-xmark"></i>&nbsp;Annuler
        </a>
        <button type="submit" class="btn btn-danger">
            <i class="fas fa-ban"></i>&nbsp;Révoquer
        </button>
    </form>
</div>
