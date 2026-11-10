<?php

/**
 * @var ConnecteurFrequenceData[] $connecteur_frequence_list
 * @var array<array{connecteur: string, instance: string, action: string, expression: string}> $connecteur_frequence_label_list
 */

use Pastell\Service\Connecteur\Frequence\ConnecteurFrequenceData;

?>
<div class='box'>
    <h2>Liste des fréquences</h2>
    <table class="table table-striped">
        <tr>
            <th>Connecteur(s)</th>
            <th>Action(s)</th>
            <th>Expression de fréquence</th>
            <th>Verrou</th>
            <th>&nbsp;</th>
        </tr>
        <?php foreach ($connecteur_frequence_list as $i => $connecteurFrequenceData) : ?>
            <tr>
                <td>
                    <?php hecho($connecteur_frequence_label_list[$i]['connecteur']) ?>
                    <?php hecho($connecteur_frequence_label_list[$i]['instance']) ?>
                </td>
                <td>
                    <?php hecho($connecteur_frequence_label_list[$i]['action']) ?>
                </td>
                <td>
                    <?php echo nl2br(get_hecho($connecteur_frequence_label_list[$i]['expression'])) ?>
                </td>
                <td>
                    <?php hecho($connecteurFrequenceData->id_verrou) ?>
                </td>
                <td>
                    <a class='btn btn-primary' href='<?php $this->url("Daemon/connecteurFrequenceDetail?id_cf={$connecteurFrequenceData->id_cf}") ?>'><i class="fa fa-eye"></i>&nbsp;Visualiser</a>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
</div>
