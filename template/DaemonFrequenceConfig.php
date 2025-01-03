<?php

/**
 * @var ConnecteurFrequence[] $connecteur_frequence_list
 *
 */
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
        <?php foreach ($connecteur_frequence_list as $connecteurFrequence) : ?>
            <tr>
                <td>
                    <?php hecho($connecteurFrequence->getConnecteurSelector()) ?>
                    <?php hecho($connecteurFrequence->getInstanceConnecteurAsString()) ?>
                </td>
                <td>
                    <?php hecho($connecteurFrequence->getActionSelector()) ?>
                </td>
                <td>
                    <?php echo nl2br(get_hecho($connecteurFrequence->getExpressionAsString())) ?>
                </td>
                <td>
                    <?php hecho($connecteurFrequence->id_verrou) ?>
                </td>
                <td>
                    <a class='btn btn-primary' href='<?php $this->url("Daemon/connecteurFrequenceDetail?id_cf={$connecteurFrequence->id_cf}") ?>'><i class="fa fa-eye"></i>&nbsp;Visualiser</a>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
</div>
