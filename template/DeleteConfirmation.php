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
    <?php if ($item_count > 1): ?>
        ressources vont être supprimées
    <?php else : ?>
        ressource va être supprimée
    <?php endif; ?>
     L'action de <strong>suppression</strong> est irréversible.
</div>

<div class="box">
    <?php if ($delete_confirmation->items && $delete_confirmation->columns) : ?>
        <div class="delete-confirmation__table-wrapper">
            <table class='table table-striped'>
                <thead>
                <tr>
                    <?php foreach (array_keys($delete_confirmation->columns) as $label) : ?>
                        <th><?php hecho($label) ?></th>
                    <?php endforeach ?>
                </tr>
                </thead>
                <?php foreach ($delete_confirmation->items as $item) : ?>
                    <tr>
                        <?php foreach ($delete_confirmation->columns as $source) :
                            $cell = is_string($source) ? ($item[$source] ?? '') : $source($item); ?>
                            <td><?php hecho((string)$cell) ?></td>
                        <?php endforeach ?>
                    </tr>
                <?php endforeach ?>
            </table>
        </div>
    <?php endif ?>

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
        <div style='margin-top:20px;'>
            <?php if ($delete_confirmation->cancelUrl !== '') : ?>
                <a class='btn btn-outline-primary js-delete-cancel'
                   href='<?php $this->url($delete_confirmation->cancelUrl) ?>'>
                    <i class="fas fa-circle-xmark"></i>&nbsp;Annuler
                </a>
            <?php endif ?>
            <button type='submit' class='btn btn-danger'>
                <i class="fas fa-trash"></i>&nbsp;Supprimer
            </button>
        </div>
    </form>
</div>
</div>
