<?php

/**
 * @var Gabarit $this
 * @var array $info
 * @var int $id_e
 * @var string $id_d
 * @var DonneesFormulaire $donneesFormulaire
 * @var Authentification $authentification
 * @var DocumentEmail $documentEmail
 * @var DocumentActionEntite $documentActionEntite
 * @var ActionPossible $actionPossible
 * @var DocumentType $documentType
 * @var array $infoEntite
 * @var int $page
 * @var Action $theAction
 * @var array $document_email_reponse_list
 * @var bool $is_super_admin
 * @var bool $daemon_edition
 * @var bool $daemon_global_lecture
 * @var bool $daemon_lecture
 * @var array|bool $job_list
 * @var string $return_url
 * @var bool $droit_erreur_fatale
 * @var array $all_action
 */

use Pastell\Helpers\UsernameDisplayer;

$usernameDisplayer = new UsernameDisplayer();

$backTitle = sprintf('Liste des "%s" de %s', $documentType->getName(), $infoEntite['denomination']);
?>
<a class='btn btn-link'
   href='Document/list?type=<?php echo $info['type']?>&id_e=<?php echo $id_e?>&last_id=<?php echo $id_d ?>'>
<i class="fa fa-arrow-left"></i>&nbsp;<?php hecho($backTitle); ?></a>

<?php if ($donneesFormulaire->getNbOnglet() > 1) : ?>
        <ul class="nav nav-tabs" style="margin-top:10px;">
            <?php foreach ($donneesFormulaire->getOngletList() as $page_num => $name) : ?>
                <li class="nav-item" >
                    <a class="nav-link <?php echo ($page_num == $page) ? 'active' : '' ?>"
                       href='<?php $this->url("Document/detail?id_d=$id_d&id_e=$id_e") ?>&page=<?php echo $page_num?>'>
                    <?php echo $name?>
                    </a>
                </li>
            <?php endforeach;?>
        </ul>
<?php endif; ?>

<div class="box">
    <?php $this->render('DonneesFormulaireDetail'); ?>

    <table>
        <tr>
            <?php
            foreach ($actionPossible->getActionPossible($id_e, $authentification->getId(), $id_d) as $action_name) :
                if ($theAction->getProperties($action_name, 'no-show')) {
                    continue;
                }
                ?>
                <td>
                    <form action='Document/action' method='post'>
                        <?php $this->displayCSRFInput() ?>
                        <input type='hidden' name='id_d' value='<?php echo $id_d; ?>'/>
                        <input type='hidden' name='id_e' value='<?php echo $id_e; ?>'/>
                        <input type='hidden' name='page' value='<?php echo $page; ?>'/>

                        <input type='hidden' name='action' value='<?php echo $action_name; ?>'/>

                        <?php if (\in_array($action_name, ['supression', 'suppression'], true)) {
                            $submitButtonClass = 'btn-danger';
                        } elseif ($action_name === 'modification') {
                            $submitButtonClass = 'btn-primary';
                        } else {
                            $submitButtonClass = 'btn-outline-primary';
                        }
                        ?>
                        <button type="submit" class="btn <?php echo $submitButtonClass; ?>"
                        ><i class="fa <?php
                            $icon = [
                                'supression' => 'fa-trash',
                                'suppression' => 'fa-trash',
                                'modification' => 'fa-pencil',
                            ];
                            if (isset($icon[$action_name])) {
                                echo $icon[$action_name];
                            } else {
                                echo 'fa-cogs';
                            }
                            ?>
        "></i>&nbsp; <?php hecho($theAction->getDoActionName($action_name)); ?></button>
                    </form>
                </td>
            <?php endforeach; ?>
        </tr>
    </table>

</div>

<?php
$infoDocumentEmail = $documentEmail->getInfo($id_d);
if ($infoDocumentEmail) :
    $reponse_column = [];
    foreach ($infoDocumentEmail as $i => $infoEmail) {
        if ($infoEmail['reponse']) {
            $reponse = json_decode($infoEmail['reponse']);
            foreach ($reponse as $reponse_key => $reponse_value) {
                if (!in_array($reponse_key, $reponse_column)) {
                    $reponse_column[] = $reponse_key;
                }
                $infoDocumentEmail[$i][$reponse_key] = $reponse_value;
            }
        }
    }
    ?>
    <div class="box">
        <h2>Utilisateurs destinataires du message</h2>

        <table class="table table-striped">
            <tr>
                <th class="w200">Email</th>
                <th>Type</th>
                <th>Date d'envoi</th>
                <th>Dernier envoi</th>
                <th>Nombre d'envois</th>
                <th>Lecture</th>
                <?php foreach ($reponse_column as $reponse_column_name) : ?>
                    <th><?php hecho($reponse_column_name); ?></th>
                <?php endforeach; ?>
                <?php if ($document_email_reponse_list) : ?>
                    <th>Réponse</th>
                <?php endif; ?>
                <?php
                if (
                    $actionPossible->isActionPossible(
                        $id_e,
                        $this->getAuthentification()->getId(),
                        $id_d,
                        'renvoi'
                    )
                ) : ?>
                <th>&nbsp;
                </th>
                <?php endif;?>

            </tr>

            <?php foreach ($infoDocumentEmail as $infoEmail) : ?>
                <tr>
                    <td><?php hecho($infoEmail['email']); ?></td>
                    <td><?php echo DocumentEmail::getChaineTypeDestinataire($infoEmail['type_destinataire']); ?></td>
                    <td><?php echo time_iso_to_fr($infoEmail['date_envoie']); ?></td>
                    <td><?php echo time_iso_to_fr($infoEmail['date_renvoi']); ?></td>
                    <td><?php echo $infoEmail['nb_renvoi']; ?></td>
                    <td>
                        <?php if ($infoEmail['lu']) : ?>
                            <p class="badge bg-success"><?php echo time_iso_to_fr($infoEmail['date_lecture']) ?></p>
                        <?php elseif ($infoEmail['has_error']) : ?>
                            <?php
                            $mailsecErrorUrl = \sprintf(
                                'Document/mailsecError?id_de=%s&id_e=%s',
                                $infoEmail['id_de'],
                                $id_e,
                            );
                            ?>
                            <a href="<?php hecho($mailsecErrorUrl); ?>"
                               target="_blank">
                                <p class="badge bg-important">Erreur possible !</p>
                            </a>
                        <?php else : ?>
                            Non
                        <?php endif; ?>
                    </td>
                    <?php foreach ($reponse_column as $reponse_column_name) : ?>
                        <?php if (isset($infoEmail[$reponse_column_name])) : ?>
                            <td><?php hecho($infoEmail[$reponse_column_name]) ?></td>
                        <?php elseif ($infoEmail['type_destinataire'] == "to") : ?>
                            <td></td>
                        <?php else : ?>
                            <td>--</td>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <?php if ($document_email_reponse_list) : ?>
                        <td>
                            <?php if (isset($document_email_reponse_list[$infoEmail['id_de']])) :
                                $reponse_info = $document_email_reponse_list[$infoEmail['id_de']];
                                ?>
                                <?php if ($reponse_info['has_date_reponse']) : ?>
                                    <p class="badge bg-success">
                                        <?php echo time_iso_to_fr($reponse_info['date_reponse']) ?>
                                    </p>
                                <?php endif; ?>
                                <a href="<?php $this->url(sprintf(
                                    '/Document/detailMailReponse?id_e=%s&id_d=%s&id_d_reponse=%s',
                                    $id_e,
                                    $id_d,
                                    $reponse_info['id_d_reponse']
                                )); ?>"
                                   class="badge <?php echo $reponse_info['is_lu'] ? 'bg-light text-dark' : 'bg-info' ?>"
                                >
                                    <?php hecho($reponse_info['titre'] ?: 'Voir'); ?>
                                </a>
                            <?php endif; ?>
                        </td>
                    <?php endif; ?>

                    <?php
                    if (
                        $actionPossible->isActionPossible(
                            $id_e,
                            $this->getAuthentification()->getId(),
                            $id_d,
                            'renvoi'
                        )
                    ) : ?>
                        <td>
                            <form action='Document/action' method='post'>
                                <?php $this->displayCSRFInput(); ?>
                                <input type='hidden' name='id_d' value='<?php echo $id_d; ?>'/>
                                <input type='hidden' name='id_e' value='<?php echo $id_e; ?>'/>
                                <input type='hidden' name='id_de' value='<?php echo $infoEmail['id_de']; ?>'/>
                                <input type='hidden' name='page' value='<?php echo $page; ?>'/>
                                <input type='hidden' name='action' value='renvoi'/>
                                <button type="submit" class="btn btn-outline-primary">
                                    <i class="fa fa-cogs"></i>&nbsp;Envoyer à nouveau
                                </button>
                            </form>
                        </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>

<?php endif; ?>

<div class="box">
    <h2>États du dossier</h2>

    <table class="table table-striped">
        <tr>
            <th class="w300">État</th>
            <th class="w200">Date</th>
            <th class="w200">Utilisateur</th>
            <th>Journal</th>
        </tr>

        <?php foreach ($documentActionEntite->getAction($id_e, $id_d) as $action) : ?>
            <tr>
                <td><?php hecho($theAction->getActionName($action['action'])); ?></td>
                <td><?php echo time_iso_to_fr($action['date']); ?></td>
                <td>
                    <?php echo $usernameDisplayer->getUsername($action); ?>
                </td>
                <td>
                    <?php if ($action['id_j']) : ?>
                        <?php
                        $journalDetailQueryParams = sprintf(
                            'id_j=%s&id_d=%s&id_e=%d&type=%s',
                            $action['id_j'],
                            $id_d,
                            $id_e,
                            $info['type']
                        ); ?>
                        <a href='Journal/detail?<?php echo $journalDetailQueryParams; ?>'
                           title="Consulter le détail des événements"
                        >
                            <i class="fa fa-eye"></i>
                        </a>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
    <div class="row">
        <div class="col float-right">
            <a class='btn btn-link'
               href='Journal/index?id_e=<?php echo $id_e ?>&id_d=<?php echo $id_d ?>&type=<?php echo $info['type'] ?>'
            ><i class='fa fa-list-alt'></i>&nbsp;Voir le journal des événements</a>
        </div>
    </div>

</div>

<?php if ($daemon_lecture && $job_list) : ?>
    <div class="box">
        <a class="collapse-link" data-bs-toggle="collapse" data-bs-target="#daemonCollapse">
            <h2><i class="fa fa-plus-square"></i>&nbsp;Travaux programmés</h2>
        </a>
        <div class="collapse" id="daemonCollapse">
            <div class='box'>
                <table class="table table-striped">
                    <tr>
                        <th>#ID travail</th>
                        <th>Suspendu</th>
                        <th>Daemon</th>
                        <th>État source<br/>État cible</th>
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
                                        <?= $job_info['id_job'] ?>
                                    </a>
                                <?php else : ?>
                                    <?= $job_info['id_job'] ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                $daemonQueryParams = 'id_job=' . $job_info['id_job'] . '&return_url=' . $return_url;
                                ?>
                                <?php if ($job_info['is_lock']) : ?>
                                    <p class='alert alert-danger'>
                                        OUI <br/>
                                        Depuis le <?php
                                        echo $this->getFancyDate()->getDateFr($job_info['lock_since']);
                                        ?><br/>
                                        <?php if ($daemon_edition) : ?>
                                            <a href='<?php $this->url("Daemon/unlock?$daemonQueryParams"); ?>'
                                               class=" btn-warning btn">
                                                <i class="fa fa-unlock-alt"></i>&nbsp;
                                                Reprendre
                                            </a>
                                        <?php endif; ?>
                                    </p>
                                <?php else : ?>
                                    <p>
                                        NON <br/>
                                        <?php if ($daemon_edition) : ?>
                                            <a href='<?php $this->url("Daemon/lock?$daemonQueryParams"); ?>'
                                               class="btn btn-warning">
                                                <i class="fa fa-lock"></i>&nbsp;
                                                Suspendre
                                            </a>
                                        <?php endif; ?>
                                    </p>
                                <?php endif; ?>
                            </td>
                            <td><?php hecho($job_info['id_daemon']); ?><br/>
                            <td><?php hecho($job_info['etat_source']); ?><br/>
                                <?php hecho($job_info['etat_cible']); ?></td>
                            <td><?php echo $this->getFancyDate()->getDateFr($job_info['first_try']); ?></td>
                            <td><?php echo $this->getFancyDate()->getDateFr($job_info['last_try']); ?></td>
                            <td><?php echo $job_info['nb_try']; ?></td>
                            <td><?php hecho($job_info['last_message']); ?></td>
                            <td>
                                <?php echo $this->getFancyDate()->getDateFr($job_info['next_try']); ?><br/>
                                <?php echo $this->getFancyDate()->getTimeElapsed($job_info['next_try']); ?>
                            </td>
                            <td>
                                <?php hecho($job_info['id_verrou']); ?>
                            </td>
                            <td><?php echo $job_info['id_worker']; ?></td>
                            <td>
                                <?php echo $job_info['pid']; ?>
                                <?php if ($job_info['pid']) : ?>
                                    <?php if (! $job_info['termine']) : ?>
                                        <?php if ($daemon_edition) : ?>
                                            <?php
                                            $killUrl = \sprintf(
                                                'Daemon/kill?id_worker=%s&return_url=%s',
                                                $job_info['id_worker'],
                                                $return_url
                                            );
                                            ?>
                                            <a href='<?php $this->url($killUrl); ?>'
                                               class='btn btn-danger'>
                                                <i class="fa fa-power-off"></i>&nbsp;Tuer
                                            </a>
                                        <?php endif; ?>
                                    <?php else : ?>
                                        <br/><?php echo $job_info['message']; ?>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($job_info['id_worker']) : ?>
                                    <?php echo $this->getFancyDate()->getDateFr($job_info['date_begin']); ?><br/>
                                    <?php echo $this->getFancyDate()->getTimeElapsed($job_info['date_begin']); ?>
                                <?php endif; ?>
                            </td>
                            <?php if ($daemon_edition) : ?>
                                <td>
                                    <?php
                                    $deleteJobUrl = \sprintf(
                                        'Daemon/deleteJobDocument?id_job=%s&id_e=%s&id_d=%s',
                                        $job_info['id_job'],
                                        $id_e,
                                        $id_d,
                                    );
                                    ?>

                                        <a href="<?php echo $deleteJobUrl; ?>"
                                           class="btn btn-danger">
                                            <i class="fa fa-trash"></i>&nbsp;
                                            Supprimer
                                        </a>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </table>

                <?php if ($droit_erreur_fatale) : ?>
                    <form action='Document/action' method='post'>
                        <?php $this->displayCSRFInput(); ?>
                        <input type='hidden' name='id_d' value='<?php echo $id_d; ?>'/>
                        <input type='hidden' name='id_e' value='<?php echo $id_e; ?>'/>
                        <input type='hidden' name='page' value='<?php echo $page; ?>'/>
                        <input type='hidden' name='action' value='fatal-error'/>

                        <button type='submit' class='btn btn-danger'>
                            <i class="fa fa-exclamation-triangle"></i>&nbsp;Passer en erreur fatale
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php endif; ?>
<?php if ($is_super_admin) : ?>
    <div class="box">
        <a class="collapse-link" data-bs-toggle="collapse" data-bs-target="#collapseExample">
            <h2><i class="fa fa-plus-square"></i>&nbsp;Administration avancée</h2>
        </a>

        <div class="collapse" id="collapseExample">
            <div class="box">
                <h3>Modification manuelle de l'état</h3>

                <div class='alert alert-danger'>
                    <b>Attention !</b> Rien ne garantit la cohérence du nouvel état !
                </div>
                <form action='<?php $this->url('Document/changeEtat'); ?>' method='post'>
                    <?php $this->displayCSRFInput(); ?>
                    <input type='hidden' name='id_e' value='<?php echo $id_e; ?>'/>
                    <input type='hidden' name='id_d' value='<?php echo $id_d; ?>'/>
                    Nouvel état : <select name='action' class="form-select">
                        <option value=''></option>
                        <?php foreach ($all_action as $etat => $libelle_etat) : ?>
                            <option value='<?php echo $etat; ?>'>
                                <?php echo $libelle_etat; ?> [<?php echo $etat; ?>]
                            </option>
                        <?php endforeach; ?>
                    </select><br/>
                    Texte à mettre dans le journal : <input class="form-control" type='text' value='' name='message'>
                    <br/>
                    <button type="submit"
                            class="btn btn-danger"><i class="fa fa-floppy-o"
                        ></i>&nbsp;Valider le changement d'état
                    </button>

                </form>
            </div>
        </div>
    </div>
<?php endif; ?>
