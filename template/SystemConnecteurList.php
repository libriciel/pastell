<?php

/** @var $all_connecteur_globaux array */
/** @var $all_connecteur_entite array */

use Pastell\Configuration\ConnectorConfiguration;

?>
<div class="box">
    <h2>Connecteurs globaux</h2>
    <table class='table table-striped'>
        <tr>
            <th class="w200">Nom symbolique</th>
            <th class="w200">Libellé</th>
            <th>Description</th>
            <th>Restriction</th>
            <th>Validation</th>
        </tr>
        <?php foreach ($all_connecteur_globaux as $id_connecteur => $connecteur) : ?>
            <tr>
                <td><a href="<?php $this->url("/System/connecteurDetail?id_connecteur=$id_connecteur&scope=global")?>"><?php hecho($id_connecteur); ?></a></td>
                <td><?php hecho($connecteur['nom']); ?></td>
                <td><?php echo nl2br(htmlentities(isset($connecteur['description']) ? $connecteur['description'] : ''), ENT_QUOTES); ?></td>
                <td>
                    <?php if ($connecteur['list_restriction_pack']) : ?>
                        <?php hecho(implode(", ", $connecteur['list_restriction_pack'])); ?>
                    <?php endif;?>
                </td>
                <td>
                    <?php if ($connecteur['is_valid']) : ?>
                        <p class="badge bg-success">
                            Valide
                        </p>
                    <?php else : ?>
                        <a href="<?php $this->url("/System/connecteurDetail?id_connecteur=$id_connecteur&scope=global")?>">
                            <p class="badge bg-danger">
                                    Erreur
                            </p>
                        </a>
                    <?php endif ?>
                </td>
            </tr>
        <?php endforeach;?>
    </table>
</div>


<div class="box">
    <h2>Connecteurs d'entité</h2>
    <table class='table table-striped'>
        <tr>
            <th class="w200">Nom symbolique</th>
            <th class="w200">Libellé</th>
            <th>Description</th>
            <th>Restriction</th>
            <th class="w100">Création sur l'entité racine</th>
            <th>Validation</th>
        </tr>
        <?php foreach ($all_connecteur_entite as $id_connecteur => $connecteur) : ?>
            <tr>
                <td><a href="<?php $this->url("/System/connecteurDetail?id_connecteur=$id_connecteur&scope=entite")?>"><?php hecho($id_connecteur); ?></a></td>
                <td><?php hecho($connecteur['nom']); ?></td>
                <td><?php echo nl2br(htmlentities(isset($connecteur['description']) ? $connecteur['description'] : ''), ENT_QUOTES); ?></td>
                <td>
                    <?php if ($connecteur['list_restriction_pack']) : ?>
                        <?php hecho(implode(", ", $connecteur['list_restriction_pack'])); ?>
                    <?php endif;?>
                </td>
                <td class="text-center">
                    <?php if ($connecteur[ConnectorConfiguration::ALLOW_ON_ENTITE_RACINE]) : ?>
                        <p class="badge bg-success">
                            Oui
                        </p>
                    <?php else : ?>
                        <p class="badge bg-danger">
                            Non
                        </p>
                    <?php endif ?>
                </td>
                <td>
                    <?php if ($connecteur['is_valid']) : ?>
                        <p class="badge bg-success">
                            Valide
                        </p>
                    <?php else : ?>
                        <a href="<?php $this->url("/System/connecteurDetail?id_connecteur=$id_connecteur&scope=entite")?>">
                            <p class="badge bg-danger">
                                Erreur
                            </p>
                        </a>
                    <?php endif ?>
                </td>
            </tr>
        <?php endforeach;?>
    </table>
</div>
<?php if (! empty($all_connecteur_globaux_restricted)) : ?>
    <div class="box">
        <h2>Connecteurs globaux indisponibles sur la plateforme</h2>
        <table class='table table-striped'>
            <tr>
                <th class="w200">Nom symbolique</th>
                <th class="w200">Libellé</th>
                <th>Restriction</th>
            </tr>
            <?php foreach ($all_connecteur_globaux_restricted as $id_connecteur => $connecteur) : ?>
                <tr>
                    <td><?php hecho($id_connecteur); ?></td>
                    <td><?php hecho($connecteur['nom']); ?></td>
                    <td>
                        <?php if ($connecteur['list_restriction_pack']) : ?>
                            <?php hecho(implode(", ", $connecteur['list_restriction_pack'])); ?>
                        <?php endif;?>
                    </td>
                </tr>
            <?php endforeach;?>
        </table>
    </div>
<?php endif;?>
<?php if (! empty($all_connecteur_entite_restricted)) : ?>
    <div class="box">
        <h2>Connecteurs d'entité indisponibles sur la plateforme</h2>
        <table class='table table-striped'>
            <tr>
                <th class="w200">Nom symbolique</th>
                <th class="w200">Libellé</th>
                <th>Restriction</th>
            </tr>
            <?php foreach ($all_connecteur_entite_restricted as $id_connecteur => $connecteur) : ?>
                <tr>
                    <td><?php hecho($id_connecteur); ?></td>
                    <td><?php hecho($connecteur['nom']); ?></td>
                    <td>
                        <?php if ($connecteur['list_restriction_pack']) : ?>
                            <?php hecho(implode(", ", $connecteur['list_restriction_pack'])); ?>
                        <?php endif;?>
                    </td>
                </tr>
            <?php endforeach;?>
        </table>
    </div>
<?php endif;?>
