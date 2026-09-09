<?php

declare(strict_types=1);

use Pastell\ViewModel\DeleteConfirmation;

/**
 * @var Gabarit $this
 * @var DeleteConfirmation $delete_confirmation
 * @var string $page_title
 */

$item_count = count($delete_confirmation->items);
?>
<div class="delete-confirmation" data-modal-title="<?php hecho($page_title ?? 'Suppression') ?>">
<div class='alert alert-danger' style='margin-top:10px;'>
    <strong><?= $item_count ?></strong>
    <?php hecho($delete_confirmation->getDeletionLabel()) ?>.
     L'action de <strong>suppression</strong> est irréversible.
</div>

<div class="box">
    <form action='<?php $this->url($delete_confirmation->actionUrl) ?>' method='post'>
        <?php $this->displayCSRFInput() ?>
        <?php foreach ($delete_confirmation->formData as $name => $value) : ?>
            <?php if (is_array($value)) : ?>
                <?php foreach ($value as $one_value) : ?>
                    <input type='hidden' name='<?php hecho($name) ?>[]' value='<?php hecho((string)$one_value) ?>'/>
                <?php endforeach ?>
            <?php else : ?>
                <input type='hidden' name='<?php hecho($name) ?>' value='<?php hecho((string)$value) ?>'/>
            <?php endif ?>
        <?php endforeach ?>
        <div class="delete-confirmation__actions">
            <?php if ($delete_confirmation->cancelUrl !== '') : ?>
                <a class='btn btn-outline-primary js-delete-cancel'
                   href='<?php $this->url($delete_confirmation->cancelUrl) ?>'>
                    <i class="fas fa-circle-xmark"></i>&nbsp;Annuler
                </a>
            <?php endif ?>
            <button type='submit' class='btn btn-danger'>
                <i class="fas fa-trash"></i>&nbsp;<?php hecho($delete_confirmation->submitLabel) ?>
            </button>
        </div>
    </form>
</div>
</div>
