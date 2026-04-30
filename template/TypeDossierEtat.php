<?php

/**
 * @var int $id_t
 * @var array $typeDossierAction
 * @var int $offset
 * @var int $limit
 * @var int $count
*/
?>

<a class='btn btn-link' href='TypeDossier/detail?id_t=<?php echo $id_t?>'>
    <i class="fas fa-arrow-left"></i>&nbsp;Détail du type de dossier
</a>

<?php $this->SuivantPrecedent($offset, $limit, $count, "TypeDossier/etat?id_t=$id_t"); ?>
<div class="box" >
    <table class="table table-striped">
        <tr>
            <th>État</th>
            <th>Date</th>
            <th>Utilisateur</th>
            <th>Empreinte sha256</th>
            <th>Message</th>
            <th>Export Json</th>
        </tr>
        <?php foreach ($typeDossierAction as $action) : ?>
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
                <td><?php echo $action['empreinte_sha256'] ?></td>
                <td><?php echo $action['message'] ?></td>
                <td><?php echo $action['export_json'] ?></td>
            </tr>
        <?php endforeach;?>
    </table>
</div>
<?php $this->SuivantPrecedent($offset, $limit, $count, "TypeDossier/etat?id_t=$id_t"); ?>
