<?php

/**
 * @var Gabarit $this
 * @var int $id_ce
 * @var array $connecteurAction
 * @var int $offset
 * @var int $limit
 * @var int $count
*/
?>

<a class='btn btn-link' href='Connecteur/edition?id_ce=<?php echo $id_ce?>'>
    <i class="fa fa-arrow-left"></i>&nbsp;Retour à la définition du connecteur
</a>

<?php $this->suivantPrecedent($offset, $limit, $count, "Connecteur/etat?id_ce=$id_ce"); ?>
<div class="box" >
    <table class="table table-striped">
        <tr>
            <th>État</th>
            <th>Date</th>
            <th>Utilisateur</th>
            <th>Entité</th>
            <th>Type de dossier</th>
            <th>Empreinte sha256</th>
            <th>Message</th>
        </tr>
        <?php foreach ($connecteurAction as $action) : ?>
            <tr>
                <td><?php echo $action['action'] ?></td>
                <td><?php echo time_iso_to_fr($action['date'])?></td>
                <td>
                    <?php if ($action['id_u'] == 0) : ?>
                        Action automatique
                    <?php else : ?>
                        <a href='Utilisateur/detail?id_u=<?php echo $action['id_u']?>'><?php hecho($action['prenom']); ?> <?php hecho($action['nom']); ?></a>
                    <?php endif;?>
                </td>
                <td>
                    <?php if ($action['id_e'] == 0) : ?>
                        Entité racine
                    <?php else : ?>
                        <a href='Entite/detail?id_e=<?php echo $action['id_e']?>'><?php hecho($action['denomination']); ?></a>
                    <?php endif;?>
                </td>
                <td><?php echo $action['type_dossier'] ?></td>
                <td><?php echo $action['empreinte_sha256'] ?></td>
                <td><?php hecho($action['message']); ?></td>
            </tr>
        <?php endforeach;?>
    </table>
</div>
<?php $this->suivantPrecedent($offset, $limit, $count, "Connecteur/etat?id_ce=$id_ce"); ?>
