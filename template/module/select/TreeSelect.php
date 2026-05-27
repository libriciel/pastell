<?php

declare(strict_types=1);

/**
 * @var string $treeselect_data
 * @var string $treeselect_container_class
 * @var string $treeselect_input_id
 * @var string $treeselect_placeholder
 * @var int $treeselect_open_level
 */

?>
<script type="module">
    const domElement = document.querySelector('.<?= $treeselect_container_class ?>')
    const initialValue = document.getElementById('<?= $treeselect_input_id ?>').value
    const treeselect = new Treeselect({
        placeholder: '<?= $treeselect_placeholder ?>',
        parentHtmlContainer: domElement,
        options: <?= $treeselect_data ?>,
        value: initialValue !== '' ? [initialValue] : [],
        isSingleSelect: true,
        showTags: false,
        openLevel: <?= $treeselect_open_level ?>,
    })

    treeselect.srcElement.addEventListener('input', (e) => {
        document.getElementById('<?= $treeselect_input_id ?>').value = e.detail
    })
</script>
