<?php

declare(strict_types=1);

/**
 * @var Gabarit $this
 * @var string $search
 * @var int $offset
 * @var array $entity_list
 * @var int $nb_allocated_workers
 * @var int $nb_workers
 * @var int $nb_shared_workers
 * @var int $global_daemon_status
 */
?>

<div class="box">
    <table class='table table-striped'>
        <div style='width:100%;'>
            <h2>Processus</h2>
        </div>
        <tr>
            <th class='w200'>
                <label for="type_connecteur">Nombre de processus totaux</label>
            </th>
            <td>
                <?= $nb_workers?>
            </td>
        </tr>
        <tr>
            <th class='w200'>
                <label for="type_connecteur">Nombre de processus partagés</label>
            </th>
            <td>
                <?= $nb_shared_workers?>
            </td>
        </tr>
        <tr>
            <th class='w200'>
                <label for="type_connecteur">Nombre de processus dédiés</label>
            </th>
            <td>
                <?= $nb_allocated_workers?>
            </td>
        </tr>
    </table>

    <a class='btn btn-primary' href="Daemon/editConfiguration">
        <i class="fas fa-pen"></i> 
        Modifier
    </a>
    <br>
    <a class="btn btn-outline-primary"  href="<?= "Daemon/create" ?>" style="margin-top:10px;">
        <i class="fas fa-plus"></i>
         Créer un nouveau gestionnaire de tâches
    </a>
</div>

<div class="box">
    <div style='width:100%;'>
        <h2>Liste des gestionnaires de tâches</h2>
    </div>
    <div class="row">
        <form action='<?php $this->url('Daemon/configuration') ?>' method='get' class="pt-3 input-group col-md-4">
            <input type='text' name='search' id='search'
                   value='<?php echo $search; ?>' class="form-control"
                   placeholder="Rechercher par dénomination d'entité"/>
            <button type='submit' class='btn btn-primary btn-search' id="search-entite"><i class='fas fa-search'></i>
            </button>
            <div class="col-md-8"></div>
        </form>
    </div>

    <?php $this->suivantPrecedent($offset, 20, count($entity_list), "Daemon/configuration?search=$search"); ?>
    <h3 id="title-result" class="ls-off title-result">Résultat(s) de la recherche</h3>
    <form action='<?php $this->url('Daemon/allocate'); ?>' method='post' class="pt-3 input-group col-md-4">
        <?php $this->displayCSRFInput(); ?>
        <table class="table table-striped">
            <tr>
                <th class='w200'>Entité</th>
                <th>État du gestionnaire de tâches</th>
                <th>Processus alloué(s)</th>
                <th>Action</th>
            </tr>

            <?php foreach ($entity_list as $entity) : ?>
                <tr>
                    <td><a href=<?= "Entite/detail?id_e={$entity['id_e']}" ?>><?php hecho($entity['denomination']) ?></a></td>
                    <td>
                        <?php if ($entity['id_daemon'] !== null) : ?>
                            <?php if ($entity['state'] && $global_daemon_status) : ?>
                                <p class="badge bg-info">
                                    Actif
                                </p>
                            <?php elseif ($entity['state'] === 0 || !$global_daemon_status) : ?>
                                <p class="badge bg-danger">
                                    Inactif
                                </p>
                            <?php endif ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($entity['id_daemon'] !== null) : ?>
                            <input type="number" id="daemon_<?= $entity['id_daemon'] ?>"
                                   name="data[<?= $entity['id_daemon'] ?>]" value="<?= $entity['nb_workers'] ?>" min="1"
                                   max="<?= $nb_workers - 1 ?>"/>
                        <?php else : ?>
                            <?= $entity['nb_workers'] ?>
                        <?php endif ?>
                    </td>
                    <td>
                        <?php if ($entity['id_daemon'] !== null && $global_daemon_status) : ?>
                            <?php
                            if ($entity['state']) : ?>
                                <a href="<?= "Daemon/daemonStop?id_daemon={$entity['id_daemon']}" ?>" class="btn btn-danger"
                                   id="arreter_deamon"
                                   name="arreter_deamon"><i class="fas fa-stop"></i>&nbsp; Arrêter
                                </a>
                            <?php else : ?>
                                <a href="<?= "Daemon/daemonStart?id_daemon={$entity['id_daemon']}" ?>" class="btn btn-success">
                                    <i class="fas fa-play"></i>&nbsp;Démarrer
                                </a>
                            <?php endif ?>
                            <a class='btn btn-danger'
                               href='Daemon/deleteDaemon?id_daemon=<?=$entity['id_daemon']?>'
                            ><i class="fas fa-trash"></i>&nbsp;Supprimer</a>
                        <?php endif ?>
                    </td>
                </tr>

            <?php endforeach; ?>
        </table>
        <button type="submit" class="btn btn-primary">
            <i class="fas fa-floppy-disk"></i>&nbsp;Enregistrer
        </button>
    </form>
</div>
