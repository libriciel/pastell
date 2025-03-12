<?php

/**
 * @var Gabarit $this
 * @var ConnecteurFrequence $connecteurFrequence
 * @var array $connecteurFrequenceByFlux
 * @var string $connecteur_hash
 * @var array $usage_flux_list
 * @var array $fieldDataList
 * @var array $connecteur_entite_info
 * @var bool $has_definition
 * @var array $action_possible
 * @var array $job_list
 * @var string $return_url
 * @var bool $daemon_edition
 * @var bool $daemon_global_lecture
 * @var bool $daemon_lecture
 * @var int $id_ce
 * @var Action $action
 * @var bool $actionPermissionOnConnector
 */

$listConnectorsUrl = \sprintf(
    'Entite/connecteur?global=%s&id_e=%s',
    $connecteur_entite_info['global'],
    $connecteur_entite_info['id_e'],
);

?>

<a class='btn btn-link'
   href='<?php echo $listConnectorsUrl; ?>'
><i class="fa fa-arrow-left"></i>&nbsp;Retour à la liste des connecteurs</a>

<div class="box">
    <h2>
        Connecteur <?php hecho($connecteur_entite_info['type']) ?> -
        <?php hecho($connecteur_entite_info['id_connecteur']) ?> :
        <?php hecho($connecteur_entite_info['libelle']) ?>
    </h2>
    <?php
    if ($has_definition) {
        $this->render('DonneesFormulaireDetail');
    } else {
        ?>
        <div class="alert alert-danger">
            Impossible d'afficher les propriétés du connecteur car celui-ci est inconnu sur cette plateforme Pastell
            (<b><?php hecho($connecteur_entite_info['id_connecteur'])?></b>)
        </div>
        <?php
    }
    ?>
    <?php if ($fieldDataList) : ?>
    &nbsp;<a class='btn btn-primary' href="<?php $this->url("Connecteur/editionModif?id_ce=$id_ce") ?>">
        <i class="fa fa-pencil"></i>&nbsp;Modifier
    </a>
    <?php endif ?>

    <?php if ($actionPermissionOnConnector) : ?>
        <?php foreach ($action_possible as $action_name) : ?>
            <form action='Connecteur/action' method='post' style='margin-top:10px; '>
                <?php $this->displayCSRFInput(); ?>
                <input type='hidden' name='id_ce' value='<?php echo $id_ce ?>'/>
                <input type='hidden' name='action' value='<?php echo $action_name ?>'/>

                <button type='submit' class='btn btn-outline-primary'>
                    <i class="fa fa-cogs"></i>&nbsp; <?php hecho($action->getActionName($action_name)) ?>
                </button>
            </form>
        <?php endforeach; ?>
    <?php endif; ?>
</div>


<div class="box">
<h2>Instance du connecteur</h2>
    <table class="table table-striped">
        <tr >
            <th class="w300">Libellé</th>
            <td><?php hecho($connecteur_entite_info['libelle']) ?></td>
            <td>&nbsp;</td>
        </tr>
        <tr >
            <th>Empreinte sha256</th>
            <td><?php hecho($connecteur_hash) ?></td>
            <td>&nbsp;</td>
        </tr>
        <tr>
            <th>Utilisation</th>
            <td>
                <?php
                $links = [];
                foreach ($usage_flux_list as $usage) {
                    $id_e = $usage['id_e'];
                    $flux = $usage['flux'];
                    ob_start();
                    $this->url("Flux/detail?id_e=$id_e&flux=$flux");
                    $url = ob_get_clean();
                    $links[] = "$flux (<a href=\"$url\">$id_e</a>)";
                }
                echo implode(', ', $links);
                ?>
            </td>
            <td>&nbsp;</td>
        </tr>
        <tr>
            <th>Fréquence (action du connecteur)</th>
            <td>
                <?php if ($connecteurFrequence->id_cf) : ?>
                    <?php if ($daemon_global_lecture) : ?>
                        <a href="<?php $this->url('Daemon/connecteurFrequenceDetail?id_cf=' . $connecteurFrequence->id_cf) ?>">
                            <?= $connecteurFrequence->getExpressionAsString() ?>
                        </a>
                    <?php else : ?>
                        <?= nl2br($connecteurFrequence->getExpressionAsString()) ?>
                    <?php endif ?>
                <?php else : ?>
                    <?php echo nl2br($connecteurFrequence->getExpressionAsString()) ?>
                <?php endif ?>
            </td>
            <td>
                <?php hecho($connecteurFrequence->id_verrou) ?>
            </td>
        </tr>
        <?php foreach ($connecteurFrequenceByFlux as $flux => $connecteur) : ?>
        <tr>
            <th>Fréquence (<?php hecho($flux) ?>)</th>
            <td>

                <?php if ($connecteur->id_cf) : ?>
                    <?php if ($daemon_global_lecture) : ?>
                        <a href="<?php $this->url('Daemon/connecteurFrequenceDetail?id_cf=' . $connecteur->id_cf) ?>">
                            <?= nl2br($connecteur->getExpressionAsString()) ?>
                        </a>
                    <?php else : ?>
                        <?= nl2br($connecteur->getExpressionAsString()) ?>
                    <?php endif ?>
                <?php else : ?>
                    <?php echo nl2br($connecteur->getExpressionAsString()) ?>
                <?php endif ?>
                <em>Sauf action particulière</em>
            </td>
            <td>
                <?php hecho($connecteur->id_verrou) ?>
            </td>
        </tr>
        <?php endforeach; ?>

    </table>

    <a class='btn btn-primary' href="<?php $this->url("Connecteur/editionLibelle?id_ce=$id_ce") ?>" >
        <i class="fa fa-pencil"></i>&nbsp;Modifier le libellé
    </a>

    <a class='btn btn-outline-primary' href="<?php $this->url("Connecteur/export?id_ce=$id_ce") ?>" >
        <i class="fa fa-download"></i>&nbsp;Exporter
    </a>
    <a class='btn btn-outline-primary' href="<?php $this->url("Connecteur/import?id_ce=$id_ce") ?>" >
        <i class="fa fa-upload"></i>&nbsp;Importer
    </a>

    <a class='btn btn-danger <?php echo $usage_flux_list ? 'disabled' : '' ?>'
       href="<?php $this->url("Connecteur/delete?id_ce=$id_ce") ?>"
         >
        <i class="fa fa-trash"></i>&nbsp;Supprimer
    </a>
</div>

<div class='box'>
<?php if ($daemon_lecture && $job_list) : ?>
    <h2>Travaux programmés</h2>
    <table class="table table-striped">
        <tr>
            <th>#ID travail</th>
            <th>Suspendu</th>
            <th>Action</th>
            <th>Premier essai</th>
            <th>Dernier essai</th>
            <th>Nombre d'essais</th>
            <th>Dernier message</th>
            <th>Prochain essai</th>
            <th>Verrou</th>
            <th>#ID processus</th>
            <th>PID processus</th>
            <th>Début processus</th>
            <?php if ($daemon_edition) : ?>
                <th>Fonction</th>
            <?php endif; ?>
        </tr>
        <?php foreach ($job_list as $job_info) : ?>
            <tr>
                <td>
                    <?php if ($daemon_global_lecture) : ?>
                        <a href='<?php $this->url("Daemon/detail?id_job={$job_info['id_job']}"); ?>'>
                            <?php echo $job_info['id_job']; ?>
                        </a>
                    <?php else : ?>
                        <?php echo $job_info['id_job']; ?>
                    <?php endif ?>
                </td>
                <td>
                    <?php if ($job_info['is_lock']) : ?>
                        <p class='alert alert-danger'>
                            OUI  <br/>Depuis le <?php echo $this->getFancyDate()->getDateFr($job_info['lock_since']);?><br/>
                        <?php if ($daemon_edition) : ?>
                            <a href='<?php $this->url("Daemon/unlock?id_job={$job_info['id_job']}&return_url={$return_url}") ?>'
                           class=" btn-warning btn"> <i class="fa fa-unlock"></i>&nbsp;Reprendre</a></p>
                        <?php endif;?>
                    <?php else : ?>
                        <p>
                            NON<br/>
                            <?php if ($daemon_edition) : ?>
                                <?php
                                $lockJobUrl = sprintf(
                                    'Daemon/lock?id_job=%s&return_url=%s',
                                    $job_info['id_job'],
                                    $return_url
                                );
                                ?>
                                <a href='<?php $this->url($lockJobUrl); ?>'
                                   class="btn btn-warning"><i class="fa fa-lock"></i>&nbsp;Suspendre</a></p>
                            <?php endif;?>
                    <?php endif;?>
                </td>
                <td><?php hecho($job_info['etat_cible'])?></td>
                <td><?php echo $this->getFancyDate()->getDateFr($job_info['first_try']) ?></td>
                <td><?php echo $this->getFancyDate()->getDateFr($job_info['last_try']) ?></td>
                <td><?php echo $job_info['nb_try'] ?></td>
                <td><?php echo $job_info['last_message'] ?></td>
                <td>
                    <?php echo $this->getFancyDate()->getDateFr($job_info['next_try']) ?><br/>
                    <?php echo $this->getFancyDate()->getTimeElapsed($job_info['next_try'])?>
                </td>
                <td>
                    <?php hecho($job_info['id_verrou']) ?>
                </td>
                <td><?php echo $job_info['id_worker']?></td>
                <td>
                    <?php echo $job_info['pid']?>
                    <?php if ($job_info['pid']) : ?>
                        <?php if (! $job_info['termine']) : ?>
                            <?php if ($daemon_edition) : ?>
                                <?php
                                $killJobUrl = sprintf(
                                    'Daemon/kill?id_worker=%s&return_url=%s',
                                    $job_info['id_worker'],
                                    $return_url
                                );
                                ?>
                            <a href='<?php $this->url($killJobUrl); ?>'
                               class='btn btn-danger'>
                                <i class="fa fa-power-off"></i>&nbsp;
                                Tuer</a>
                            <?php endif; ?>
                        <?php else : ?>
                        <br/><?php echo $job_info['message']?>
                        <?php endif;?>
                    <?php endif;?>
                </td>
                <td>
                    <?php if ($job_info['id_worker']) : ?>
                        <?php echo $this->getFancyDate()->getDateFr($job_info['date_begin'])?><br/>
                        <?php echo $this->getFancyDate()->getTimeElapsed($job_info['date_begin'])?>
                    <?php endif;?>
                </td>
                <td>
                    <?php if ($daemon_edition) : ?>
                        <?php
                        $deleteJobUrl = "Daemon/deleteJob?id_job={$job_info['id_job']}&id_ce={$job_info['id_ce']}";
                        ?>
                        <a href="<?php echo $deleteJobUrl; ?>"
                           class="btn btn-danger"><i class="fa fa-trash"></i>&nbsp;Supprimer</a>
                    <?php endif;?>
                </td>
            </tr>
        <?php endforeach;?>
    </table>
    </div>
<?php endif;?>

<div class="row">
    <div class="col float-right">
        <a class='btn btn-link'
           href='Connecteur/etat?id_ce=<?php echo $id_ce ?>'><i class='fa fa-list-alt'
            ></i>&nbsp;Voir les états du connecteur</a>
    </div>
</div>

