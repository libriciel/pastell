<?php

/**
 * @var Gabarit $this
 * @var array $extension_info
 */

$extensionId = get_hecho($extension_info['id_e']);
?>

<a class='btn btn-link' href='<?php $this->url('Extension/index')?>'>
    <i class="fas fa-arrow-left"></i>&nbsp;Liste des extensions
</a>

<div class="box">
    <h2>Extension « <?php hecho($extension_info['nom'])?> »</h2>

    <?php if ($extension_info['error']) : ?>
        <div class='alert alert-danger'>
            <?php hecho($extension_info['error-detail'])?>
        </div>
    <?php endif ?>

    <?php if ($extension_info['warning']) : ?>
        <div class='alert alert-warning'>
            <?php hecho($extension_info['warning-detail'])?>
        </div>
    <?php endif ?>

    <table class='table table-striped'>
        <tr>
            <th>Emplacement de l'extension sur le système de fichier</th>
            <td><?php hecho($extension_info['path']); ?></td>
        </tr>
    </table>

    <a href='<?php $this->url("Extension/edition?id_extension=$extensionId"); ?>'
       class='btn btn-primary'
    ><i class="fas fa-pen"></i>&nbsp;Modifier</a>
    <a href='<?php $this->url("Extension/delete?id_extension=$extensionId"); ?>'
       class='btn btn-danger'
    ><i class="fas fa-trash"></i>&nbsp;Supprimer</a>
</div>

<div class='box'>
    <h2>Contenu du fichier manifest</h2>
    <table class='table table-striped'>
        <tr>
            <th>Identifiant</th>
            <td><?php hecho($extension_info['id']) ?></td>
        </tr>
        <tr>
            <th>Nom</th>
            <td><?php hecho($extension_info['manifest']['nom']) ?></td>
        </tr>
        <tr>
            <th>Description</th>
            <td><?php hecho($extension_info['manifest']['description']) ?></td>
        </tr>
        <tr>
            <th>Numéro de version de l'extension</th>
            <td><?php hecho($extension_info['manifest']['version']) ?></td>
        </tr>
        <tr>
            <th>Numéro de version compatible de l'extension</th>
            <td>
                <ul>
                    <?php if (! empty($extension_info['manifest'])) : ?>
                        <?php foreach ($extension_info['manifest']['autre-version-compatible'] as $version) : ?>
                            <li><?php hecho($version) ?></li>
                        <?php endforeach;?>
                    <?php endif; ?>
                </ul>
            </td>
        </tr>

        <tr>
            <th>Version de Pastell attendue</th>
            <td>
                <?php hecho($extension_info['manifest']['pastell-version']) ?>
            </td>
        </tr>
        <tr>
            <th>Extensions attendues</th>
            <td>
                <ul>
                    <?php if (! empty($extension_info['manifest'])) : ?>
                        <?php foreach ($extension_info['manifest']['extension_needed'] as $extension_needed => $extension_needed_info) : ?>
                            <li>
                                <?php hecho($extension_needed)?>&nbsp;
                                (version <?php hecho($extension_needed_info['version'])?>)
                                <?php if (! $extension_needed_info['extension_presente']) :?>
                                    <span class='text_alert'>KO</span>
                                <?php elseif (! $extension_needed_info['extension_version_ok']) :?>
                                    <span class='text_alert'>Version KO</span>
                                <?php endif;?>
                            </li>
                        <?php endforeach;?>
                    <?php endif; ?>
                </ul>
            </td>
        </tr>
    </table>
</div>      

<div class="box">
    <h2>Connecteurs</h2>
    <table class='table table-striped'>
        <tr>
            <th>Nom</th>
            <th>Description</th>
        </tr>
        <?php foreach ($extension_info['connecteur'] as $connecteur) : ?>
            <tr>
                <td><b><?php hecho($connecteur)?></b></td>
                <td>
                    <?php $connecteur_info = $this->getConnecteurDefinitionFiles()->getInfo($connecteur); ?>
                    <?php if (isset($connecteur_info['description'])) : ?>
                        <?php echo nl2br($connecteur_info['description']); ?>
                    <?php endif;?>
                </td>
            </tr>
        <?php endforeach;?>
    </table>
</div>      

<div class="box">
    <h2>Flux</h2>
    <table class='table table-striped'>
        <tr>
            <th>Nom</th>
            <th>Description</th>
        </tr>
        <?php foreach ($extension_info['flux'] as $flux) : ?>
            <tr>
                <td><b><?php hecho($flux)?></b></td>
                <td>
                    <?php $flux_info = $this->getFluxDefinitionFiles()->getInfo($flux); ?>
                    <?php if (isset($flux_info['description'])) : ?>
                        <?php echo nl2br($flux_info['description']); ?>
                    <?php endif;?>
                </td>
            </tr>
        <?php endforeach;?>
    </table>
</div>
