<?php

declare(strict_types=1);

/**
 * @var Gabarit $this
 * @var string $multiselect_name
 * @var array<int|string, string> $multiselect_options
 * @var string[] $multiselect_selected
 * @var string $multiselect_placeholder
 * @var bool $multiselect_multiple
 * @var string $multiselect_css_class
 */

?>
<div class="select2-multiselect-field">
    <select
        class="<?php hecho($multiselect_css_class) ?> select2-multiselect"
        name="<?php hecho($multiselect_name) ?><?php echo $multiselect_multiple ? '[]' : '' ?>"
        id="<?php hecho($multiselect_name) ?>"
        data-placeholder="<?php hecho($multiselect_placeholder) ?>"
        <?php echo $multiselect_multiple ? '' : 'data-allow-clear="true"' ?>
        <?php echo $multiselect_multiple ? 'multiple="multiple"' : '' ?>
>
    <?php if (!$multiselect_multiple) : ?>
        <option value=""></option>
    <?php endif; ?>
    <?php foreach ($multiselect_options as $multiselect_value => $multiselect_label) : ?>
        <option
                value="<?php hecho((string)$multiselect_value) ?>"
            <?php echo in_array((string)$multiselect_value, $multiselect_selected, true) ? 'selected="selected"' : '' ?>
        ><?php hecho($multiselect_label) ?></option>
    <?php endforeach; ?>
    </select>
</div>
