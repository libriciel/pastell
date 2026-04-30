<?php

/**
 * @var Gabarit $this
 * @var string $id_d
 * @var int $id_e
 * @var array $infoEntite
 * @var RoleUtilisateur $roleUtilisateur
 * @var Authentification $authentification
 * @var int $offset
 * @var int $limit
 * @var int $count
 * @var int $id_u
 * @var string $recherche
 * @var string $type
 * @var array $all
 */

use Pastell\Helpers\UsernameDisplayer;

$usernameDisplayer = new UsernameDisplayer();

$exportUrl = sprintf(
    'Journal/export?format=csv&offset=0&limit=%s&id_e=%s&type=%s&id_d=%s&id_u=%s&recherche=%s',
    $count,
    $id_e,
    $type,
    $id_d,
    $id_u,
    $recherche
);

?>
<?php if ($id_d) : ?>
    <a class='btn btn-link' href='Journal/index?id_e=<?php echo $id_e ?>'>
        <i class="fas fa-arrow-left"></i>&nbsp;Journal de <?php hecho($infoEntite['denomination']); ?>
    </a>
<?php endif;?>

<?php if ($roleUtilisateur->hasDroit($authentification->getId(), "journal:lecture", $id_e)) :
    $this->SuivantPrecedent(
        $offset,
        $limit,
        $count,
        "Journal/index?id_e=$id_e&id_u=$id_u&recherche=$recherche&type=$type&id_d=$id_d"
    );
    ?>
    <div class="box">

        <h2>Journal des événements (extraits)</h2>

        <form action="Journal/index" method='get' class="col-5">
            <input type='hidden' name='id_e' value='<?php echo $id_e?>'/>
            <input type='hidden' name='type' value='<?php hecho($type); ?>'/>
            <input type='hidden' name='id_d' value='<?php hecho($id_d); ?>'/>
            <input type='hidden' name='id_u' value='<?php echo $id_u?>'/>

            <div class="input-group">
                <input type='text'
                       name='recherche'
                       class="form-control input-search"
                       value='<?php hecho($recherche); ?>'
                       placeholder="Date, dossier, message"
                />
                <button type='submit' class='btn btn-primary btn-search'><i class='fas fa-search'></i></button>
                <a class='btn btn-outline-primary ms-2' href='<?php hecho($exportUrl); ?>'>
                    <i class='fas fa-download'></i>&nbsp;Exporter le journal
                </a>
            </div>

        </form>

        <br/>

        <h3 id="title-result" class="ls-off title-result">Résultat(s) de la recherche</h3>
        <table class="table table-striped">
            <tr>
                <th>Numéro</th>
                <th>Date</th>
                <th>Type</th>
                <th>Entité</th>
                <th>SIREN</th>
                <th>Utilisateur</th>
                <th>Dossier</th>
                <th>État</th>
                <th>Message</th>
                <th>Horodatage</th>
            </tr>
            <?php foreach ($all as $i => $ligne) : ?>
                <tr>
                    <td>
                        <?php
                        $journalDetailUrl = sprintf(
                            'Journal/detail?id_j=%s&id_d=%s&type=%s&id_e=%s&offset=%s',
                            $ligne['id_j'],
                            $id_d,
                            $type,
                            $id_e,
                            $offset
                        );
                        ?>
                        <a href='<?php hecho($journalDetailUrl); ?>'><?php echo $ligne['id_j']?></a>
                    </td>
                    <td><?php echo time_iso_to_fr($ligne['date']) ?></td>
                    <td><?php echo $this->getJournal()->getTypeAsString($ligne['type']); ?></td>
                    <td>
                        <a href='Entite/detail?id_e=<?php echo $ligne['id_e'] ?>'>
                            <?php hecho($ligne['denomination'] ?? $ligne['id_e'])?>
                        </a>
                    </td>
                    <td><?php hecho($ligne['siren']) ?></td>
                    <td><?php echo $usernameDisplayer->getUsername($ligne); ?></td>
                    <td>
                        <?php if ($ligne['id_d']) : ?>
                            <a href='<?php $this->url("Document/detail?id_d={$ligne['id_d']}&id_e={$ligne['id_e']}"); ?>'>
                                <?php hecho($ligne['titre'] ?: $ligne['id_d'])?>
                            </a>
                        <?php else : ?>
                            N/A
                        <?php endif;?>
                    </td>
                    <td>
                        <?php hecho($ligne['action_libelle']); ?>
                    </td>

                    <td><?php hecho($ligne['message']) ?></td>
                    <td><?php if ($ligne['date_horodatage'] !== '0000-00-00 00:00:00') : ?>
                            <?php echo time_iso_to_fr($ligne['date_horodatage']) ?>
                        <?php else : ?>
                            en cours
                        <?php endif;?>
                    </td>
                </tr>
            <?php endforeach;?>
        </table>
        <a class='btn btn-outline-primary' href='<?php hecho($exportUrl); ?>'>
            <i class='fas fa-download'></i>&nbsp;Exporter le journal
        </a>
    </div>

    <br/><br/>
<?php endif;?>
