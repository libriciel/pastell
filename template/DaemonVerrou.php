<?php

/**
 * @var Gabarit $this
 * @var array $job_queue_info_list
 * @var string $return_url
 * @var bool $daemon_edition
 */

?>

<div class='box'>
    <h2>Liste des travaux par files d'attente et par états</h2>
    <table class="table table-striped">
        <tr>
            <th>File d'attente</th>
            <th>État source</th>
            <th>État cible</th>
            <th>Dernier essai</th>
            <th>Nombre total de travaux</th>
            <th>Nombre de travaux suspendus</th>
            <th>Nombre de travaux en retard</th>
            <?php if ($daemon_edition) : ?>
                <th>Action</th>
            <?php endif; ?>
        </tr>

<?php

foreach ($job_queue_info_list as $job_queue_list) :?>
    <tr>
        <td><?php hecho($job_queue_list['id_verrou']); ?></td>
        <td><?php hecho($job_queue_list['etat_source']); ?></td>
        <td><?php hecho($job_queue_list['etat_cible']); ?></td>
        <td><?php hecho($job_queue_list['last_try']); ?></td>
        <td><?php hecho($job_queue_list['count']); ?></td>
        <td><?php hecho($job_queue_list['nb_lock']); ?></td>
        <td><?php hecho($job_queue_list['nb_late']); ?></td>
        <?php if ($daemon_edition) : ?>
            <td>

                <?php
                $lockUrl = sprintf(
                    'Daemon/lock?id_verrou=%s&etat_source=%s&etat_cible=%s&return_url=%s',
                    $job_queue_list['id_verrou'],
                    $job_queue_list['etat_source'],
                    $job_queue_list['etat_cible'],
                    $return_url
                );
                $unlockUrl = sprintf(
                    'Daemon/unlock?id_verrou=%s&etat_source=%s&etat_cible=%s&return_url=%s',
                    $job_queue_list['id_verrou'],
                    $job_queue_list['etat_source'],
                    $job_queue_list['etat_cible'],
                    $return_url
                );
                ?>

                <a href='<?php $this->url($lockUrl); ?>' class="btn btn-warning">
                    <i class="fas fa-lock"></i>&nbsp;
                    Suspendre</a>
                <a href='<?php $this->url($unlockUrl); ?>' class="btn btn-warning">
                    <i class="fas fa-unlock-keyhole"></i>&nbsp;
                    Reprendre</a>
            </td>
        <?php endif; ?>
    </tr>
<?php endforeach; ?>
    </table>
</div>
