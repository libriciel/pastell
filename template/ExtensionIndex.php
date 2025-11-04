<?php

/** @var Gabarit $this */
/** @var array $all_extensions */
/** @var array $pastell_manifest */
?>
<div class="box">

<table class='table table-striped'>
<tr>
    <th class="w200">Nom<br/><em>#id</em></th>
    <th>Connecteurs-Type</th>
    <th>Connecteurs</th>
    <th>Types de dossier</th>
    <th>Types d'étapes</th>
    <th>Numéro de version (révision)</th>
    <th>Version de Pastell attendue</th>
    <th>Extensions attendues</th>
    <th>Module ok</th>
</tr>
<?php $i = 0; foreach ($all_extensions as $id_e => $extension) : ?>
    <tr>
        <td><a href='<?php $this->url("Extension/detail?id_extension=$id_e") ?>'><?php hecho($extension['nom']); ?></a>
            <br/>
            <em><a href='<?php $this->url("Extension/detail?id_extension=$id_e") ?>'><?php hecho($extension['id']); ?></a></em>
        </td>
        <td>
            <ul>
            <?php foreach ($extension['connecteur-type'] as $connecteur_type) : ?>
                <li><?php hecho($connecteur_type)?></li>
            <?php endforeach;?>
            </ul>
        </td>
        <td>
            <ul>
            <?php foreach ($extension['connecteur'] as $connecteur) : ?>
                <li><?php hecho($connecteur)?></li>
            <?php endforeach;?>
            </ul>
        </td>
        <td>
            <ul>
            <?php foreach ($extension['flux'] as $flux) : ?>
                <li>
                <?php hecho($flux)?>
                </li>
            <?php endforeach;?>
            </ul>
        </td>
        <td>
            <ul>
                <?php foreach ($extension['type_etape'] as $type_etape) : ?>
                    <li><?php hecho($type_etape)?></li>
                <?php endforeach;?>
            </ul>
        </td>
        <td>
            <?php if (! empty($extension['manifest']['version'])) : ?>
                <a href='<?php $this->url("Extension/changelog?id_extension=$id_e") ?>'><?php hecho($extension['manifest']['version']); ?></a>
            <?php else :?>
                <span class='text_alert'>NON VERSIONNÉE</span>
            <?php endif;?>
            &nbsp;
            (<?php hecho($extension['manifest']['revision'] ?? "")?>)
            <?php if (! empty($extension['manifest']['autre-version-compatible'])) : ?>
                <br/>Versions compatibles :
                <ul>
                    <?php foreach ($extension['manifest']['autre-version-compatible'] as $version) : ?>
                    <li><?php hecho($version)?></li>
                    <?php endforeach;?>
                </ul> 
            <?php endif;?>

        </td>
        <td>
            <?php hecho($extension['manifest']['pastell-version'] ?? '')?>
            <?php if (! $extension['pastell-version-ok']) : ?>
            &nbsp;-&nbsp;<span class='text_alert'>KO</span>
            <?php endif;?>
        </td>
        <td>
        <ul>
            <?php if (! empty($extension['manifest'])) : ?>
                <?php foreach ($extension['manifest']['extension_needed'] as $extension_needed => $extension_needed_info) : ?>
                    <li><?php hecho($extension_needed)?>(version <?php hecho($extension_needed_info['version'])?>)
                        <?php if (empty($extension_needed_info['extension_presente'])) :?>
                            <span class='text_alert'>KO</span>
                        <?php elseif (! $extension_needed_info['extension_version_ok']) :?>
                            <span class='text_alert'>Version KO</span>
                        <?php endif;?>
                    </li>
                <?php endforeach;?>
            <?php endif; ?>
        </ul>
        </td>
        <td>
            <?php if ($extension['error']) : ?>
                <p class='alert alert-danger'>
                    <?php hecho($extension['error'])?>
                </p>
            <?php elseif ($extension['warning']) : ?>
                <p class='alert alert-warning'>
                    <?php hecho($extension['warning'])?>
                </p>
            <?php else : ?>
                <p class="alert alert-success">
                    <b>OK</b>
                </p>
            <?php endif;?>
        </td>

    </tr>
<?php endforeach;?>
</table>
</div>

<div class="box">
<h2>Graphe des dépendances des extensions</h2>
<img src="<?php $this->url("Extension/graphique") ?>"  alt="Graphe des dépendances des extensions" />
</div>

<div class="box">
<h2>Version de Pastell</h2>
<div class='alert alert-info'>Cette instance de Pastell est compatible avec les extensions qui nécessitent une des versions de Pastell suivantes:</div>
<ul>
<?php foreach ($pastell_manifest['extensions_versions_accepted'] as $version) : ?>
<li>
    <?php hecho($version)?>
</li>
<?php endforeach;?>
</ul>
</div>

