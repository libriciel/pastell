<?php

declare(strict_types=1);

/**
 * @var string $treeselect_data
 * @var string $treeselect_container_class
 * @var string $treeselect_input_id
 * @var string $treeselect_placeholder
 * @var int $treeselect_open_level
 */

$treeselect_config = [
    'containerClass' => $treeselect_container_class,
    'inputId' => $treeselect_input_id,
    'placeholder' => $treeselect_placeholder,
    'openLevel' => $treeselect_open_level,
    'options' => json_decode($treeselect_data, true),
];

?>
<script type="application/json" class="treeselect-config">
<?= json_encode($treeselect_config, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>
</script>
