<?php

declare(strict_types=1);

/**
 * @var Gabarit $this
 * @var string $role_treeselect_data
 * @var string $entity_treeselect_data
 */

?>
<div class="box">
    <form action='<?php $this->url('System/doMagicLinkEdition'); ?>' method='post'>
        <table class='table table-striped'>
            <tr>
                <th class='w300'>
                    <label for="prenom">Prénom de l'intervenant<span class="obl">*</span></label>
                </th>
                <td>
                    <input class="form-control col-md-4" id='prenom' type="text" name='prenom'
                           maxlength="64" required/>
                </td>
            </tr>
            <tr>
                <th class='w300'>
                    <label for="nom">Nom de l'intervenant<span class="obl">*</span></label>
                </th>
                <td>
                    <input class="form-control col-md-4" id='nom' type="text" name='nom'
                           maxlength="64" required/>
                </td>
            </tr>
            <tr>
                <th class='w300'>
                    <label for="mail">Email de l'intervenant<span class="obl">*</span></label>
                </th>
                <td>
                    <input class="form-control col-md-4" id='mail' type="email" name='mail'
                           maxlength="256" required/>
                </td>
            </tr>
            <tr>
                <th class='w300'>
                    <label for="motif">Motif de l'intervention<span class="obl">*</span></label>
                    <p class="form_commentaire">
                        Conservé dans le journal pour la traçabilité de l'accès.
                    </p>
                </th>
                <td>
                    <input class="form-control col-md-4" id='motif' type="text" name='motif'
                           maxlength="128" required/>
                </td>
            </tr>
            <tr>
                <th class='w300'>
                    <label for="duration">Durée d'intervention (en heures)<span class="obl">*</span></label>
                    <p class="form_commentaire">
                        Délai au-delà duquel le lien expire (24 heures maximum).
                    </p>
                </th>
                <td>
                    <input class="form-control col-md-4" id='duration' type="number" name='duration'
                           min="1" max="<?= \Pastell\Service\MagicLink\MagicLinkService::MAX_DURATION_IN_HOURS ?>"
                           step="1" value="1" required/>
                </td>
            </tr>
            <tr>
                <th class='w300'>
                    <label for="role_id">Rôle de base<span class="obl">*</span></label>
                </th>
                <td>
                    <input id='role_id' type='hidden' name='role' value=''/>
                    <div class="treeselect-role notification-select"></div>
                    <?php
                    $this->renderTreeSelect(
                        $role_treeselect_data,
                        'treeselect-role',
                        'role_id',
                        'Sélectionner un rôle'
                    ); ?>
                </td>
            </tr>
            <tr>
                <th class='w300'>
                    <label for="role-entity_id">Entité de base<span class="obl">*</span></label>
                </th>
                <td>
                    <input id='role-entity_id' type='hidden' name='id_e' value=''/>
                    <div class="treeselect-role-entity notification-select"></div>
                    <?php
                    $this->renderTreeSelect(
                        $entity_treeselect_data,
                        'treeselect-role-entity',
                        'role-entity_id',
                        'Sélectionner une entité',
                        3
                    ); ?>
                </td>
            </tr>
        </table>
        <?php $this->displayCSRFInput() ?>
        <button type="submit" class="btn btn-primary">
            <i class="fas fa-link"></i>&nbsp;Générer le lien
        </button>
    </form>
</div>
