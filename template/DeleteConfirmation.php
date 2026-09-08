<?php

declare(strict_types=1);

use Pastell\ViewModel\DeleteConfirmation;

/**
 * @var Gabarit $this
 * @var DeleteConfirmation $delete_confirmation
 */
?>
<div class='alert alert-danger' style='margin-top:10px;'>
    L'action de <strong>suppression</strong> est irréversible.
</div>

<div class="box">
    <h2>La ressource suivante va être supprimée :</h2>

    <?php if ($delete_confirmation->items && $delete_confirmation->columns) : ?>
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
                <a class='btn btn-outline-primary' href='<?php $this->url($delete_confirmation->cancelUrl) ?>'>
                    <i class="fa fa-times-circle"></i>&nbsp;Annuler
                </a>
            <?php endif ?>
            <button type='submit' class='btn btn-danger'>
                <i class="fa fa-trash"></i>&nbsp;Supprimer
            </button>
        </div>
    </form>
</div>
