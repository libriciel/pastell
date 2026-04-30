<?php

/**
 * @var Gabarit $this
 * @var Job $job
 * @var string $return_url
 * @var bool $daemon_edition
 */
?>
<div class="box">
    <h2>Information sur le travail</h2>
    <table class='table'>
        <tr>
            <th>Type</th>
            <td><?php echo $job->type == Job::TYPE_DOCUMENT ? "Document" : "Connecteur"?></td>
        </tr>
        <tr>
            <th>Entité</th>
            <td style="word-break: break-all;">
                <?php echo $job->entite_denomination ?>
                (<a href='<?php
                $this->url("Entite/detail?id_e={$job->id_e}") ?>'><?php
                    echo $job->id_e ?></a>)
            </td>
        </tr>

        <tr>
            <th>Dernier message</th>
            <td style="word-break: break-all;"><?php echo $job->last_message ?></td>
        </tr>

        <tr>
            <th>Suspension</th>
            <td>
            <?php if ($job->is_lock) : ?>
                <?php
                $unlockUrl = \sprintf(
                    'Daemon/unlock?id_job=%s&return_url=%s',
                    $job->id_job,
                    $return_url
                );
                ?>
                <p class='alert alert-danger'>OUI  <br/>
                    Depuis le <?php echo $this->getFancyDate()->getDateFr($job->lock_since);?>
                <?php if ($daemon_edition) : ?>
                    <a href='<?php $this->url($unlockUrl); ?>' class=" btn-warning btn">
                        <i class="fas fa-unlock-keyhole"></i>&nbsp;

                        Reprendre
                    </a></p>
                <?php endif; ?>
            <?php else : ?>
                <?php
                $lockUrl = \sprintf(
                    'Daemon/lock?id_job=%s&return_url=%s',
                    $job->id_job,
                    $return_url
                );
                ?>
                <?php if ($daemon_edition) : ?>
                    <p>NON <a href='<?php $this->url($lockUrl); ?>' class="btn btn-warning">
                            <i class="fas fa-lock"></i>&nbsp;

                            Suspendre
                        </a></p>
                <?php endif;?>
            <?php endif;?>
            </td>
        </tr>
        <tr>
            <?php if ($job->id_d) : ?>
                <th>Document</th>
            <?php else : ?>
                <th>Connecteur</th>
            <?php endif; ?>
            <td style="word-break: break-all;">
            <?php if ($job->id_d) : ?>
                <?php echo $job->document_titre ?>
                (<a href='<?php
                $this->url("Document/detail?id_e=$job->id_e&id_d=$job->id_d") ?>'><?php
                    hecho($job->id_d) ?></a>)
            <?php endif;?>
            <?php if ($job->id_ce) : ?>
                <?php echo $job->connecteur_libelle ?>
                (<a href='<?php
                $this->url("Connecteur/edition?id_ce=$job->id_ce") ?>'><?php
                    hecho($job->id_ce) ?></a>)
            <?php endif;?>
            </td>
        </tr>
        <tr>
            <th>Etat source</th>
            <td>
                <?php hecho($job->etat_source)?>
            </td>
        </tr>
        <tr>
            <th>Etat cible</th>
            <td>
                <?php hecho($job->etat_cible)?>
            </td>
        </tr>
        <tr>
            <th>Premier essai</th>
            <td><?php echo $this->getFancyDate()->getDateFr($job->first_try); ?></td>
        </tr>
        <tr>
            <th>Dernier essai</th>
            <td><?php echo $this->getFancyDate()->getDateFr($job->last_try); ?></td>
        </tr>
        <tr>
            <th>Nombre d'essai</th>
            <td><?php echo $job->nb_try ?></td>
        </tr>
        <tr>
            <th>Prochain essai</th>

            <td>
                <?php echo $this->getFancyDate()->getDateFr($job->next_try); ?><br/>
                <?php echo $this->getFancyDate()->getTimeElapsed($job->next_try); ?>
            </td>
        </tr>
        <tr>
            <th>File d'attente</th>
            <td><?php hecho($job->id_verrou) ?></td>
        </tr>
    </table>

</div>

