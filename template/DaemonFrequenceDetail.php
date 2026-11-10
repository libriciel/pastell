<?php

/**
 * @var ConnecteurFrequenceData $connecteurFrequenceData
 * @var array{connecteur: string, instance: string, action: string, expression: string} $connecteur_frequence_label
 * @var Gabarit $this
 */

use Pastell\Service\Connecteur\Frequence\ConnecteurFrequenceActionType;
use Pastell\Service\Connecteur\Frequence\ConnecteurFrequenceData;

?>
<a class='btn btn-link' href='<?php $this->url("Daemon/frequenceConfiguration") ?>'>
    <i class="fa fa-arrow-left"></i>&nbsp;Retour à la liste des fréquences
</a>
<div class="box">
    <h2>Détail d'une fréquence</h2>
    <table class='table table-striped'>
        <tr>
            <th class='w200'>Type</th>
            <td><?php hecho($connecteurFrequenceData->type_connecteur ?: 'Tous') ?></td>
        </tr>
        <tr>
            <th class='w200'>Famille de connecteurs</th>
            <td><?php hecho($connecteurFrequenceData->famille_connecteur ?: 'Toutes') ?></td>
        </tr>
        <tr>
            <th class='w200'>Connecteur</th>
            <td><?php hecho($connecteurFrequenceData->id_connecteur ?: 'Tous') ?></td>
        </tr>
        <tr>
            <th class='w200'>Instance de connecteur</th>
            <td>
                <?php if ($connecteurFrequenceData->id_ce) : ?>
                <a href="<?php $this->url("Connecteur/edition?id_ce={$connecteurFrequenceData->id_ce}")?>">
                    <?php hecho($connecteur_frequence_label['instance']) ?>
                </a>
                <?php else : ?>
                    Toutes
                <?php endif ?>
            </td>
        </tr>

        <tr>
            <th class='w200'>Type d'action</th>
            <td><?php hecho($connecteurFrequenceData->action_type ?: 'Tous') ?></td>
        </tr>
        <?php if ($connecteurFrequenceData->action_type === ConnecteurFrequenceActionType::DOCUMENT->value) : ?>
        <tr>
            <th class='w200'>Type de dossier</th>
            <td><?php hecho($connecteurFrequenceData->type_document ?: 'Tous') ?></td>
        </tr>
        <?php endif; ?>
        <tr>
            <th class='w200'>Action</th>
            <td><?php hecho($connecteurFrequenceData->action ?: 'Toutes') ?></td>
        </tr>
        <tr>
            <th class='w200'>Fréquence</th>
            <td>
                <?php echo nl2br(get_hecho($connecteur_frequence_label['expression'])) ?>
            </td>
        </tr>
        <tr>
            <th class='w200'>Verrou</th>
            <td><?php hecho($connecteurFrequenceData->id_verrou) ?></td>
        </tr>

    </table>
    <a class='btn btn-primary'
       href="<?php $this->url("Daemon/editFrequence?id_cf={$connecteurFrequenceData->id_cf}") ?>"
    >
        <i class="fa fa-pencil"></i>&nbsp;Modifier
    </a>
    <a class='btn btn-danger'
       href="<?php $this->url("Daemon/deleteFrequence?id_cf={$connecteurFrequenceData->id_cf}") ?>"
    >
        <i class="fa fa-trash"></i>&nbsp;Supprimer
    </a>
</div>