<?php

/**
 * @var array $manifest_info
 * @var array $listPack
 * @var string $changelog
 */
?>
<div class="box">

    <h2>Information de version</h2>
    <table class='table table-striped'>

        <tr>
            <th class="w140">Version</th>
            <td><?php echo $manifest_info['version']; ?></td>
        </tr>
        <tr>
            <th class="w140">Révision</th>
            <td><?php echo $manifest_info['revision']; ?></td>
        </tr>
        <tr>
            <th class="w140">Date du commit</th>
            <td><?php echo $manifest_info['last_changed_date']; ?></td>
        </tr>
    </table>

</div>

<div class="box">
    <h2>Liste des suppléments et packs activés</h2>
    <table class='table table-striped'>
        <?php foreach ($listPack as $pack => $enabled) : ?>
            <tr>
                <th class="w140"><?php hecho($pack)?></th>
            </tr>
        <?php endforeach;?>
    </table>
</div>

<div class="box">
    <h2>Journal des modifications (CHANGELOG)</h2>
    <?php echo $changelog; ?>
</div>
