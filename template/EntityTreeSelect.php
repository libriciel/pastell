<?php

declare(strict_types=1);

/**
 * @var string $treeselect_data
 * @var string $treeselect_container_class
 * @var string $treeselect_input_id
 */

?>
<script type="module">
    const domElement = document.querySelector('.<?= $treeselect_container_class ?>')
    const initialValueRaw = document.getElementById('<?= $treeselect_input_id ?>').value
    const treeselect = new Treeselect({
        placeholder: 'Sélectionner une entité',
        parentHtmlContainer: domElement,
        options: <?= $treeselect_data ?>,
        value: initialValueRaw !== '' ? [Number(initialValueRaw)] : [],
        isSingleSelect: true,
        showTags: false,
        openLevel: 3
    })

    treeselect.srcElement.addEventListener('input', (e) => {
        document.getElementById('<?= $treeselect_input_id ?>').value = e.detail;
    })
</script>
